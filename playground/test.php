<?php

require './vendor/autoload.php';

dump('Playground test..');


$db = new \Amxm\Db\PDO('mysql:dbname=test;host=127.0.0.1', 'root', '12');

$rows = $db->row("SELECT * FROM tests");
dd($rows);