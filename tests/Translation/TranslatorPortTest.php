<?php
use PHPUnit\Framework\TestCase;

/**
 * Port TranslationTranslatorTest + TranslationMessageSelectorTest hulu ke CTranslation_Translator
 * di atas ArrayLoader: penggantian placeholder (kapitalisasi, terpanjang dulu), namespace,
 * choice/plural, JSON lines, has/hasForLocale, addLines, locale/fallback.
 */
class TranslatorPortTest extends TestCase {
    /**
     * @var CTranslation_Loader_ArrayLoader
     */
    protected $loader;

    protected function setUp(): void {
        $this->loader = new CTranslation_Loader_ArrayLoader();
    }

    /**
     * @param string $locale
     *
     * @return CTranslation_Translator
     */
    protected function translator($locale = 'en') {
        return new CTranslation_Translator($this->loader, $locale);
    }

    public function testHasMethodReturnsFalseWhenReturnedTranslationIsNull() {
        $t = $this->translator();
        $this->assertFalse($t->has('foo.bar'));
        $this->loader->addMessages('en', 'foo', ['bar' => 'baz']);
        $this->assertTrue($this->translator()->has('foo.bar'));
    }

    public function testHasUsesFallbackUnlessDisabledAndHasForLocaleDoesNot() {
        $this->loader->addMessages('en', 'foo', ['bar' => 'baz']);
        $t = $this->translator('id');
        $t->setFallback('en');
        $this->assertTrue($t->has('foo.bar'));
        $this->assertFalse($t->has('foo.bar', 'id', false), 'fallback=false: hanya locale itu');
        $this->assertFalse($t->hasForLocale('foo.bar'), 'hasForLocale tidak memakai fallback');
        $this->assertTrue($t->hasForLocale('foo.bar', 'en'));
    }

    public function testGetMethodProperlyLoadsAndRetrievesArrayItem() {
        $this->loader->addMessages('en', 'foo', ['bar' => ['a' => 'x', 'b' => 'y']]);
        $this->assertSame(['a' => 'x', 'b' => 'y'], $this->translator()->get('foo.bar'), 'item array dikembalikan utuh tanpa penggantian');
        $this->assertSame('x', $this->translator()->get('foo.bar.a'), 'notasi titik ke dalam array');
    }

    public function testGetMethodForNonExistingReturnsSameKey() {
        $this->loader->addMessages('en', 'foo', ['bar' => 'baz']);
        $this->assertSame('foo.missing', $this->translator()->get('foo.missing'));
        $this->assertSame('nope.missing :foo', $this->translator()->get('nope.missing :foo'), 'placeholder di kunci tetap');
        $this->assertSame('nope.missing bar', $this->translator()->get('nope.missing :foo', ['foo' => 'bar']), 'penggantian tetap diterapkan pada kunci');
    }

    public function testGetMethodProperlyLoadsAndRetrievesItemWithCapitalization() {
        $this->loader->addMessages('en', 'foo', ['bar' => 'breeze :foo :Foo :FOO']);
        $this->assertSame('breeze bar Bar BAR', $this->translator()->get('foo.bar', ['foo' => 'bar']));
        $this->assertSame('breeze 1 1 1', $this->translator()->get('foo.bar', ['foo' => 1]), 'nilai numerik aman');
    }

    public function testGetMethodProperlyLoadsAndRetrievesItemWithLongestReplacementsFirst() {
        $this->loader->addMessages('en', 'foo', ['bar' => 'breeze :foo :foobar']);
        $this->assertSame('breeze bar taylor', $this->translator()->get('foo.bar', ['foo' => 'bar', 'foobar' => 'taylor']), ':foobar tidak dirusak oleh :foo');
        $this->assertSame('breeze bar taylor', $this->translator()->get('foo.bar', ['foobar' => 'taylor', 'foo' => 'bar']));
    }

    public function testReplacementKeysMayCarryTheColon() {
        $this->loader->addMessages('en', 'foo', ['bar' => 'hello :name']);
        $this->assertSame('hello world', $this->translator()->get('foo.bar', [':name' => 'world']));
    }

    public function testGetMethodProperlyLoadsAndRetrievesItemForFallback() {
        $this->loader->addMessages('en', 'foo', ['bar' => 'breeze :foo']);
        $t = $this->translator('id');
        $t->setFallback('en');
        $this->assertSame('breeze bar', $t->get('foo.bar', ['foo' => 'bar']));
        $this->assertSame('foo.bar', $t->get('foo.bar', [], 'id', false), 'fallback dimatikan → kunci');
        $this->assertSame('en', $t->getFallback());
    }

    public function testGetMethodProperlyLoadsAndRetrievesItemForNamespace() {
        $this->loader->addMessages('en', 'bar', ['foo' => 'from-namespace'], 'ns');
        $this->loader->addMessages('en', 'bar', ['foo' => 'global']);
        $t = $this->translator();
        $this->assertSame('from-namespace', $t->get('ns::bar.foo'));
        $this->assertSame('global', $t->get('bar.foo'));
        $this->assertSame([null, 'bar', 'foo'], (new CBase_NamespacedItemResolver())->parseKey('bar.foo'));
        $this->assertSame(['*', 'bar', 'foo'], $t->parseKey('bar.foo'), 'tanpa namespace → *');
        $this->assertSame(['ns', 'bar', 'foo'], $t->parseKey('ns::bar.foo'));
    }

    public function testAddLinesInjectsTranslations() {
        $t = $this->translator();
        $t->addLines(['foo.bar' => 'added', 'foo.nested.deep' => 'deep'], 'en');
        $this->assertSame('added', $t->get('foo.bar'));
        $this->assertSame('deep', $t->get('foo.nested.deep'));
        $t->addLines(['foo.bar' => 'ns-added'], 'en', 'ns');
        $this->assertSame('ns-added', $t->get('ns::foo.bar'));
    }

    public function testChoiceMethodProperlyLoadsAndRetrievesItemForAnInt() {
        $this->loader->addMessages('en', 'foo', ['bar' => 'one apple|many apples']);
        $t = $this->translator();
        $this->assertSame('one apple', $t->choice('foo.bar', 1));
        $this->assertSame('many apples', $t->choice('foo.bar', 10));
        $this->assertSame('many apples', $t->choice('foo.bar', 0));
        $this->assertSame('many apples', $t->transChoice('foo.bar', 2));
    }

    public function testChoiceMethodProperlyLoadsAndRetrievesItemForAFloat() {
        $this->loader->addMessages('en', 'foo', ['bar' => '{0} none|{1.2} one point two|[2,*] many']);
        $t = $this->translator();
        $this->assertSame('one point two', $t->choice('foo.bar', 1.2));
        $this->assertSame('many', $t->choice('foo.bar', 3.5));
    }

    public function testChoiceMethodProperlyCountsCollectionsAndArrays() {
        $this->loader->addMessages('en', 'foo', ['bar' => '{0} none|{1} one|[2,*] :count items']);
        $t = $this->translator();
        $this->assertSame('none', $t->choice('foo.bar', []));
        $this->assertSame('one', $t->choice('foo.bar', ['a']));
        $this->assertSame('3 items', $t->choice('foo.bar', new CCollection([1, 2, 3])));
        $this->assertSame('3 items', $t->choice('foo.bar', new ArrayObject([1, 2, 3])), 'Countable apa pun');
    }

    public function testChoiceMethodProperlyUsesCustomCountReplacement() {
        $this->loader->addMessages('en', 'foo', ['bar' => '{1} one :count|[2,*] :count of :total']);
        $this->assertSame('3 of 10', $this->translator()->choice('foo.bar', 3, ['total' => 10]));
        $this->assertSame('5 of 10', $this->translator()->choice('foo.bar', 3, ['count' => 5, 'total' => 10]), 'count eksplisit menang atas angka - divergensi: hulu juga');
    }

    public function testChoiceUsesTheLocaleForPluralRules() {
        $this->loader->addMessages('id', 'foo', ['bar' => 'satu|banyak']);
        $t = $this->translator('id');
        $this->assertSame('satu', $t->choice('foo.bar', 1));
        $this->assertSame('satu', $t->choice('foo.bar', 5), 'id tidak punya bentuk jamak → indeks 0');
        $this->assertSame('satu', $t->choice('foo.bar', 5, [], 'id'));
        $this->assertSame(0, $t->getSelector()->getPluralIndex('id', 5));
        $this->assertSame(1, $t->getSelector()->getPluralIndex('en', 5));
        $this->assertSame(0, $t->getSelector()->getPluralIndex('en', 1));
        $this->assertSame(0, $t->getSelector()->getPluralIndex('fr', 0), 'fr: 0 dan 1 tunggal');
        $this->assertSame(1, $t->getSelector()->getPluralIndex('fr', 2));
    }

    /**
     * @return array
     */
    public function chooseData() {
        return [
            ['first', 'first', 1],
            ['first', 'first', 10],
            ['first', 'first|second', 1],
            ['second', 'first|second', 10],
            ['second', 'first|second', 0],
            ['first', '{0}  first|{1}second', 0],
            ['first', '{1}first|{2}second', 1],
            ['second', '{1}first|{2}second', 2],
            ['first', '{2}first|{1}second', 2],
            ['second', '{9}first|{10}second', 0],
            ['first', '{9}first|{10}second', 1],
            ['', '{0}|{1}second', 0],
            ['', '{0}first|{1}', 1],
            ['second', '{1.3}first|{2.3}second', .3],
            ['first', '{1.3}first|{2.3}second', 1.3],
            ['second', '{1.3}first|{2.3}second', 2.3],
            ["first\n            line", "{1}first\n            line|{2}second", 1],
            ['first', '{0}  first|[1,9]second', 0],
            ['second', '{0}first|[1,9]second', 1],
            ['second', '{0}first|[1,9]second', 10],
            ['first', '{0}first|[2,9]second', 1],
            ['second', '[4,*]first|[1,3]second', 1],
            ['first', '[4,*]first|[1,3]second', 100],
            ['second', '[1,5]first|[6,10]second', 7],
            ['first', '[*,4]first|[5,*]second', 1],
            ['second', '[5,*]first|[*,4]second', 1],
            ['second', '[5,*]first|[*,4]second', 0],
            ['first', '{0}first|[1,3]second|[4,*]third', 0],
            ['second', '{0}first|[1,3]second|[4,*]third', 1],
            ['third', '{0}first|[1,3]second|[4,*]third', 9],
            ['first', '[*,-1]first|{0}second|[1,*]third', -4],
            ['first', '[*,-1] first|{0} second|[1,*] third', -4],
            ['second', '[*,-1]first|{0}second|[1,*]third', 0],
            ['third', '[*,-1]first|{0}second|[1,*]third', 9],
            ['first', '[-5,-1]first|{0}second|[1,*]third', -4],
            ['first', 'first|second|third', 1],
            ['second', 'first|second|third', 9],
            ['second', 'first|second|third', 0],
            ['first', '{0}  first | { 1 } second', 0],
            ['first', '[4,*]first | [1,3]second', 100],
            ['[first](//example.com)', '[first](//example.com)|[second](//test.com)', 1],
            ['[second](//test.com)', '[first](//example.com)|[second](//test.com)', 2],
            ['[first](//example.com)', '{0}[first](//example.com)|{1}[second](//test.com)', 0],
            ['[second](//test.com)', '{0}[first](//example.com)|{1}[second](//test.com)', 1],
            ['[first](//example.com)', '{0}[first](//example.com)|[2,*][second](//test.com)', 0],
        ];
    }

    /**
     * @dataProvider chooseData
     *
     * @param string    $expected
     * @param string    $line
     * @param int|float $number
     */
    public function testMessageSelectorChoose($expected, $line, $number) {
        $this->assertSame($expected, (new CTranslation_MessageSelector())->choose($line, $number, 'en'));
    }

    public function testGetJsonLinesAndFallbackToRegularKeys() {
        $this->loader->addMessages('en', '*', ['I love you' => 'Aku sayang kamu', 'Hello :name' => 'Halo :name'], '*');
        $this->loader->addMessages('en', 'foo', ['bar' => 'regular']);
        $t = $this->translator();
        $this->assertSame('Aku sayang kamu', $t->getFromJson('I love you'));
        $this->assertSame('Halo Hery', $t->getFromJson('Hello :name', ['name' => 'Hery']));
        $this->assertSame('regular', $t->getFromJson('foo.bar'), 'kunci JSON tak ada → baris grup biasa');
        $this->assertSame('foo.missing', $t->getFromJson('foo.missing'));
        $this->assertSame('Missing Hery', $t->getFromJson('Missing :name', ['name' => 'Hery']), 'kunci tak ada tetap diganti placeholder-nya');
    }

    public function testGetJsonReplacesForAssociativeInputAndPreservesOrder() {
        $this->loader->addMessages('en', '*', ['Hello :first :last' => ':last, :first'], '*');
        $t = $this->translator();
        $this->assertSame('Otwell, Taylor', $t->getFromJson('Hello :first :last', ['first' => 'Taylor', 'last' => 'Otwell']));
        $this->assertSame('Otwell, Taylor', $t->getFromJson('Hello :first :last', ['last' => 'Otwell', 'first' => 'Taylor']));
    }

    public function testGetJsonHasAtomicReplacements() {
        $this->loader->addMessages('en', '*', ['Hello :foo!' => 'Hello :foo!'], '*');
        $this->assertSame('Hello baz:bar!', $this->translator()->getFromJson('Hello :foo!', ['foo' => 'baz:bar', 'bar' => 'abcdef']), 'nilai pengganti tidak diproses lagi');
    }

    public function testTransIsAliasOfGet() {
        $this->loader->addMessages('en', 'foo', ['bar' => 'baz :x']);
        $t = $this->translator();
        $this->assertSame('baz 1', $t->trans('foo.bar', ['x' => 1]));
        $this->assertSame($t->get('foo.bar', ['x' => 1]), $t->trans('foo.bar', ['x' => 1]));
    }

    public function testLocaleAccessorsAndLoader() {
        $t = $this->translator('en');
        $this->assertSame('en', $t->getLocale());
        $this->assertSame('en', $t->locale());
        $t->setLocale('id');
        $this->assertSame('id', $t->getLocale());
        $t->setFallback('en');
        $this->assertSame('en', $t->getFallback());
        $this->assertSame($this->loader, $t->getLoader());
        $selector = new CTranslation_MessageSelector();
        $t->setSelector($selector);
        $this->assertSame($selector, $t->getSelector());
    }

    public function testExplicitLocaleOverridesCurrentLocale() {
        $this->loader->addMessages('en', 'foo', ['bar' => 'english']);
        $this->loader->addMessages('id', 'foo', ['bar' => 'indonesia']);
        $t = $this->translator('en');
        $this->assertSame('english', $t->get('foo.bar'));
        $this->assertSame('indonesia', $t->get('foo.bar', [], 'id'));
        $this->assertSame('indonesia', $t->choice('foo.bar', 1, [], 'id'));
    }

    public function testLoadedGroupsAreNotReloadedButLocalesAreSeparate() {
        $this->loader->addMessages('en', 'foo', ['bar' => 'v1']);
        $t = $this->translator();
        $this->assertSame('v1', $t->get('foo.bar'));
        $this->loader->addMessages('en', 'foo', ['bar' => 'v2']);
        $this->assertSame('v1', $t->get('foo.bar'), 'grup yang sudah dimuat di-memoize');
        $this->loader->addMessages('id', 'foo', ['bar' => 'id-v2']);
        $this->assertSame('id-v2', $t->get('foo.bar', [], 'id'), 'locale lain dimuat terpisah');
    }

    public function testArrayLoaderNamespacesAndJsonPaths() {
        $loader = new CTranslation_Loader_ArrayLoader();
        $loader->addNamespace('ns', '/path');
        $this->assertSame([], $loader->namespaces(), 'ArrayLoader tidak melacak namespace (hulu juga)');
        $this->assertSame([], $loader->load('en', 'missing'));
        $loader->addMessages('en', 'grp', ['a' => 1]);
        $loader->addMessages('en', 'grp', ['b' => 2]);
        $this->assertSame(['b' => 2], $loader->load('en', 'grp'), 'addMessages menimpa grup, bukan menggabungkan');
    }
}
