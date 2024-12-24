<?php

require './vendor/autoload.php';

dump('Playground test..');

try {
    $db = new \Amxm\Db\PDO('mysql:dbname=test;host=127.0.0.1', 'root', '12');
} catch(\Exception $e) {
    die('ERROR connect to mysql: '.$e->getMessage());
}


$rows = $db->row("SELECT * FROM tests");
dd($rows);