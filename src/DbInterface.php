<?php

namespace Amxm\Db;


interface DbInterface
{

    function row($sql, $vars);

    function rows($sql, $vars);

    function col($sql, $vars);

    function insert($table, $data);

    function update($table, $data, $vars);

}
