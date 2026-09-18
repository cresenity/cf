<?php

use PHPUnit\Framework\TestCase;

/**
 * CApp_Navigation_Helper::nav() dengan struktur nav eksplisit (tanpa route aktif — di CLI
 * controller/method diberikan langsung): pencocokan controller+method, alias method, uri literal,
 * aliases wildcard, daftar action, subnav rekursif; plus url()/childCount()/haveChild()/isLeaf().
 */
class AppNavigationHelperTest extends TestCase {
    /**
     * @return array
     */
    protected function navs() {
        return [
            [
                'name' => 'dashboard',
                'label' => 'Dasbor',
                'controller' => 'home',
                'method' => 'index',
                'alias' => ['dashboard'],
            ],
            [
                'name' => 'master',
                'label' => 'Master',
                'subnav' => [
                    [
                        'name' => 'master.item',
                        'controller' => 'item',
                        'method' => 'index',
                        'path' => 'master/',
                        'action' => [
                            ['method' => 'add'],
                            ['method' => 'edit'],
                            ['controller' => 'itemImport', 'method' => 'index'],
                        ],
                    ],
                    [
                        'name' => 'master.supplier',
                        'uri' => 'master/supplier/list',
                        'aliases' => ['master/supplier/*'],
                    ],
                ],
            ],
            [
                'name' => 'laporan',
                'link' => 'https://laporan.contoh.test',
            ],
        ];
    }

    /**
     * @param string      $controller
     * @param string      $method
     * @param string      $path
     *
     * @return null|string
     */
    protected function activeName($controller, $method, $path = '') {
        foreach ($this->navs() as $nav) {
            $found = CApp_Navigation_Helper::nav($nav, $controller, $method, $path);
            if ($found !== false) {
                return $found['name'];
            }
        }

        return null;
    }

    public function testMatchesByControllerAndMethod() {
        $this->assertSame('dashboard', $this->activeName('home', 'index'));
        $this->assertNull($this->activeName('home', 'lain'));
        $this->assertNull($this->activeName('lain', 'index'));
    }

    public function testMethodAliasMatches() {
        $this->assertSame('dashboard', $this->activeName('home', 'dashboard'), 'alias method dianggap sama dengan method utama');
    }

    public function testPathMustMatchTooAndSubnavIsSearchedRecursively() {
        $this->assertSame('master.item', $this->activeName('item', 'index', 'master/'));
        $this->assertNull($this->activeName('item', 'index', ''), 'path berbeda → bukan menu itu');
        $this->assertNull($this->activeName('item', 'index', 'lain/'));
    }

    public function testActionListMatchesAlternativeMethodsAndControllers() {
        $this->assertSame('master.item', $this->activeName('item', 'add', 'master/'));
        $this->assertSame('master.item', $this->activeName('item', 'edit', 'master/'));
        $this->assertSame('master.item', $this->activeName('itemImport', 'index', 'master/'), 'action boleh menunjuk controller lain di path yang sama');
        $this->assertNull($this->activeName('item', 'delete', 'master/'));
    }

    public function testUriAndWildcardAliasesMatchTheRoutedUri() {
        $this->assertSame('master.supplier', $this->activeName('supplier', 'list', 'master/'), 'uri literal = path.controller/method');
        $this->assertSame('master.supplier', $this->activeName('supplier', 'edit', 'master/'), 'aliases dengan * mencakup semua method');
        $this->assertSame('master.supplier', $this->activeName('supplier', 'x', 'master/'));
        $this->assertNull($this->activeName('supplier', 'list', 'lain/'));
    }

    public function testNavReturnsTheMatchedEntryItself() {
        $found = CApp_Navigation_Helper::nav($this->navs()[0], 'home', 'index');

        $this->assertSame('Dasbor', $found['label']);
        $this->assertFalse(CApp_Navigation_Helper::nav($this->navs()[2], 'home', 'index'), 'entri link eksternal tanpa controller tidak pernah aktif');
    }

    public function testUrlBuildsFromPathControllerMethodOrUsesLink() {
        $this->assertSame(curl::base() . 'master/item/index', CApp_Navigation_Helper::url(['path' => 'master', 'controller' => 'item', 'method' => 'index']));
        $this->assertSame(curl::base() . 'home/index', CApp_Navigation_Helper::url(['controller' => 'home', 'method' => 'index']));
        $this->assertSame('https://laporan.contoh.test', CApp_Navigation_Helper::url(['link' => 'https://laporan.contoh.test', 'controller' => 'x', 'method' => 'y']), 'link menang');
        $this->assertSame('', CApp_Navigation_Helper::url(['controller' => 'home']), 'tanpa method → kosong');
        $this->assertSame('', CApp_Navigation_Helper::url(['method' => 'index']), 'tanpa controller → kosong');
    }

    public function testChildHelpers() {
        $navs = $this->navs();

        $this->assertSame(2, CApp_Navigation_Helper::childCount($navs[1]));
        $this->assertTrue(CApp_Navigation_Helper::haveChild($navs[1]));
        $this->assertFalse(CApp_Navigation_Helper::haveChild($navs[0]));
        $this->assertTrue(CApp_Navigation_Helper::isLeaf($navs[0]), 'tanpa subnav = daun');
        $this->assertFalse(CApp_Navigation_Helper::isLeaf($navs[1]));
        $this->assertSame([], CNavigation_Data::resolveSubnav($navs[0]));
        $this->assertSame([], CNavigation_Data::resolveSubnav('bukan array'));
    }

    public function testSubnavMayBeAClosureResolvedOnce() {
        $calls = 0;
        $nav = [
            'name' => 'dinamis_' . uniqid(),
            'subnav' => function () use (&$calls) {
                $calls++;

                return [['name' => 'anak', 'controller' => 'anak', 'method' => 'index']];
            },
        ];

        $this->assertSame(1, CApp_Navigation_Helper::childCount($nav));
        $this->assertSame('anak', CApp_Navigation_Helper::nav($nav, 'anak', 'index')['name']);
        $this->assertSame(1, $calls, 'closure subnav dievaluasi sekali per nama nav');
    }
}
