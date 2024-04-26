<?php

use PHPUnit\Framework\TestCase;
use Amxm\Db\PDOAdapter;

class PDOAdapterTest extends TestCase
{
    private $db;
    private $lastInsertId;

    protected function setUp(): void
    {
        // $pdo = new PDO('mysql:dbname=test;host=127.0.0.1', 'root', '12');
        // $this->db = new PDOAdapter($pdo);

        $this->db = new \Amxm\Db\PDO('mysql:dbname=test;host=127.0.0.1', 'root', '12');
        
        //$this->db->setCacheAdapter($cache);


        $this->db->query("CREATE TABLE IF NOT EXISTS tests (
            id INT AUTO_INCREMENT,
            title VARCHAR(255),
            status VARCHAR(50),
            date TIMESTAMP,
            date2 TIMESTAMP,
            PRIMARY KEY (id)
        );");

        // $this->db->listen(function($queryInfo){
        //     print_r ($queryInfo);
        // });

    }


    private function insertSamples()
    {

        $this->db->insert('tests', [
            'title' => 'hello start',
            'status' => 'active',
            'date' => $this->db->now(),
            'date2' => $this->db->now(),
        ]);

        $this->lastInsertId = $this->db->insert('tests', [
            'title' => 'hello',
            'status' => 'active',
            'date' => $this->db->now(),
            'date2' => $this->db->now(),
        ]);
    }


    public function testPDOConstructor()
    {

        $db = new \Amxm\Db\PDO('mysql:dbname=test;host=127.0.0.1', 'root', '12');
        $this->assertInstanceOf(\Amxm\Db\PDOAdapter::class, $db);

    }


    public function testInsertUpdate()
    {

        $ins = [
            'id'    =>  55,
            'title' => 'hello start',
            'status' => 'active',
            'date' => $this->db->now(),
            'date2' => $this->db->now(),
        ];

        $insertId = $this->db->insertUpdate('tests', $ins, ['status'=>'DUPLICATE']);
        $this->assertEquals($insertId, 55);
        $rowCount = $this->db->rowCount();
        $this->assertEquals($rowCount, 1); // 1 = insert

        $row = $this->db->row("SELECT * FROM tests WHERE id=55");
        //print_r ($row);
        $this->assertEquals($row['status'], 'active');

        $this->db->insertUpdate('tests', $ins, ['status'=>'DUPLICATE', 'date2'=>(object)'NOW()+INTERVAL 1 YEAR']);
        $row = $this->db->row("SELECT * FROM tests WHERE id=55");
        //print_r ($row);
        $this->assertEquals($row['status'], 'DUPLICATE');

        $insertOrUpdatedId = $this->db->insertUpdate('tests', $ins, ['status'=>'D1', 'title'=>'T1']);
        $rowCount = $this->db->rowCount();
        $this->assertEquals($rowCount, 2); // 2  = updated

        // var_dump($insertOrUpdatedId);
        // var_dump($this->db->rowCount());

        $row = $this->db->row("SELECT * FROM tests WHERE id=55");
        //print_r ($row);
        $this->assertEquals($row['status'], 'D1');
        $this->assertEquals($row['title'], 'T1');


    }


    public function testSelect()
    {

        $this->insertSamples();

        $row = $this->db->row("SELECT * FROM tests WHERE 1 LIMIT 1");
        $this->assertIsArray($row);

        $row = $this->db->row("SELECT * FROM tests WHERE id=? LIMIT 1", [$this->lastInsertId]);
        $this->assertIsArray($row);

        $resultId = $this->db->col("SELECT id FROM tests WHERE id=? LIMIT 1", [$this->lastInsertId]);
        $this->assertEquals($resultId, $this->lastInsertId);

        $count = $this->db->col("SELECT count(*) FROM tests WHERE 1");
        $this->assertEquals($count, 2);

        $rows = $this->db->rows("SELECT id FROM tests WHERE 1");
        $this->assertEquals($rows[1]['id'], $this->lastInsertId);


        $rows = $this->db->rows("SELECT id as ARRAY_KEY, title as ARRAY_VALUE FROM tests WHERE 1");
        $this->assertEquals($rows[$this->lastInsertId], 'hello');


        $rows = $this->db->rows("SELECT id as ARRAY_KEY, title as ARRAY_VALUE FROM tests WHERE id=?", [$this->lastInsertId]);
        $this->assertEquals($rows[$this->lastInsertId], 'hello');


        /**
         * ARRAY_KEY
         */
        $rows = $this->db->rows("SELECT *, id as ARRAY_KEY FROM tests WHERE 1");
        $this->assertArrayNotHasKey('ARRAY_KEY', $rows[$this->lastInsertId]);

        // fix v0.4.5
        $row = $this->db->row("SELECT *, id as ARRAY_KEY FROM tests WHERE 1");
        $this->assertArrayNotHasKey('ARRAY_KEY', $row);

        $this->db->query("SELECT * from tests WHERE 1 LIMIT 1");
        $row = $this->db->fetch();
        $this->assertArrayNotHasKey('ARRAY_KEY', $row);


        // $row = $this->db->row("SELECT * FROM tests WHERE date>?", [$this->db->func('NOW()')]);
        // print_r ($row);


        $rows = array();
        $this->db->query("SELECT * from tests WHERE status=?", ['active']);
        while ($row = $this->db->fetch()) {
            $row['info'] = $this->db->row("SELECT NOW() as date,'love'");
            $rows[] = $row;
        }
        $this->assertEquals(count($rows), 2);

        $this->assertEquals($rows[1]['info']['love'], 'love');
    }


    public function testUpdates()
    {

        $this->insertSamples();

        $countAffected = $this->db->update('tests', ['title' => 'New Title'], 'status=?', ['active']);
        $this->assertEquals($countAffected, 2);

        $rows = $this->db->rows("SELECT * FROM tests");
        $this->assertEquals($rows[0]['title'], 'New Title');
        $this->assertEquals($rows[1]['title'], 'New Title');

        $countAffected = $this->db->update('tests', ['title' => $this->db->func('MOD (29, 9)')]);
        $this->assertEquals($countAffected, 2);

        $rows = $this->db->rows("SELECT * FROM tests");
        $this->assertEquals($rows[0]['title'], '2');
        $this->assertEquals($rows[1]['title'], '2');

        $countAffected = $this->db->update('tests', ['date' => (object)'NOW()-INTERVAL 7 DAY'], 'id=?', [$this->lastInsertId]);
        $this->assertEquals($countAffected, 1);
    }


    public function testInserts()
    {

        $insertId = $this->db->insert('tests', [
            'title' => 'hello start',
            'status' => 'active',
            'date' => $this->db->now(),
            'date2' => $this->db->func('NOW()+INTERVAL 1 DAY'),
        ]);
        $this->assertGreaterThan(0, $insertId);


        $insertId = $this->db->insert('tests', [
            'title' => $this->db->func('MOD(29,9)'),
            'status' => 'active',
            'date' => $this->db->now(),
            'date2' => $this->db->now(),
        ]);
        $this->assertGreaterThan(0, $insertId);

        $row = $this->db->row("SELECT * FROM tests WHERE id=?", [$insertId]);
        $this->assertEquals($row['title'], '2');


        // since v0.4.5
        $insertId = $this->db->insert('tests', [
            'title' => (object)('MOD(1983,39)'),
            'status' => 'active',
            'date' => (object)'NOW()',
            'date2' => (object)'NOW()+INTERVAL 1 DAY',
        ]);
        $this->assertGreaterThan(0, $insertId);

        $row = $this->db->row("SELECT * FROM tests WHERE id=?", [$insertId]);
        $this->assertEquals($row['title'], '33');
    }


    public function testInsertIgnore()
    {


        $insertId = $this->db->insert('tests', [
            'id'    =>  1,
            'title' => 'Hello world',
            'status' => 'active',
            'date' => (object)'NOW()',
            'date2' => (object)'NOW()+INTERVAL 1 DAY',
        ]);
        $this->assertGreaterThan(0, $insertId);

        $insertId = $this->db->insert('tests', [
            'id'    =>  1,
            'title' => 'Hello world Ignore',
            'status' => 'active',
            'date' => (object)'NOW()',
            'date2' => (object)'NOW()+INTERVAL 1 DAY',
        ], ['ignore' => true]);


        $this->assertEquals(0, $insertId);

        $insertId = $this->db->insert('tests', [
            'id'    =>  100,
            'title' => 'Hello world Ignore',
            'status' => 'active',
            'date' => (object)'NOW()',
            'date2' => (object)'NOW()+INTERVAL 1 DAY',
        ], ['ignore' => true]);

        // check if insert ignore return insertId
        $this->assertEquals(100, $insertId);

        $insertId = $this->db->insertIgnore('tests', [
            'id'    =>  101,
            'title' => 'Hello world Ignore',
            'status' => 'active',
            'date' => (object)'NOW()',
            'date2' => (object)'NOW()+INTERVAL 1 DAY',
        ], ['ignore' => true]);

        // check if insert ignore return insertId
        $this->assertEquals(101, $insertId);


    }



    public function testInsertManyIgnore()
    {

        $dataSet = [];
        for ($n = 1; $n < 10; $n++) {
            $dataSet[] = [
                'id'    =>  $n,
                'title' => 'My title number ' . $n,
                'status' => ($n % 2 ? 'active' : 'inactive'),
                'date' => $this->db->now(),
                'date2' => (object)"NOW()+INTERVAL {$n} DAY",
            ];
        }

        $rowCount = $this->db->insertMany('tests', $dataSet);
        $this->assertEquals($rowCount, count($dataSet));


        $rowCount = $this->db->insertMany('tests', $dataSet, ['ignore'=>true]);
        $this->assertEquals($rowCount, 0);


        for ($n = 1; $n <= 5; $n++) {
            $dataSet[] = [
                'id'    =>  $n*100,
                'title' => 'My title number ' . $n,
                'status' => ($n % 2 ? 'active' : 'inactive'),
                'date' => $this->db->now(),
                'date2' => (object)"NOW()+INTERVAL {$n} DAY",
            ];
        }
        $rowCount = $this->db->insertMany('tests', $dataSet, ['ignore'=>true]);
        $this->assertEquals($rowCount, 5);

        $count = $this->db->col("SELECT count(*) FROM tests");
        $this->assertEquals($count, 14);

    }


 


    public function testInsertMany()
    {

        $dataSet = [];
        for ($n = 1; $n < 10; $n++) {
            $dataSet[] = [
                'title' => 'My title number ' . $n,
                'status' => ($n % 2 ? 'active' : 'inactive'),
                'date' => $this->db->now(),
                'date2' => (object)"NOW()+INTERVAL {$n} DAY",
            ];
        }

        $rowCount = $this->db->insertMany('tests', $dataSet);
        $this->assertEquals($rowCount, count($dataSet));

        $rowCountFromMethod = $this->db->rowCount();
        $this->assertEquals($rowCountFromMethod, count($dataSet));

    }


    public function testInsertManyPerfomance()
    {
        $dataSet = [];
        for ($n = 1; $n < 100; $n++) {
            $dataSet[] = [
                'title' => 'My title number ' . $n,
                'status' => ($n % 2 ? 'active' : 'inactive'),
                'date' => $this->db->now(),
                'date2' => (object)"NOW()+INTERVAL {$n} DAY",
            ];
        }

        // multiInsert
        $timeStart = microtime(true);
        $rowCount = $this->db->insertMany('tests', $dataSet);
        $elapsedTime = microtime(true) - $timeStart;

        $this->assertEquals($rowCount, count($dataSet));

        //printf("insertMany() took to execute: %.4f seconds\n", $elapsedTime);
        $this->assertLessThanOrEqual(1, $elapsedTime, sprintf("insertMany() took too long to execute: %.2f seconds", $elapsedTime));

        $this->db->query("TRUNCATE TABLE tests");

        // ordinary
        $timeStart = microtime(true);
        $rowCount = 0;
        foreach ($dataSet    as $ins) {
            $inserId = $this->db->insert('tests', $ins);
            if ($inserId) $rowCount++;
        }
        $elapsedTime = microtime(true) - $timeStart;
        //printf("multi insert() took to execute: %.4f seconds\n", $elapsedTime);

        $this->assertEquals($rowCount, count($dataSet));
    }




    public function testReplace()
    {
        $title = 'id999';
        $this->db->insert('tests', [
            'id'    =>  999,
            'title' => $title,
            'status' => 'active',
            'date' => $this->db->now(),
            'date2' => $this->db->now(),
        ]);

        $titleFromDb = $this->db->col("SELECT title FROM tests WHERE id=?",[999]);
        $this->assertEquals($titleFromDb, $title);

        $newTitle = 'id999v2';
        $this->db->replace('tests', [
            'id'    =>  999,
            'title' => $newTitle,
            'status' => 'active',
            'date' => $this->db->now(),
            'date2' => $this->db->now(),
        ]);

        $titleFromDb = $this->db->col("SELECT title FROM tests WHERE id=?",[999]);
        $this->assertEquals($titleFromDb, $newTitle);

    }




    public function testQueries(): void
    {

        $this->insertSamples();

        $this->db->query("DELETE FROM tests");
        $count = $this->db->rowCount();
        $this->assertEquals($count, 2);


        $this->insertSamples();
        $this->insertSamples();

        $stmt = $this->db->query("DELETE FROM tests");
        $count = $stmt->rowCount();
        $this->assertEquals($count, 4);
    }


    public function testListen()
    {

        $sql = 'SELECT 999';

        $this->db->listen(function($queryInfo) use ($sql){
            //print_r ($queryInfo);
            $this->assertEquals($queryInfo['sql'], $sql);
        });

        $count = $this->db->col($sql);
        $this->assertEquals($count, 999);

        $this->db->listen(null);

    }


    public function testQuote()
    {


        $this->insertSamples();

        $text = "don't give up";

        $this->db->insert('tests', [
            'title' => $text,
            'status' => 'active',
            'date' => $this->db->now(),
            'date2' => $this->db->now(),
        ]);

        $rows = $this->db->rows("SELECT * FROM tests WHERE title LIKE (".$this->db->quote($text).")");
        $this->assertEquals(count($rows), 1);


        $this->db->query("SELECT NOW()");


    }




    protected function tearDown(): void
    {
        $this->db->query('DELETE FROM tests');
    }
}
