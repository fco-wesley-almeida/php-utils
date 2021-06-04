<?php
namespace App\Database;

use App\Services\LogService;
use Exception;
use PDO;
use PDOException;
use PDOStatement;
use stdClass;

/**
 * Class DatabaseConn
 * @package App\Database
 */
abstract class DatabaseConn {

    protected $username;
    protected $hostspec;
    protected $password;
    protected $database;
    protected $pdoConfig;
    protected $connection;
    protected $resultArray;
    protected $countRow;
    protected $environment;
    protected $dbSearchObj;
    protected $resultObj;
    protected $error;
    private $keepConnectionAlive;
    protected const DEVELOPMENT = 1;
    protected const QA = 2;
    protected const PRODUCTION = 3;
    public const FETCH_AS_SINGLE_OBJ = 4;
    public const FETCH_AS_OBJ_ARR = 5;
    public const FETCH_AS_ASSOC_ARR = 6;

    // colocar os erros corretos


    /**
     * DatabaseConn constructor.
     */
    public final function __construct(bool $keepConnectionAlive = false)
	{
	    $this->keepConnectionAlive = $keepConnectionAlive;
		$this->environment = self::DEVELOPMENT;
	}

    abstract protected function configureAcessCredentials(): void;
    abstract protected function configurePDOConfig(): void;
    abstract protected function configureAfterConnection(): void;

    public function getError(): DatabaseError {
        return $this->error;
	}

    /**
     * @return bool
     */
    public final function connect(): bool
	{
		$this->configureAcessCredentials();
		$this->configurePDOConfig();
		try {
			$this->connection = new PDO($this->pdoConfig, $this->username, $this->password);
			$this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->configureAfterConnection();
			LogService::logInfo("Uma conexão com o banco de dados {$this->hostspec}:{$this->database} foi estabelecida.", $_SERVER);
			return true;
		} catch (PDOException $pdoException) {
			$this->logPDOException($pdoException, "Uma conexão com o banco de dados {$this->hostspec}:{$this->database} não pôde ser realizada.");
			return false;
		}
	}


    /**
     * @param PDOStatement $stmt
     * @param array $binds
     * @param string $sql
     * @return void
     * @throws Exception
     */
    private function configureBinds(PDOStatement $stmt, array $binds, string $sql): void
	{
		foreach ($binds as $key => $value) {
			if (preg_match('/^bin_/', $key)) {
				$stmt->bindValue(":$key", $value, PDO::PARAM_LOB);
				continue;
			}
			$type = gettype($value);
			$pdoParamType = [
					'boolean' => PDO::PARAM_BOOL,
					'integer' => PDO::PARAM_INT,
					'double' => PDO::PARAM_STR,
					'string' => PDO::PARAM_STR,
					'array' => - 1,
					'object' => - 1,
					'resource' => - 1,
					'NULL' => PDO::PARAM_NULL,
					'unknown type' => - 1
			][$type];
			if ($pdoParamType === -1) {
				$bindsJSON = json_encode($binds);
				throw new Exception("Erro na consulta \"$sql\": o parâmetro $key tem erro em sua tipagem: $type. Binds: $bindsJSON");
			}
            $stmt->bindValue(":$key", $value, $pdoParamType);
		}
	}

    /**
     * @param PDOStatement $stmt
     * @return bool
     */
    private function fetchResultAsArray(PDOStatement $stmt): bool
	{
		$this->resultArray = [];
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			if ($row === false) {
				return false;
			}
			$encodedRow = [];
			foreach ($row as $key => $value) {
				$encodedRow[$key] = utf8_encode($value);
			}
			$this->resultArray[] = $encodedRow;
		}
		return true;
	}

    /**
     * @param PDOStatement $stmt
     * @param string $objectFetch
     * @return bool
     */
    private function fetchResultAsSingleObject(PDOStatement $stmt, string $objectFetch): bool
	{
		// If $objectFetch is defined, so fetch the result as $objectFetch. Else, fetch result as stdclass.
		$obj = $objectFetch ? $stmt->fetchObject($objectFetch) : $stmt->fetch(PDO::FETCH_OBJ);
		if ($obj) {
			if ($objectFetch) {
				$this->dbSearchObj = $obj;
			} else {
				$this->resultObj = $obj;
			}
		}
		return !!$obj;
	}

    /**
     * @param PDOStatement $stmt
     * @param string $objectFetch
     * @return bool
     */
    private function fetchResultAsObjectsArray(PDOStatement $stmt, string $objectFetch): bool
	{
		$resultArray = $stmt->fetchAll(PDO::FETCH_CLASS, $objectFetch);
		$sucessFetch = $resultArray !== false;
		if ($sucessFetch) {
			$this->resultArray = $resultArray;
		}
		return $sucessFetch;
	}

    /**
     * @param PDOStatement $stmt
     * @return bool
     */
    private function fetchResultAsStdClassObjArray(PDOStatement $stmt): bool
	{
		$resultArray = $stmt->fetchAll(PDO::FETCH_OBJ);
		$sucessFetch = $resultArray !== false;
		if ($sucessFetch) {
			$this->resultArray = $resultArray;
		}
		return $sucessFetch;
	}

    /**
     * @param string $sql
     * @param array $binds
     * @param int $fetchConfig
     * @param string $objectFetch
     * @return bool
     * @throws Exception
     */
    public final function select(string $sql, array $binds = [], int $fetchConfig = self::FETCH_AS_ASSOC_ARR, string $objectFetch = ''): bool
	{
		try {
			LogService::logInfo("A seguinte consulta SQL foi realizada: $sql.", $binds);
			$stmt = $this->connection->prepare($sql);
			$this->configureBinds($stmt, $binds, $sql);
			$stmt->execute();
            $result = false;
			switch ($fetchConfig){
				case self::FETCH_AS_ASSOC_ARR:
                    $result = $this->fetchResultAsArray($stmt);
                    break;
				case self::FETCH_AS_OBJ_ARR:
                    $result =  $objectFetch
						? $this->fetchResultAsObjectsArray($stmt, $objectFetch)
						: $this->fetchResultAsStdClassObjArray($stmt);
                    break;
				case self::FETCH_AS_SINGLE_OBJ:
                    $result = $this->fetchResultAsSingleObject($stmt, $objectFetch);
                    break;
			}
			if (!$this->keepConnectionAlive) {
			    $this->disconnect();
            }
			return $result;
		} catch (PDOException $pdoException) {
            $this->storeError($pdoException);
            $this->logPDOException($pdoException, "Ocorreu uma falha na consulta \"$sql\".");
            return false;
		}
	}

    /**
     * @param string $sql
     * @param array $binds
     * @return bool
     * @throws Exception
     */
    public final function persist(string $sql, array $binds=[]): bool
	{
		try {
			LogService::logInfo("A seguinte consulta SQL foi realizada: $sql", $binds);
			$stmt = $this->connection->prepare($sql);
			$this->configureBinds($stmt, $binds, $sql);
			$stmt->execute();
			$this->setCountRow($stmt->rowCount());
			return true;
		} catch (PDOException $pdoException) {
            $this->storeError($pdoException);
			$this->logPDOException($pdoException, "Ocorreu uma falha na consulta \"$sql\".");
            return false;
		}
	}

    /**
     * @param string $sql
     * @param array $binds
     * @return int
     * @throws Exception
     */
    public final function insert(string $sql, array $binds=[]): int
	{
		$id = 0;
		if ($this->persist($sql, $binds)) {
			$connection = $this->getConnection();
			$id = $this->getCountRow() > 0 ? $connection->lastInsertId() : 0;
		}
		if ($this->getConnection() && !$this->keepConnectionAlive) {
            $this->disconnect();
        }
		return $id;
	}

    /**
     *
     */
    public final function disconnect(): void
	{
		LogService::logInfo("A conexão com o banco de dados {$this->hostspec}:{$this->database} foi fechada.", $_SERVER);
		$this->connection = null;
	}

    /**
     * @param PDOException $pdoException
     * @param string $message
     */
    private function logPDOException (PDOException $pdoException, string $message): void {
		$pdoExceptionArray = [
    		$pdoException->getCode(),
    		$pdoException->getMessage(),
    		$pdoException->getTraceAsString(),
    		$pdoException->errorInfo
		];
		LogService::logError($pdoException->getCode(), $message, $pdoExceptionArray);
	}

    /**
     * @param PDOException $pdoException
     */
    private function storeError (PDOException $pdoException): void
	{
	    $this->error = new DatabaseError();
	    $this->error->setCode($pdoException->getCode());
	    $this->error->setMessage($pdoException->getMessage());
	}
	
	/**
	 *
	 * @return string
	 */
	public final function getUsername(): string
	{
		return $this->username;
	}
	
	/**
	 *
	 * @return array
	 */
	public final function getResultArray(): array
	{
		return $this->resultArray;
	}
	
	/**
	 *
	 * @return stdClass
	 */
	public final function getResultObj(): stdClass
	{
		return $this->resultObj;
	}
	
	/**
	 *
	 * @return string
	 */
	public final function getHostspec(): string
	{
		return $this->hostspec;
	}
	
	/**
	 *
	 * @return string
	 */
	public final function getPassword(): string
	{
		return $this->password;
	}
	
	/**
	 *
	 * @return string
	 */
	public final function getDatabase(): string
	{
		return $this->database;
	}

	
	/**
	 *
	 * @return PDO
	 */
	public final function getConnection(): ?PDO
	{
		return $this->connection;
	}
	
	/**
	 *
	 * @return int
	 */
	public final function getCountRow(): int
	{
		return $this->countRow;
	}
	
	/**
	 *
	 * @param string $username
	 */
	public final function setUsername(string $username): void
	{
		$this->username = $username;
	}
	
	/**
	 *
	 * @param string $hostspec
	 */
	public final function setHostspec(string $hostspec): void
	{
		$this->hostspec = $hostspec;
	}
	
	/**
	 *
	 * @param string $password
	 */
	public final function setPassword(string $password): void
	{
		$this->password = $password;
	}
	
	/**
	 *
	 * @param string $database
	 */
	public final function setDatabase(string $database): void
	{
		$this->database = $database;
	}
	
	/**
	 *
	 * @param PDO $connection
	 */
	public final function setConnection(?PDO $connection): void
	{
		$this->connection = $connection;
	}
	
	/**
	 *
	 * @param array $resultArray
	 */
	public final function setResultArray(array $resultArray): void
	{
		$this->resultArray = $resultArray;
	}
	
	/**
	 *
	 * @param int $countRow
	 */
	public final function setCountRow(int $countRow): void
	{
		$this->countRow = $countRow;
	}
}
?>