<?php

use PHPUnit\Framework\TestCase;
use Amxm\Db\PDOAdapter;

class DbAdapterTest extends TestCase
{
    private $db;

    protected function setUp(): void
    {
        $pdo = new PDO('mysql:dbname=test;host=127.0.0.1', 'root', '12');
        $this->db = new PDOAdapter($pdo);
    }

    public function testInsert()
    {
        $insertId = $this->db->insert('tests', [
            'title' => 'hello',
            'status' => 'active',
            'date' => $this->db->now(),
            'date2' => $this->db->now(),
        ]);
        $this->assertGreaterThan(0, $insertId);
    }

    public function testSelectRow()
    {

        $this->db->insert('tests', [
            'title' => 'hello start',
            'status' => 'active',
            'date' => $this->db->now(),
            'date2' => $this->db->now(),
        ]);

        $insertId = $this->db->insert('tests', [
            'title' => 'hello',
            'status' => 'active',
            'date' => $this->db->now(),
            'date2' => $this->db->now(),
        ]);
        $this->assertGreaterThan(0, $insertId);


        $row = $this->db->row("SELECT * FROM tests WHERE 1 LIMIT 1");
        $this->assertIsArray($row);

        $row = $this->db->row("SELECT * FROM tests WHERE id=? LIMIT 1", [$insertId]);
        $this->assertIsArray($row);

        $resultId = $this->db->col("SELECT id FROM tests WHERE id=? LIMIT 1", [$insertId]);
        $this->assertEquals($resultId, $insertId);

        $count = $this->db->col("SELECT count(*) FROM tests WHERE 1");
        $this->assertEquals($count, 2);

        $rows = $this->db->rows("SELECT id FROM tests WHERE 1");
        $this->assertEquals($rows[1]['id'], $insertId);


    }


    protected function tearDown(): void
    {
        $this->db->query('DELETE FROM tests');
    }
}