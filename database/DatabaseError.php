<?php


namespace App\Database;


class DatabaseError
{
    protected $code;
    protected $message;

    /**
     * @return mixed
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @param mixed $code
     * @return DatabaseError
     */
    public function setCode(int $code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param mixed $message
     * @return DatabaseError
     */
    public function setMessage(string $message)
    {
        $this->message = $message;
        return $this;
    }
}