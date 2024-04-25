<?php

namespace Amxm\Db;

use Exception;
use \PDO;

/**
 * Class PDOAdapter
 * version 0.56
 */
class PDOAdapter implements DbInterface
{

    private $db;
    private object $cache;
    private $cacheTimeout;
    private $lastStmt;

    private $listener;

    public function __construct(PDO $db, $cache = false)
    {
        $this->db = $db;
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        if ($cache) $this->cache = $cache;
        $this->cacheTimeout = false;
    }

    function setCacheAdapter($cache)
    {
        $this->cache = $cache;
    }

    public function cache($timeout)
    {
        $this->cacheTimeout = intval($timeout);
        return $this;
    }

    public function listen($callback)
    {
        $this->listener = $callback;
    }


    public function query(string $sql, array $vars = array())
    {
        $stmt = $this->db->prepare($sql);
        //$stmt->execute($vars);

        $this->executeStatement($stmt, $vars);

        // try {
        //     $this->executeStatement($stmt, $vars);
        // } catch (Exception $e) {
        //     throw new Exception("SQL ERROR: {$sql}");
        // }
        

        $this->lastStmt = $stmt;

        return $stmt;
    }


    // since 0.4.5, only for queries now (where $this->lastStmt exist)
    public function rowCount()
    {
        return (isset($this->lastStmt)?$this->lastStmt->rowCount():false);
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



    private function executeStatement(&$stmt, $vars = null, $binds = null){

        $timeStart = microtime(true);

        $r = $stmt->execute($vars);

        if (isset($this->listener) && is_callable($this->listener)) {
            call_user_func($this->listener, ['sql'=>$stmt->queryString, 'bindings'=> ($vars??$binds), 'time'=> round(microtime(true)-$timeStart, 5)]);
        }

        return $r;
    }


    private function execute($sql, $vars, $type)
    {

        if ($this->cacheTimeout && $this->cache) {
            $key = md5($sql . '-' . serialize($vars) . '-' . $type);
            $result = $this->cache->get($key);
            if ($result) return $result;
        }


        $stmt = $this->db->prepare($sql);
        //$stmt->execute($vars);
        $this->executeStatement($stmt, $vars);


        switch ($type) {
            case 'FETCH_ASSOC':
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $result = $this->formatArrayBlocks($sql, $result, false);
                break;
            case 'FETCH_ALL_ASSOC':
                $result =  $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result = $this->formatArrayBlocks($sql, $result, true);
                break;
            case 'FETCH_COLUMN':
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
            if (isset($result['ARRAY_VALUE'])) $result = $result['ARRAY_VALUE'];
            if (isset($result['ARRAY_KEY'])) unset($result['ARRAY_KEY']); // There is no effect in a single row, but it is better to remove it.
        }

        return $result;

    }

    /**
     * INSERT ON DUPLICATE KEY UPDATE
     */
    public function insertUpdate($table, $data, $updateData = null, $options = [])
    {

        if ($updateData === null || $updateData === false) $updateData = $data;

        $set = [];
        foreach ($data as $key => $value) {
            $set['columns'][] = $key;
            if (is_object($value) && property_exists($value, 'scalar')) {
                $set['values'][] = $value->scalar;
            } else {
                $set['values'][] = ':' . $key;
                $set['binds'][$key] = $value;
            }
        }

        // $updateData
        $updSqlData = [];
        $updSql = '';
        foreach ($updateData as $key => $value) {
            //$set['columns'][] = $key;
            if (is_object($value) && property_exists($value, 'scalar')) {
                //$set['upd-values'][] = $value->scalar;
                $updSqlData[]="{$key} = ".$value->scalar;
            } else {
                //$set['upd-values'][] = ':upd' . $key;
                $set['binds']['upd___'.$key] = $value;
                $updSqlData[]="{$key} = :upd___" . $key;
            }
        }
        $updSql = implode(', ', $updSqlData);
        //var_dump($updSql);

        $columns = implode(',', $set['columns']);
        $values = implode(', ', $set['values']);

        $sql = (isset($options['replace'])?"REPLACE":"INSERT").(isset($options['ignore'])?" IGNORE":"")." INTO {$table} ({$columns}) VALUES ({$values})";

        $sql.=" ON DUPLICATE KEY UPDATE " . $updSql;

        //var_dump($sql);

        $stmt = $this->db->prepare($sql);
        foreach ($set['binds'] as $key => $value) {
            $stmt->bindValue(":$key", $value);
            //var_dump("bind :$key \n");
        }
        // foreach ($set['upd-binds'] as $key => $value) {
        //     $stmt->bindValue(":upd".$key, $value);
        //     //var_dump("bind :upd{$key} \n");
        // }

        // $r = $stmt->execute();
        $r = $this->executeStatement($stmt, null, $set['binds']);

        //var_dump($this->db->lastInsertId());
        //var_dump($stmt->rowCount());

        if ($r === false) {
            var_dump($stmt->errorInfo()); // Debug statement: display the error message and code
        }

        $this->lastStmt = $stmt;

        return $this->db->lastInsertId($r);

    }


    public function insert($table, $data, $options = [])
    {

        $set = [];
        foreach ($data as $key => $value) {
            $set['columns'][] = $key;
            if (is_object($value) && property_exists($value, 'scalar')) {
                $set['values'][] = $value->scalar;
            } else {
                $set['values'][] = ':' . $key;
                $set['binds'][$key] = $value;
            }
        }

        $columns = implode(',', $set['columns']);
        $values = implode(', ', $set['values']);

        $sql = (isset($options['replace'])?"REPLACE":"INSERT").(isset($options['ignore'])?" IGNORE":"")." INTO {$table} ({$columns}) VALUES ({$values})";
        $stmt = $this->db->prepare($sql);
        foreach ($set['binds'] as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        // $r = $stmt->execute();
        $r = $this->executeStatement($stmt, null, $set['binds']);

        if ($r === false) {
            var_dump($stmt->errorInfo()); // Debug statement: display the error message and code
        }
        return $this->db->lastInsertId();
    }


    // alias to insert with ignore
    public function insertIgnore($table, $data)
    {
        // @todo: merge options
        $this->insert($table, $data, ['ignore'=>true]);
    }

    public function replace($table, $data)
    {
        $this->insert($table, $data, ['replace' => true]);
    }


    public function insertMany($table, $dataSet, $options = [])
	{
		$set = [];
		
		$columns = [];
		$binds = [];
		$rowCount = 0;
		
		// Build the SQL statement and bind parameters for each row of data
		
		foreach ($dataSet as $data) {
			$set['values'] = [];
			foreach ($data as $key => $value) {
				if ($rowCount == 0) {
					$set['columns'][] = $key;
				}
				if (is_object($value) && property_exists($value, 'scalar')) {
					$set['values'][] = $value->scalar;
				} else {
					$set['values'][] = ':' . $key . '_' . $rowCount;
					$binds[$key . '_' . $rowCount] = $value;
				}
			}
			$values[] = '(' . implode(', ', $set['values']) . ')';
			$rowCount++;
		}

		
		$columns = implode(',', $set['columns']);
		$values = implode(', ', $values);
		$sql = "INSERT".(isset($options['ignore'])?" IGNORE":"")." INTO {$table} ({$columns}) VALUES {$values}";
        // Prepare and execute the SQL statement with the bound parameters
		$stmt = $this->db->prepare($sql);
		foreach ($binds as $key => $value) {
			$stmt->bindValue(":$key", $value);
		}
		//$r = $stmt->execute();
        $r = $this->executeStatement($stmt, null, $binds);

        $rowCount = $stmt->rowCount();

        $this->lastStmt = $stmt;

		// If the insert was successful, return the number of rows inserted
		if ($r) {
			return $rowCount;
		} else {
			var_dump($stmt->errorInfo()); // Debug statement: display the error message and code
			return false;
		}

    }



    public function update ($table, $data, $where = null, $vars = array()) {

        $set = array();
        foreach($data as $column => $value) {
            if (is_object($value) && property_exists($value, 'scalar')) {
                $set[] = $column . '=' . $value->scalar;
                unset($data[$column]);
            } else {
                $set[] = $column . '=?';
            } 
        }
        $sql = 'UPDATE ' . $table . ' SET ' . implode(',', $set) . ($where?' WHERE ' . $where:'');
        $stmt = $this->db->prepare($sql);
        //$stmt->execute(array_merge(array_values($data), $vars));
        $this->executeStatement($stmt, array_merge(array_values($data), $vars));
        return $stmt->rowCount();
      }


    public function func($mysqlFunction)
    {
        // or maybe another variant 'date ' => (object)'NOW()'
        // https://www.php.net/manual/ru/language.types.object.php#language.types.object.casting
        // since v0.4.5
        return (object)$mysqlFunction;
        // $obj = new \stdClass;
        // $obj->mysqlFunction = $mysqlFunction;
        // return $obj;
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


    public function quote($string)
    {
        return $this->db->quote($string);
    }


}