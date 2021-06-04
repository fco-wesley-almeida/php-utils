<?php

namespace App\Database;



class DatabaseExample extends MysqlConn
{
    protected function configureAcessCredentials(): void
    {
        $connectionConfig = [
            parent::DEVELOPMENT => [
                'username' => 'admin2',
                'hostspec' => 'localhost',
                'password' => 'admin',
                'database' => 'database_example'
            ],
            parent::QA => [
                'username' => 'admin2',
                'hostspec' => 'localhost',
                'password' => 'admin',
                'database' => 'database_example_qa'
            ],
            parent::PRODUCTION => [
                'username' => 'admin2',
                'hostspec' => 'localhost',
                'password' => 'admin',
                'database' => 'database_example_prod'
            ]
        ];
        
        $this->username = $connectionConfig[$this->environment]['username'];
        $this->hostspec = $connectionConfig[$this->environment]['hostspec'];
        $this->password = $connectionConfig[$this->environment]['password'];
        $this->database = $connectionConfig[$this->environment]['database'];
    }
}
