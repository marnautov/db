<?php

use PHPUnit\Framework\TestCase;
use Amxm\Db\PDOAdapter;

class PDOAdapterTest extends TestCase
{
    private $db;
    private $lastInsertId;

    protected function setUp(): void
    {
        $pdo = new PDO('mysql:dbname=test;host=127.0.0.1', 'root', '12');
        $this->db = new PDOAdapter($pdo);

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
        $this->db->query("SELECT * from tests WHERE status=?",['active']);
        while($row = $this->db->fetch()){
            $row['info'] = $this->db->row("SELECT NOW() as date,'love'");
            $rows[] = $row;
        }
        $this->assertEquals(count($rows), 2);

        $this->assertEquals($rows[1]['info']['love'], 'love');

    }


    public function testUpdates()
    {

        $this->insertSamples();

        $countAffected = $this->db->update('tests', ['title'=>'New Title'], 'status=?', ['active']);
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


    public function testQueries(): void{

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


 


    protected function tearDown(): void
    {
        $this->db->query('DELETE FROM tests');
    }
}