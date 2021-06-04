<?php

namespace App\Database;



abstract class MysqlConn extends DatabaseConn
{
//    protected const FOREIGN_KEY_ERROR = 23000;
    
    abstract protected function configureAcessCredentials(): void;
    
    protected function configurePDOConfig(): void {
        $this->pdoConfig = "mysql:host={$this->hostspec};dbname={$this->database};charset=UTF8";
    }
    
    protected function configureAfterConnection(): void 
    {
//        $this->connection->setAttribute(PDO::SQLSRV_ATTR_ENCODING, PDO::SQLSRV_ENCODING_SYSTEM);
    }
}

?>
