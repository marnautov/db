<?php

namespace Amxm\Db;

class PDO extends PDOAdapter {


    function __construct($dsn, $username = null, $password = null, $options = null)
    {
        
        $pdo = new \PDO($dsn, $username, $password);
        parent::__construct($pdo);

    }


}