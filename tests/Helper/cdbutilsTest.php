<?php
use PHPUnit\Framework\TestCase;

/**
 * cdbutils - pembantu query mentah di atas SQLite in-memory. get_value/get_list/row_exists/
 * get_row_count_from_base_query memakai $db->query() → CDatabase_ResultData, yang mem-buffer
 * baris SELECT bila driver tidak mengisi rowCount() (SQLite). table_exists()/empty_table()/
 * get_table_list() memakai sintaks MySQL (SHOW TABLES, ALTER ... AUTO_INCREMENT).
 */
class cdbutilsTest extends TestCase {
    const CONNECTION = 'uji_dbutils';

    /**
     * @var CDatabase_Connection
     */
    protected $db;

    protected function setUp(): void {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('butuh ekstensi pdo_sqlite');
        }
        $manager = CDatabase_Manager::instance();
        $manager->purge(static::CONNECTION);
        $manager->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], static::CONNECTION);
        $this->db = $manager->connection(static::CONNECTION);
        $this->db->statement('create table item (item_id integer primary key autoincrement, code varchar(20), name varchar(50), price integer)');
        $this->db->table('item')->insert([
            ['code' => 'A', 'name' => 'Apel', 'price' => 100],
            ['code' => 'B', 'name' => 'Beras', 'price' => 200],
            ['code' => 'C', 'name' => 'Cabai', 'price' => 300],
        ]);
    }

    protected function tearDown(): void {
        CDatabase_Manager::instance()->purge(static::CONNECTION);
    }

    public function testQueryBasedHelpersSeeTheRowsOnSqlite() {
        // rowCount() SELECT = 0 di SQLite; CDatabase_ResultData mem-buffer barisnya (#-14721)
        $this->assertSame(3, $this->db->query('select * from item')->count());
        $this->assertEquals(3, cdbutils::get_value('select count(*) from item', $this->db));
        $this->assertSame(['A' => 'Apel', 'B' => 'Beras', 'C' => 'Cabai'], cdbutils::get_list('select code, name from item order by code', $this->db));
        $this->assertTrue(cdbutils::row_exists('item', ['code' => 'A'], $this->db));
        $this->assertFalse(cdbutils::row_exists('item', ['code' => 'Z'], $this->db));
        $this->assertEquals(3, cdbutils::get_row_count_from_base_query('select * from item', $this->db));
    }

    public function testQueryResultDataOnABufferedDriver() {
        $result = $this->db->query('select code, price from item order by code');

        $this->assertSame('A', $result[0]->code);
        $this->assertSame('C', $result[2]->code);
        $this->assertTrue(isset($result[2]));
        $this->assertFalse(isset($result[3]));
        $codes = [];
        foreach ($result as $i => $row) {
            $codes[$i] = $row->code;
        }
        $this->assertSame(['A', 'B', 'C'], $codes);
        $this->assertEquals(['code' => 'B', 'price' => 200], $result->result(false)[1], 'PHP 7.4 + SQLite memberi angka sebagai string');
        $this->assertCount(3, $result->resultArray(false));
        $this->assertSame('A', $result->resultArray(true)[0]->code);
        $this->assertSame(0, $this->db->query("select * from item where code = 'Z'")->count());
        $this->assertSame([], $this->db->query("select * from item where code = 'Z'")->resultArray());
    }

    public function testGetRowReturnsOneRowOrNull() {
        $row = cdbutils::get_row("select * from item where code = 'A'", $this->db);
        $this->assertSame('Apel', is_array($row) ? $row['name'] : $row->name);
        $this->assertNull(cdbutils::get_row("select * from item where code = 'Z'", $this->db));
        $first = cdbutils::get_row('select code from item order by price desc', $this->db);
        $this->assertSame('C', is_array($first) ? $first['code'] : $first->code, 'hanya baris pertama');
    }

    public function testGetArrayReturnsTheFirstColumnOfEveryRow() {
        $this->assertSame(['A', 'B', 'C'], cdbutils::get_array('select code, name from item order by code', $this->db));
        $this->assertSame([], cdbutils::get_array("select code from item where code = 'Z'", $this->db));
    }

    /**
     * Dokumentasi, bukan perbaikan: parse_column_type() menyusun $result lalu tidak pernah
     * mengembalikannya (dan bukan static), sehingga selalu null. Tidak ada pemanggil di system/.
     */
    public function testParseColumnTypeReturnsNothing() {
        $utils = new cdbutils();
        $this->assertNull($utils->parse_column_type('INT(11) UNSIGNED'));
        $this->assertNull($utils->parse_column_type('varchar(191)'));
    }
}
