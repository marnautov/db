<?php

namespace Amxm\Db;
use \PDO;

/**
 * Class PDOAdapter
 * version 0.1 (init release)
 */
class PDOAdapter implements DbInterface
{

    private $db;
    private $cache;
    private $cacheTimeout;

    public function __construct(PDO $db, $cache = false)
    {
        $this->db = $db;
        if ($cache) $this->cache = $cache;
        $this->cacheTimeout = false;
    }

    public function cache($timeout)
    {
        $this->cacheTimeout = intval($timeout);
        return $this;
    }


    public function query($sql, $vars = array())
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($vars);

        return $stmt;
    }


    public function row($sql, $vars = array())
    {
        return $this->execute($sql, $vars, 'FETCH_ASSOC');
    }

    public function rows($sql, $vars = array())
    {
        return $this->execute($sql, $vars, 'FETCH_ALL_ASSOC');
    }

    // ранее result
    public function col($sql, $vars = array())
    {
        return $this->execute($sql, $vars, 'FETCH_COLUMN');
    }


    private function execute($sql, $vars, $type)
    {

        if ($this->cacheTimeout && $this->cache) {
            $key = md5($sql . '-' . serialize($vars) . '-' . $type);
            $result = $this->cache->get($key);
            if ($result) return $result;
        }

        switch ($type) {
            case 'FETCH_ASSOC':
                $stmt = $this->db->prepare($sql);
                $stmt->execute($vars);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                break;
            case 'FETCH_ALL_ASSOC':
                $stmt = $this->db->prepare($sql);
                $stmt->execute($vars);
                $result =  $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
            case 'FETCH_COLUMN':
                $stmt = $this->db->prepare($sql);
                $stmt->execute($vars);
                $result = $stmt->fetchColumn();
                break;
            default:
                throw new \Exception('Invalid query type');
        }


        if (strstr($sql, 'ARRAY_KEY') !== false || strstr($sql, 'ARRAY_VALUE') !== false) {
            $newResult = array();
            foreach ($result as $k => $v) {
                if (isset($v['ARRAY_KEY'])) {
                    $keyName = $v['ARRAY_KEY'];
                    unset($v['ARRAY_KEY']);
                    $newResult[$keyName]=(isset($v['ARRAY_VALUE'])?$v['ARRAY_VALUE']:$v);
                } else {
                    $newResult[]=(isset($v['ARRAY_VALUE'])?$v['ARRAY_VALUE']:$v);
                }
            }
            $result = $newResult;
        }

        if ($this->cacheTimeout && $this->cache) {
            $this->cache->set($key, $result, $this->cacheTimeout);
        }

        $this->cacheTimeout = false;

        return $result;
    }


    public function insert($table, $data)
    {

        $set = [];
        foreach ($data as $key => $value) {
            $set['columns'][] = $key;
            if (is_object($value) && property_exists($value, 'mysqlFunction')) {
                $set['values'][] = $key;
            } else {
                $set['values'][] = ':' . $key;
                $set['binds'][$key] = $value;
            }
        }

        $columns = implode(',', $set['columns']);
        $values = implode(', ', $set['values']);

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$values})";
        // var_dump($sql);
        $stmt = $this->db->prepare($sql);
        foreach ($set['binds'] as $key => $value) {

            $stmt->bindValue(":$key", $value);
        }
        $r = $stmt->execute();
        if ($r === false) {
            var_dump($stmt->errorInfo()); // Debug statement: display the error message and code
        }
        return $this->db->lastInsertId();
    }

    public function update($table, $data, $vars)
    {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "$key=:$key";
        }
        $set = implode(', ', $set);
        $sql = "UPDATE {$table} SET {$set} WHERE {$vars[0]}";
        $stmt = $this->db->prepare($sql);
        foreach ($data as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->execute();
        return $stmt->rowCount();
    }


    public function func($mysqlFunction)
    {
        $obj = new stdClass;
        $obj->mysqlFunction = $mysqlFunction;
        return $obj;
    }

    public function now()
    {
        return date('Y-m-d H:i:s');
    }

//    public function now($interval = false)
//    {
//        return date('Y-m-d H:i:s', ($interval?strtotime($interval):time()));
//    }


}