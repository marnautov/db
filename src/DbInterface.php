<?php

namespace Amxm\Db;


interface DbInterface
{

    function row(string $sql, array $vars);

    function rows(string $sql, array $vars);

    function col(string $sql, array $vars);

    function insert(string $table, array $data);

    function update(string $table, array $data, string $where, array $vars);

}
