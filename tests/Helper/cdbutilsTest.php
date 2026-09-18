<?php
use PHPUnit\Framework\TestCase;

/**
 * cdbutils - pembantu query mentah di atas SQLite in-memory. Hanya metode berbasis
 * $db->select() (get_row, get_array) yang bisa diuji di sini: get_value/get_list/row_exists/
 * get_row_count_from_base_query memakai $db->query() → CDatabase_ResultData yang membaca
 * PDOStatement::rowCount(), dan untuk SELECT rowCount() hanya diisi driver MySQL (SQLite
 * mengembalikan 0 → hasil kosong). table_exists()/empty_table()/get_table_list() memakai
 * sintaks MySQL (SHOW TABLES, ALTER ... AUTO_INCREMENT). Semua app CF memakai MySQL, jadi
 * di sini perilaku SQLite itu hanya dikunci sebagai catatan.
 */
class cdbutilsTest extends TestCase {
    const CONNECTION = 'uji_dbutils';

    /** @var CDatabase_Connection */
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

    public function testQueryBasedHelpersSeeNoRowsOnSqlite() {
        // dokumentasi keterbatasan driver, bukan bug cdbutils: rowCount() SELECT = 0 di SQLite
        $this->assertSame(0, $this->db->query('select * from item')->count());
        $this->assertNull(cdbutils::get_value('select count(*) from item', $this->db));
        $this->assertSame([], cdbutils::get_list('select code, name from item', $this->db));
        $this->assertFalse(cdbutils::row_exists('item', ['code' => 'A'], $this->db));
        $this->assertEquals(0, cdbutils::get_row_count_from_base_query('select * from item', $this->db));
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
