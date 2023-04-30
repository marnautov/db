<?php

namespace Amxm\Db;
use \PDO;

/**
 * Class PDOAdapter
 * version 0.4.0
 */
class PDOAdapter implements DbInterface
{

    private $db;
    private $cache;
    private $cacheTimeout;
    private $lastStmt;

    public function __construct(PDO $db, $cache = false)
    {
        $this->db = $db;
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        if ($cache) $this->cache = $cache;
        $this->cacheTimeout = false;
    }

    public function cache($timeout)
    {
        $this->cacheTimeout = intval($timeout);
        return $this;
    }


    public function query(string $sql, array $vars = array())
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($vars);

        $this->lastStmt = $stmt;

        return $stmt;
    }


    public function row(string $sql, array $vars = array())
    {
        return $this->execute($sql, $vars, 'FETCH_ASSOC');
    }

    public function rows($sql, $vars = array())
    {
        return $this->execute($sql, $vars, 'FETCH_ALL_ASSOC');
    }

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
                $result = $this->formatArrayBlocks($sql, $result, false);
                break;
            case 'FETCH_ALL_ASSOC':
                $stmt = $this->db->prepare($sql);
                $stmt->execute($vars);
                $result =  $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result = $this->formatArrayBlocks($sql, $result, true);
                break;
            case 'FETCH_COLUMN':
                $stmt = $this->db->prepare($sql);
                $stmt->execute($vars);
                $result = $stmt->fetchColumn();
                break;
            default:
                throw new \Exception('Invalid query type');
        }


        if ($this->cacheTimeout && $this->cache) {
            $this->cache->set($key, $result, $this->cacheTimeout);
        }

        $this->cacheTimeout = false;

        return $result;
    }


    private function formatArrayBlocks($sql, $result, $multi = false)
    {

        if ($sql && (strstr($sql, 'ARRAY_KEY') === false && strstr($sql, 'ARRAY_VALUE') === false)) return $result;

        if ($multi){
            $newRresult = array();
            foreach ($result as $k => $v){
                if (isset($v['ARRAY_KEY'])){
                    $k = $v['ARRAY_KEY'];
                    unset($v['ARRAY_KEY']);
                }
                if (isset($v['ARRAY_VALUE'])){
                    $v = $v['ARRAY_VALUE'];
                }
                $newRresult[$k] = $v;
            }
            $result = $newRresult;
        } else {
            if (isset($v['ARRAY_VALUE'])) $result = $v['ARRAY_VALUE'];
        }

        return $result;

    }


    public function insert($table, $data)
    {

        $set = [];
        foreach ($data as $key => $value) {
            $set['columns'][] = $key;
            if (is_object($value) && property_exists($value, 'mysqlFunction')) {
                $set['values'][] = $value->mysqlFunction;
            } else {
                $set['values'][] = ':' . $key;
                $set['binds'][$key] = $value;
            }
        }

        $columns = implode(',', $set['columns']);
        $values = implode(', ', $set['values']);

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$values})";
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


    public function update ($table, $data, $where = null, $vars = array()) {

        $set = array();
        foreach($data as $column => $value) {
            if (is_object($value) && property_exists($value, 'mysqlFunction')) {
                $set[] = $column . '=' . $value->mysqlFunction;
                unset($data[$column]);
            } else {
                $set[] = $column . '=?';
            } 
        }
        $sql = 'UPDATE ' . $table . ' SET ' . implode(',', $set) . ($where?' WHERE ' . $where:'');
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge(array_values($data), $vars));
        return $stmt->rowCount();
      }


    public function func($mysqlFunction)
    {
        // or maybe another variant 'date ' => (object)'NOW()'
        $obj = new \stdClass;
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

    // beta
    public function fetch()
    {
        return $this->formatArrayBlocks(false,$this->lastStmt->fetch(),false);  
    }



}