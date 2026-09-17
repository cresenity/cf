<?php

use PHPUnit\Framework\TestCase;

/**
 * Helper string `cstr` - padanan suite hulu untuk Str, dibatasi pada method yang memang ada
 * di CF (76 dari 111). Perbedaan perilaku yang disengaja dicatat di test-nya.
 */
class SupportCstrTest extends TestCase {
    public function testWords() {
        $this->assertSame('Taylor...', cstr::words('Taylor Otwell', 1));
        $this->assertSame('Taylor___', cstr::words('Taylor Otwell', 1, '___'));
        $this->assertSame('Taylor Otwell', cstr::words('Taylor Otwell', 3));
        $this->assertSame('Taylor Otwell', cstr::words('Taylor Otwell', -1, '...'));
        $this->assertSame('', cstr::words('', 3, '...'));
    }

    public function testTitle() {
        $this->assertSame('Jefferson Costella', cstr::title('jefferson costella'));
        $this->assertSame('Jefferson Costella', cstr::title('jefFErson coSTella'));
        $this->assertSame('', cstr::title(''));
        $this->assertSame('123 Cresenity', cstr::title('123 cresenity'));
        $this->assertSame('❤Cresenity', cstr::title('❤cresenity'));
        $this->assertSame('Cresenity ❤', cstr::title('cresenity ❤'));
        $this->assertSame('Cresenity123', cstr::title('cresenity123'));
    }

    public function testHeadline() {
        $this->assertSame('Jefferson Costella', cstr::headline('jefferson costella'));
        $this->assertSame('Jefferson Costella', cstr::headline('jefFErson coSTella'));
        $this->assertSame('Jefferson Costella Uses Cresenity', cstr::headline('jefferson_costella uses-_Cresenity'));
        $this->assertSame('Cresenity P H P Framework', cstr::headline('cresenity_p_h_p_framework'));
        $this->assertSame('Cresenity Php Framework', cstr::headline('cresenity_php_framework'));
        $this->assertSame('Cresenity Ph P Framework', cstr::headline('cresenity-phP-framework'));
        $this->assertSame('Cresenity Php Framework', cstr::headline('cresenity  -_-  php   -_-   framework   '));
        $this->assertSame('Foo Bar', cstr::headline('fooBar'));
        $this->assertSame('Foo Bar', cstr::headline('foo_bar'));
        $this->assertSame('Foo Bar Baz', cstr::headline('foo-barBaz'));
        $this->assertSame('Foo Bar Baz', cstr::headline('foo-bar_baz'));
        $this->assertSame('Öffentliche Überraschungen', cstr::headline('öffentliche-überraschungen'));
        $this->assertSame('Sind Öde Und So', cstr::headline('sindÖdeUndSo'));
        $this->assertSame('Orwell 1984', cstr::headline('orwell 1984'));
        $this->assertSame('Orwell 1984', cstr::headline('-orwell-1984 -'));
        $this->assertSame('Cresenity Rocks!', cstr::headline('cresenity rocks!'));
    }

    public function testApa() {
        $this->assertSame('Tom and Jerry', cstr::apa('tom and jerry'));
        $this->assertSame('Tom and Jerry', cstr::apa('TOM AND JERRY'));
        $this->assertSame('Back to the Future', cstr::apa('back to the future'));
        $this->assertSame('This, Then That', cstr::apa('this, then that'));
        $this->assertSame('Bond. James Bond.', cstr::apa('bond. james bond.'));
        $this->assertSame('Self-Report', cstr::apa('self-report'));
        $this->assertSame('As the World Turns, So Are the Days of Our Lives', cstr::apa('as the world turns, so are the days of our lives'));
        $this->assertSame('To Kill a Mockingbird', cstr::apa('to kill a mockingbird'));
        $this->assertSame('', cstr::apa(''));
        $this->assertSame('   ', cstr::apa('   '));
    }

    public function testAscii() {
        $this->assertSame('@', cstr::ascii('@'));
        $this->assertSame('u', cstr::ascii('ü'));
        $this->assertSame('', cstr::ascii(''));
        $this->assertSame('a!2e', cstr::ascii('a!2ë'));
    }

    public function testStartsWith() {
        $this->assertTrue(cstr::startsWith('jason', 'jas'));
        $this->assertTrue(cstr::startsWith('jason', 'jason'));
        $this->assertTrue(cstr::startsWith('jason', ['jas']));
        $this->assertTrue(cstr::startsWith('jason', ['day', 'jas']));
        $this->assertFalse(cstr::startsWith('jason', 'day'));
        $this->assertFalse(cstr::startsWith('jason', ['day']));
        $this->assertFalse(cstr::startsWith('jason', null));
        $this->assertFalse(cstr::startsWith('jason', [null]));
        $this->assertFalse(cstr::startsWith('0123', [null]));
        $this->assertTrue(cstr::startsWith('0123', 0));
        $this->assertFalse(cstr::startsWith('jason', 'J'));
        $this->assertFalse(cstr::startsWith('jason', ''));
        $this->assertFalse(cstr::startsWith('', ''));
        $this->assertFalse(cstr::startsWith('7', ' 7'));
        $this->assertTrue(cstr::startsWith('7a', '7'));
        $this->assertTrue(cstr::startsWith('7a', 7));
        $this->assertTrue(cstr::startsWith('7.12a', 7.12));
        $this->assertFalse(cstr::startsWith('7.12a', 7.13));
        $this->assertTrue(cstr::startsWith(7.123, '7'));
        $this->assertTrue(cstr::startsWith(7.123, '7.12'));
        $this->assertFalse(cstr::startsWith(7.123, '7.13'));
        $this->assertTrue(cstr::startsWith('Jönköping', 'Jö'));
        $this->assertTrue(cstr::startsWith('Malmö', 'Malmö'));
        $this->assertFalse(cstr::startsWith('Jönköping', 'Jonko'));
        $this->assertFalse(cstr::startsWith('Malmö', 'Malmo'));
        $this->assertTrue(cstr::startsWith('你好', '你'));
        $this->assertFalse(cstr::startsWith('你好', '好'));
        $this->assertFalse(cstr::startsWith('你好', 'a'));
    }

    public function testEndsWith() {
        $this->assertTrue(cstr::endsWith('jason', 'on'));
        $this->assertTrue(cstr::endsWith('jason', 'jason'));
        $this->assertTrue(cstr::endsWith('jason', ['on']));
        $this->assertTrue(cstr::endsWith('jason', ['no', 'on']));
        $this->assertFalse(cstr::endsWith('jason', 'no'));
        $this->assertFalse(cstr::endsWith('jason', ['no']));
        $this->assertFalse(cstr::endsWith('jason', ''));
        $this->assertFalse(cstr::endsWith('', ''));
        $this->assertFalse(cstr::endsWith('jason', [null]));
        $this->assertFalse(cstr::endsWith('jason', null));
        $this->assertFalse(cstr::endsWith('jason', 'N'));
        $this->assertFalse(cstr::endsWith('7', ' 7'));
        $this->assertTrue(cstr::endsWith('a7', '7'));
        $this->assertTrue(cstr::endsWith('a7', 7));
        $this->assertTrue(cstr::endsWith('a7.12', 7.12));
        $this->assertFalse(cstr::endsWith('a7.12', 7.13));
        $this->assertTrue(cstr::endsWith(0.27, '7'));
        $this->assertTrue(cstr::endsWith(0.27, '0.27'));
        $this->assertFalse(cstr::endsWith(0.27, '8'));
        $this->assertTrue(cstr::endsWith('Jönköping', 'öping'));
        $this->assertTrue(cstr::endsWith('Malmö', 'mö'));
        $this->assertFalse(cstr::endsWith('Jönköping', 'oping'));
        $this->assertFalse(cstr::endsWith('Malmö', 'mo'));
        $this->assertTrue(cstr::endsWith('你好', '好'));
        $this->assertFalse(cstr::endsWith('你好', '你'));
        $this->assertFalse(cstr::endsWith('你好', 'a'));
    }

    public function testExcerpt() {
        $this->assertSame('...is a beautiful morn...', cstr::excerpt('This is a beautiful morning', 'beautiful', ['radius' => 5]));
        $this->assertSame('This is a...', cstr::excerpt('This is a beautiful morning', 'this', ['radius' => 5]));
        $this->assertSame('...iful morning', cstr::excerpt('This is a beautiful morning', 'morning', ['radius' => 5]));
        $this->assertNull(cstr::excerpt('This is a beautiful morning', 'day'));
        $this->assertSame('...is a beautiful! mor...', cstr::excerpt('This is a beautiful! morning', 'Beautiful', ['radius' => 5]));
        $this->assertSame('', cstr::excerpt('', '', ['radius' => 0]));
        $this->assertSame('a', cstr::excerpt('a', 'a', ['radius' => 0]));
        $this->assertSame('...b...', cstr::excerpt('abc', 'B', ['radius' => 0]));
        $this->assertSame('abc', cstr::excerpt('abc', 'b', ['radius' => 1]));
        $this->assertSame('abc...', cstr::excerpt('abcd', 'b', ['radius' => 1]));
        $this->assertSame('...abc', cstr::excerpt('zabc', 'b', ['radius' => 1]));
        $this->assertSame('...abc...', cstr::excerpt('zabcd', 'b', ['radius' => 1]));
        $this->assertSame('zabcd', cstr::excerpt('zabcd', 'b', ['radius' => 2]));
        $this->assertSame('[...]is a beautiful morn[...]', cstr::excerpt('This is a beautiful morning', 'beautiful', ['omission' => '[...]', 'radius' => 5]));
        $this->assertSame('...y...', cstr::excerpt('taylor', 'y', ['radius' => 0]));
        $this->assertSame('...ayl...', cstr::excerpt('taylor', 'Y', ['radius' => 1]));
        $this->assertSame('', cstr::excerpt(null));
        $this->assertSame('', cstr::excerpt(''));
        $this->assertSame('T...', cstr::excerpt('The article description', null, ['radius' => 1]));
        $this->assertSame('The arti...', cstr::excerpt('The article description', '', ['radius' => 8]));
        $this->assertSame('...cle description', cstr::excerpt('The article description', 'description', ['radius' => 4]));
        $this->assertSame('What i?', cstr::excerpt('What is the article?', 'What', ['radius' => 2, 'omission' => '?']));
        $this->assertSame('...ö - 二 sān 大åè...', cstr::excerpt('åèö - 二 sān 大åèö', '二 sān', ['radius' => 4]));
        $this->assertSame('João...', cstr::excerpt('João Antônio ', 'jo', ['radius' => 2]));
        $this->assertNull(cstr::excerpt('', '/'));
    }

    public function testBefore() {
        $this->assertSame('han', cstr::before('hannah', 'nah'));
        $this->assertSame('ha', cstr::before('hannah', 'n'));
        $this->assertSame('ééé ', cstr::before('ééé hannah', 'han'));
        $this->assertSame('hannah', cstr::before('hannah', 'xxxx'));
        $this->assertSame('hannah', cstr::before('hannah', ''));
        $this->assertSame('han', cstr::before('han0nah', '0'));
        $this->assertSame('han', cstr::before('han0nah', 0));
        $this->assertSame('han', cstr::before('han2nah', 2));
        $this->assertSame('', cstr::before('', ''));
        $this->assertSame('', cstr::before('', 'a'));
        $this->assertSame('', cstr::before('a', 'a'));
        $this->assertSame('foo', cstr::before('foo@bar.com', '@'));
        $this->assertSame('foo', cstr::before('foo@@bar.com', '@'));
        $this->assertSame('', cstr::before('@foo@bar.com', '@'));
    }

    public function testBeforeLast() {
        $this->assertSame('yve', cstr::beforeLast('yvette', 'tte'));
        $this->assertSame('yvet', cstr::beforeLast('yvette', 't'));
        $this->assertSame('ééé ', cstr::beforeLast('ééé yvette', 'yve'));
        $this->assertSame('', cstr::beforeLast('yvette', 'yve'));
        $this->assertSame('yvette', cstr::beforeLast('yvette', 'xxxx'));
        $this->assertSame('yvette', cstr::beforeLast('yvette', ''));
        $this->assertSame('yv0et', cstr::beforeLast('yv0et0te', '0'));
        $this->assertSame('yv0et', cstr::beforeLast('yv0et0te', 0));
        $this->assertSame('yv2et', cstr::beforeLast('yv2et2te', 2));
        $this->assertSame('', cstr::beforeLast('', 'test'));
        $this->assertSame('', cstr::beforeLast('yvette', 'yvette'));
        $this->assertSame('cresenity', cstr::beforeLast('cresenity framework', ' '));
        $this->assertSame('yvette', cstr::beforeLast("yvette\tyv0et0te", "\t"));
    }

    public function testBetween() {
        $this->assertSame('abc', cstr::between('abc', '', 'c'));
        $this->assertSame('abc', cstr::between('abc', 'a', ''));
        $this->assertSame('abc', cstr::between('abc', '', ''));
        $this->assertSame('b', cstr::between('abc', 'a', 'c'));
        $this->assertSame('b', cstr::between('dddabc', 'a', 'c'));
        $this->assertSame('b', cstr::between('abcddd', 'a', 'c'));
        $this->assertSame('b', cstr::between('dddabcddd', 'a', 'c'));
        $this->assertSame('nn', cstr::between('hannah', 'ha', 'ah'));
        $this->assertSame('a]ab[b', cstr::between('[a]ab[b]', '[', ']'));
        $this->assertSame('foo', cstr::between('foofoobar', 'foo', 'bar'));
        $this->assertSame('bar', cstr::between('foobarbar', 'foo', 'bar'));
        $this->assertSame('234', cstr::between('12345', 1, 5));
        $this->assertSame('45', cstr::between('123456789', '123', '6789'));
        $this->assertSame('nothing', cstr::between('nothing', 'foo', 'bar'));
    }

    public function testBetweenFirst() {
        $this->assertSame('abc', cstr::betweenFirst('abc', '', 'c'));
        $this->assertSame('abc', cstr::betweenFirst('abc', 'a', ''));
        $this->assertSame('abc', cstr::betweenFirst('abc', '', ''));
        $this->assertSame('b', cstr::betweenFirst('abc', 'a', 'c'));
        $this->assertSame('b', cstr::betweenFirst('dddabc', 'a', 'c'));
        $this->assertSame('b', cstr::betweenFirst('abcddd', 'a', 'c'));
        $this->assertSame('nn', cstr::betweenFirst('hannah', 'ha', 'ah'));
        $this->assertSame('a', cstr::betweenFirst('[a]ab[b]', '[', ']'));
        $this->assertSame('foo', cstr::betweenFirst('foofoobar', 'foo', 'bar'));
        $this->assertSame('', cstr::betweenFirst('foobarbar', 'foo', 'bar'));
    }

    public function testAfter() {
        $this->assertSame('nah', cstr::after('hannah', 'han'));
        $this->assertSame('nah', cstr::after('hannah', 'n'));
        $this->assertSame('nah', cstr::after('ééé hannah', 'han'));
        $this->assertSame('hannah', cstr::after('hannah', 'xxxx'));
        $this->assertSame('hannah', cstr::after('hannah', ''));
        $this->assertSame('nah', cstr::after('han0nah', '0'));
        $this->assertSame('nah', cstr::after('han0nah', 0));
        $this->assertSame('nah', cstr::after('han2nah', 2));
    }

    public function testAfterLast() {
        $this->assertSame('tte', cstr::afterLast('yvette', 'yve'));
        $this->assertSame('e', cstr::afterLast('yvette', 't'));
        $this->assertSame('e', cstr::afterLast('ééé yvette', 't'));
        $this->assertSame('', cstr::afterLast('yvette', 'tte'));
        $this->assertSame('yvette', cstr::afterLast('yvette', 'xxxx'));
        $this->assertSame('yvette', cstr::afterLast('yvette', ''));
        $this->assertSame('te', cstr::afterLast('yv0et0te', '0'));
        $this->assertSame('te', cstr::afterLast('yv0et0te', 0));
        $this->assertSame('te', cstr::afterLast('yv2et2te', 2));
        $this->assertSame('foo', cstr::afterLast('----foo', '---'));
        $this->assertSame('', cstr::afterLast('café au café', 'café'));
        $this->assertSame('', cstr::afterLast('こんにちは世界こんにちは', 'こんにちは'));
    }

    /**
     * Beda dari hulu: belum ada parameter $ignoreCase.
     */
    public function testContains() {
        $this->assertTrue(cstr::contains('Taylor', 'ylo'));
        $this->assertTrue(cstr::contains('Taylor', ['ylo']));
        $this->assertTrue(cstr::contains('Taylor', ['xxx', 'ylo']));
        $this->assertFalse(cstr::contains('Taylor', 'xxx'));
        $this->assertFalse(cstr::contains('Taylor', ['xxx']));
        $this->assertFalse(cstr::contains('Taylor', ''));
        $this->assertFalse(cstr::contains('', ''));
        $this->assertFalse(cstr::contains('Taylor', null));
        //needle null belum ditolak (mb_strpos menganggapnya '') - lihat docs/NOTES.md
    }

    public function testContainsAll() {
        $this->assertTrue(cstr::containsAll('Taylor Otwell', ['Taylor', 'Otwell']));
        $this->assertTrue(cstr::containsAll('Taylor Otwell', ['Taylor']));
        $this->assertFalse(cstr::containsAll('Taylor Otwell', ['taylor', 'xxx']));
        $this->assertFalse(cstr::containsAll('Taylor Otwell', ['Taylor', 'xxx']));
    }

    public function testParseCallback() {
        $this->assertEquals(['Class', 'method'], cstr::parseCallback('Class@method'));
        $this->assertEquals(['Class', 'method'], cstr::parseCallback('Class@method', 'foo'));
        $this->assertEquals(['Class', 'foo'], cstr::parseCallback('Class', 'foo'));
        $this->assertEquals(['Class', null], cstr::parseCallback('Class'));
    }

    public function testSlug() {
        $this->assertSame('hello-world', cstr::slug('hello world'));
        $this->assertSame('hello-world', cstr::slug('hello-world'));
        $this->assertSame('hello-world', cstr::slug('hello_world'));
        $this->assertSame('hello_world', cstr::slug('hello_world', '_'));
        $this->assertSame('user-at-host', cstr::slug('user@host'));
        $this->assertSame('sometext', cstr::slug('some text', ''));
        $this->assertSame('', cstr::slug('', ''));
        $this->assertSame('', cstr::slug(''));
    }

    public function testStart() {
        $this->assertSame('/test/string', cstr::start('test/string', '/'));
        $this->assertSame('/test/string', cstr::start('/test/string', '/'));
        $this->assertSame('/test/string', cstr::start('//test/string', '/'));
    }

    public function testFinish() {
        $this->assertSame('abbc', cstr::finish('ab', 'bc'));
        $this->assertSame('abbc', cstr::finish('abbcbc', 'bc'));
        $this->assertSame('abcbbc', cstr::finish('abcbbcbc', 'bc'));
    }

    public function testWrap() {
        $this->assertEquals('"value"', cstr::wrap('value', '"'));
        $this->assertEquals('foo-bar-baz', cstr::wrap('-bar-', 'foo', 'baz'));
    }

    public function testIs() {
        $this->assertTrue(cstr::is('/', '/'));
        $this->assertFalse(cstr::is('/', ' /'));
        $this->assertFalse(cstr::is('/', '/a'));
        $this->assertTrue(cstr::is('foo/*', 'foo/bar/baz'));

        $this->assertTrue(cstr::is('*@*', 'App\Class@method'));
        $this->assertTrue(cstr::is('*@*', 'app\Class@'));
        $this->assertTrue(cstr::is('*@*', '@method'));

        $this->assertTrue(cstr::is('*/foo', 'blah/baz/foo'));

        $valueObject = new SupportCstrTestStringable('foo/bar/baz');
        $patternObject = new SupportCstrTestStringable('foo/*');
        $this->assertTrue(cstr::is('foo/bar/baz', $valueObject));
        $this->assertTrue(cstr::is($patternObject, $valueObject));

        $this->assertFalse(cstr::is('', 0));
        $this->assertFalse(cstr::is([null], 0));
        $this->assertTrue(cstr::is([null], null));

        $this->assertTrue(cstr::is(['a*', 'b*'], 'a/'));
        $this->assertTrue(cstr::is(['a*', 'b*'], 'b/'));
        $this->assertFalse(cstr::is(['a*', 'b*'], 'f/'));

        $this->assertTrue(cstr::is('*', 'anything'));
        $this->assertFalse(cstr::is([], 'anything'));
    }

    public function testIsJson() {
        $this->assertTrue(cstr::isJson('1'));
        $this->assertTrue(cstr::isJson('[1,2,3]'));
        $this->assertTrue(cstr::isJson('[1,   2,   3]'));
        $this->assertTrue(cstr::isJson('{"first": "John", "last": "Doe"}'));
        $this->assertTrue(cstr::isJson('[{"first": "John", "last": "Doe"}, {"first": "Jane", "last": "Doe"}]'));

        $this->assertFalse(cstr::isJson('1,'));
        $this->assertFalse(cstr::isJson('[1,2,3'));
        $this->assertFalse(cstr::isJson('[1,   2   3]'));
        $this->assertFalse(cstr::isJson('{first: "John"}'));
        $this->assertFalse(cstr::isJson('[{first: "John"}, {first: "Jane"}]'));
        $this->assertFalse(cstr::isJson(''));
        $this->assertFalse(cstr::isJson(null));
        $this->assertFalse(cstr::isJson([]));
    }

    public function testIsUuid() {
        $this->assertTrue(cstr::isUuid('a0a2a2d2-0b87-4a18-83f2-2529882be2de'));
        $this->assertTrue(cstr::isUuid('145a1e72-d11d-11e8-a8d5-f2801f1b9fd1'));
        $this->assertTrue(cstr::isUuid('00000000-0000-0000-0000-000000000000'));
        $this->assertTrue(cstr::isUuid('ff6f8cb0-c57d-11e1-9b21-0800200c9a66'));

        $this->assertFalse(cstr::isUuid(null));
        $this->assertFalse(cstr::isUuid(0));
        $this->assertFalse(cstr::isUuid('uuid'));
        $this->assertFalse(cstr::isUuid('a0a2a2d2-0b87-4a18-83f2-2529882be2dex'));
        $this->assertFalse(cstr::isUuid('a0a2a2d2-0b87-4a18-83f2-2529882be2d'));
        $this->assertFalse(cstr::isUuid('af6f8cb0c57d11e19b210800200c9a66'));
        $this->assertFalse(cstr::isUuid('ff6f8cb0-c57da-51e1-9b21-0800200c9a66'));
        $this->assertFalse(cstr::isUuid(''));
        $this->assertFalse(cstr::isUuid([]));
    }

    public function testIsUlid() {
        $this->assertTrue(cstr::isUlid('01gd6r360bp37zj17nxb55yv40'));
        $this->assertTrue(cstr::isUlid('01GD6R360BP37ZJ17NXB55YV40'));

        $this->assertFalse(cstr::isUlid('01gd6r360bp37zj17nxb55yv4'));
        $this->assertFalse(cstr::isUlid('01gd6r360bp37zj17nxb55yv400'));
        $this->assertFalse(cstr::isUlid('01gd6r360bp37zj17nxb55yv4i'));
        $this->assertFalse(cstr::isUlid('ulid'));
        $this->assertFalse(cstr::isUlid(''));
        $this->assertFalse(cstr::isUlid(null));
    }

    public function testIsMatch() {
        $this->assertTrue(cstr::isMatch('/.*,.*!/', 'Hello, Cresenity!'));
        $this->assertTrue(cstr::isMatch('/^.*$(.*)/', 'Hello, Cresenity!'));
        $this->assertTrue(cstr::isMatch('/cresenity/i', 'Hello, Cresenity!'));
        $this->assertTrue(cstr::isMatch('/^(.*(.*(.*)))/', 'Hello, Cresenity!'));

        $this->assertFalse(cstr::isMatch('/H.o/', 'Hello, Cresenity!'));
        $this->assertFalse(cstr::isMatch('/^cresenity!/i', 'Hello, Cresenity!'));
        $this->assertFalse(cstr::isMatch('/cresenity!(.*)/', 'Hello, Cresenity!'));
        $this->assertFalse(cstr::isMatch('/^[a-zA-Z,!]+$/', 'Hello, Cresenity!'));

        $this->assertTrue(cstr::isMatch(['/.*,.*!/', '/H.o/'], 'Hello, Cresenity!'));
        $this->assertFalse(cstr::isMatch(['/^cresenity!/i', '/cresenity!(.*)/'], 'Hello, Cresenity!'));
    }

    public function testKebab() {
        $this->assertSame('cresenity-php-framework', cstr::kebab('CresenityPhpFramework'));
        $this->assertSame('cresenity-php-framework', cstr::kebab('Cresenity Php Framework'));
        $this->assertSame('cresenity❤-php-framework', cstr::kebab('Cresenity ❤ Php Framework'));
        $this->assertSame('', cstr::kebab(''));
    }

    public function testLowerAndUpper() {
        $this->assertSame('foo bar baz', cstr::lower('FOO BAR BAZ'));
        $this->assertSame('foo bar baz', cstr::lower('fOo Bar bAz'));
        $this->assertSame('FOO BAR BAZ', cstr::upper('foo bar baz'));
        $this->assertSame('FOO BAR BAZ', cstr::upper('foO bAr BaZ'));
    }

    public function testLimit() {
        $this->assertSame('Cresenity is...', cstr::limit('Cresenity is a free, open source PHP web application framework.', 12));
        $this->assertSame('这是一...', cstr::limit('这是一段中文', 6));
        $this->assertSame('Cresenity is a free, open source PHP web application framework.', cstr::limit('Cresenity is a free, open source PHP web application framework.', 100));
        $this->assertSame('Cresenity is a free, open source PHP web application framework.', cstr::limit('Cresenity is a free, open source PHP web application framework.', 100, '...'));
        $this->assertSame('Cresenity is', cstr::limit('Cresenity is a free, open source PHP web application framework.', 12, ''));
        $this->assertSame('Cresenity is...', cstr::limit('Cresenity is a free, open source PHP web application framework.', 12, '...'));
        $this->assertSame('', cstr::limit('', 12));
    }

    public function testLength() {
        $this->assertEquals(11, cstr::length('foo bar baz'));
        $this->assertEquals(11, cstr::length('foo bar baz', 'UTF-8'));
        $this->assertEquals(6, cstr::length('这是一段中文'));
    }

    public function testRandom() {
        $this->assertEquals(16, strlen(cstr::random()));
        $randomInteger = random_int(1, 100);
        $this->assertEquals($randomInteger, strlen(cstr::random($randomInteger)));
        $this->assertIsString(cstr::random());
    }

    public function testReplace() {
        $this->assertSame('foo bar cresenity', cstr::replace('baz', 'cresenity', 'foo bar baz'));
        $this->assertSame('foo bar baz 8.x', cstr::replace('?', '8.x', 'foo bar baz ?'));
        $this->assertSame('foo/bar/baz', cstr::replace(' ', '/', 'foo bar baz'));
        $this->assertSame('foo bar baz', cstr::replace(['?1', '?2', '?3'], ['foo', 'bar', 'baz'], '?1 ?2 ?3'));
        //beda dari hulu: argumen koleksi belum diterima, hanya string/array
    }

    public function testReplaceArray() {
        $this->assertSame('foo/bar/baz', cstr::replaceArray('?', ['foo', 'bar', 'baz'], '?/?/?'));
        $this->assertSame('foo/bar/baz/?', cstr::replaceArray('?', ['foo', 'bar', 'baz'], '?/?/?/?'));
        $this->assertSame('foo/bar', cstr::replaceArray('?', ['foo', 'bar', 'baz'], '?/?'));
        $this->assertSame('?/?/?', cstr::replaceArray('x', ['foo', 'bar', 'baz'], '?/?/?'));
        $this->assertSame('foo?/bar/baz', cstr::replaceArray('?', ['foo?', 'bar', 'baz'], '?/?/?'));
        $this->assertSame('foo/bar', cstr::replaceArray('?', [1 => 'foo', 2 => 'bar'], '?/?'));
        $this->assertSame('foo/bar', cstr::replaceArray('?', ['x' => 'foo', 'y' => 'bar'], '?/?'));
    }

    public function testReplaceFirst() {
        $this->assertSame('fooqux foobar', cstr::replaceFirst('bar', 'qux', 'foobar foobar'));
        $this->assertSame('foo/qux? foo/bar?', cstr::replaceFirst('bar?', 'qux?', 'foo/bar? foo/bar?'));
        $this->assertSame('foo foobar', cstr::replaceFirst('bar', '', 'foobar foobar'));
        $this->assertSame('foobar foobar', cstr::replaceFirst('xxx', 'yyy', 'foobar foobar'));
        $this->assertSame('foobar foobar', cstr::replaceFirst('', 'yyy', 'foobar foobar'));
        $this->assertSame('Jxxxnköping Malmö', cstr::replaceFirst('ö', 'xxx', 'Jönköping Malmö'));
        $this->assertSame('Jönköping Malmö', cstr::replaceFirst('', 'yyy', 'Jönköping Malmö'));
    }

    public function testReplaceStart() {
        $this->assertSame('foobar foobar', cstr::replaceStart('bar', 'qux', 'foobar foobar'));
        $this->assertSame('foo/bar? foo/bar?', cstr::replaceStart('bar?', 'qux?', 'foo/bar? foo/bar?'));
        $this->assertSame('quxbar foobar', cstr::replaceStart('foo', 'qux', 'foobar foobar'));
        $this->assertSame('qux? foo/bar?', cstr::replaceStart('foo/bar?', 'qux?', 'foo/bar? foo/bar?'));
        $this->assertSame('bar foobar', cstr::replaceStart('foo', '', 'foobar foobar'));
        $this->assertSame('1', cstr::replaceStart(0, '1', '0'));
        $this->assertSame('xxxnköping Malmö', cstr::replaceStart('Jö', 'xxx', 'Jönköping Malmö'));
        $this->assertSame('Jönköping Malmö', cstr::replaceStart('', 'yyy', 'Jönköping Malmö'));
    }

    public function testReplaceLast() {
        $this->assertSame('foobar fooqux', cstr::replaceLast('bar', 'qux', 'foobar foobar'));
        $this->assertSame('foo/bar? foo/qux?', cstr::replaceLast('bar?', 'qux?', 'foo/bar? foo/bar?'));
        $this->assertSame('foobar foo', cstr::replaceLast('bar', '', 'foobar foobar'));
        $this->assertSame('foobar foobar', cstr::replaceLast('xxx', 'yyy', 'foobar foobar'));
        $this->assertSame('foobar foobar', cstr::replaceLast('', 'yyy', 'foobar foobar'));
        $this->assertSame('Malmö Jönkxxxping', cstr::replaceLast('ö', 'xxx', 'Malmö Jönköping'));
        $this->assertSame('Malmö Jönköping', cstr::replaceLast('', 'yyy', 'Malmö Jönköping'));
    }

    public function testReplaceEnd() {
        $this->assertSame('foobar fooqux', cstr::replaceEnd('bar', 'qux', 'foobar foobar'));
        $this->assertSame('foo/bar? foo/qux?', cstr::replaceEnd('bar?', 'qux?', 'foo/bar? foo/bar?'));
        $this->assertSame('foobar foo', cstr::replaceEnd('bar', '', 'foobar foobar'));
        $this->assertSame('foobar foobar', cstr::replaceEnd('xxx', 'yyy', 'foobar foobar'));
        $this->assertSame('foobar foobar', cstr::replaceEnd('', 'yyy', 'foobar foobar'));
        $this->assertSame('fooxxx foobar', cstr::replaceEnd('xxx', 'yyy', 'fooxxx foobar'));
        $this->assertSame('Malmö Jönköping', cstr::replaceEnd('ö', 'xxx', 'Malmö Jönköping'));
        $this->assertSame('Malmö Jönkyyy', cstr::replaceEnd('öping', 'yyy', 'Malmö Jönköping'));
    }

    public function testRemove() {
        $this->assertSame('Fbar', cstr::remove('o', 'Foobar'));
        $this->assertSame('Foo', cstr::remove('bar', 'Foobar'));
        $this->assertSame('oobar', cstr::remove('F', 'Foobar'));
        $this->assertSame('Foobar', cstr::remove('f', 'Foobar'));
        $this->assertSame('oobar', cstr::remove('f', 'Foobar', false));
        $this->assertSame('Fbr', cstr::remove(['o', 'a'], 'Foobar'));
        $this->assertSame('Fooar', cstr::remove(['f', 'b'], 'Foobar'));
        $this->assertSame('ooar', cstr::remove(['f', 'b'], 'Foobar', false));
        $this->assertSame('Foobar', cstr::remove(['f', '|'], 'Foo|bar'));
    }

    public function testReverse() {
        $this->assertSame('FooBar', cstr::reverse('raBooF'));
        $this->assertSame('Teniszütő', cstr::reverse('őtüzsineT'));
        $this->assertSame('❤MultiByte☆', cstr::reverse('☆etyBitluM❤'));
    }

    public function testSnake() {
        $this->assertSame('cresenity_p_h_p_framework', cstr::snake('CresenityPHPFramework'));
        $this->assertSame('cresenity_php_framework', cstr::snake('CresenityPhpFramework'));
        $this->assertSame('cresenity php framework', cstr::snake('CresenityPhpFramework', ' '));
        $this->assertSame('cresenity_php_framework', cstr::snake('Cresenity Php Framework'));
        $this->assertSame('cresenity_php_framework', cstr::snake('Cresenity    Php      Framework   '));
        $this->assertSame('cresenity__php__framework', cstr::snake('CresenityPhpFramework', '__'));
        $this->assertSame('cresenity_php_framework_', cstr::snake('CresenityPhpFramework_', '_'));
        $this->assertSame('cresenity_php_framework', cstr::snake('cresenity php Framework'));
        $this->assertSame('cresenity_php_frame_work', cstr::snake('cresenity php FrameWork'));
        $this->assertSame('foo-bar', cstr::snake('foo-bar'));
        $this->assertSame('foo-_bar', cstr::snake('Foo-Bar'));
        $this->assertSame('foo__bar', cstr::snake('Foo_Bar'));
        $this->assertSame('żółtałódka', cstr::snake('ŻółtaŁódka'));
    }

    public function testSquish() {
        $this->assertSame('cresenity php framework', cstr::squish(' cresenity   php  framework '));
        $this->assertSame('cresenity php framework', cstr::squish("cresenity\t\tphp\n\nframework"));
        $this->assertSame('cresenity php framework', cstr::squish('
            cresenity
            php
            framework
        '));
        $this->assertSame('cresenity php framework', cstr::squish('   cresenity   php   framework   '));
        $this->assertSame('123', cstr::squish('   123    '));
        $this->assertSame('だ', cstr::squish('だ'));
        $this->assertSame('ム', cstr::squish('ム'));
        $this->assertSame('だ', cstr::squish('   だ    '));
        $this->assertSame('ム', cstr::squish('   ム    '));
        //beda dari hulu: pengisi Hangul (U+3164, U+1160) belum dianggap spasi
    }

    public function testStudly() {
        $this->assertSame('CresenityPHPFramework', cstr::studly('cresenity_p_h_p_framework'));
        $this->assertSame('CresenityPhpFramework', cstr::studly('cresenity_php_framework'));
        $this->assertSame('CresenityPhPFramework', cstr::studly('cresenity-phP-framework'));
        $this->assertSame('CresenityPhpFramework', cstr::studly('cresenity  -_-  php   -_-   framework   '));
        $this->assertSame('FooBar', cstr::studly('fooBar'));
        $this->assertSame('FooBar', cstr::studly('foo_bar'));
        $this->assertSame('FooBar', cstr::studly('foo_bar'));
        $this->assertSame('FooBarBaz', cstr::studly('foo-barBaz'));
        $this->assertSame('FooBarBaz', cstr::studly('foo-bar_baz'));
        $this->assertSame('ÖffentlicheÜberraschungen', cstr::studly('öffentliche-überraschungen'));
    }

    public function testMask() {
        $this->assertSame('tay*************', cstr::mask('taylor@email.com', '*', 3));
        $this->assertSame('******@email.com', cstr::mask('taylor@email.com', '*', 0, 6));
        $this->assertSame('tay*************', cstr::mask('taylor@email.com', '*', -13));
        $this->assertSame('tay***@email.com', cstr::mask('taylor@email.com', '*', -13, 3));
        $this->assertSame('****************', cstr::mask('taylor@email.com', '*', -17));
        $this->assertSame('*****r@email.com', cstr::mask('taylor@email.com', '*', -99, 5));
        $this->assertSame('taylor@email.com', cstr::mask('taylor@email.com', '*', 16));
        $this->assertSame('taylor@email.com', cstr::mask('taylor@email.com', '*', 16, 99));
        $this->assertSame('taylor@email.com', cstr::mask('taylor@email.com', '', 3));
        $this->assertSame('taysssssssssssss', cstr::mask('taylor@email.com', 'something', 3));
        $this->assertSame('这是一***', cstr::mask('这是一段中文', '*', 3));
        $this->assertSame('**一段中文', cstr::mask('这是一段中文', '*', 0, 2));
    }

    public function testMatch() {
        $this->assertSame('bar', cstr::match('/bar/', 'foo bar'));
        $this->assertSame('bar', cstr::match('/foo (.*)/', 'foo bar'));
        $this->assertEmpty(cstr::match('/nothing/', 'foo bar'));

        $this->assertEquals(['bar', 'bar'], cstr::matchAll('/bar/', 'bar foo bar')->all());
        $this->assertEquals(['un', 'ly'], cstr::matchAll('/f(\w*)/', 'bar fun bar fly')->all());
        $this->assertEmpty(cstr::matchAll('/nothing/', 'bar fun bar fly'));
    }

    public function testCamel() {
        $this->assertSame('cresenityPHPFramework', cstr::camel('Cresenity_p_h_p_framework'));
        $this->assertSame('cresenityPhpFramework', cstr::camel('Cresenity_php_framework'));
        $this->assertSame('cresenityPhPFramework', cstr::camel('Cresenity-phP-framework'));
        $this->assertSame('cresenityPhpFramework', cstr::camel('Cresenity  -_-  php   -_-   framework   '));
        $this->assertSame('fooBar', cstr::camel('FooBar'));
        $this->assertSame('fooBar', cstr::camel('foo_bar'));
        $this->assertSame('fooBar', cstr::camel('foo_bar'));
        $this->assertSame('fooBarBaz', cstr::camel('Foo-barBaz'));
        $this->assertSame('fooBarBaz', cstr::camel('foo-bar_baz'));
        $this->assertSame('', cstr::camel(''));
    }

    public function testCharAt() {
        $this->assertEquals('р', cstr::charAt('Привет, мир!', 1));
        $this->assertEquals('ち', cstr::charAt('「こんにちは世界」', 4));
        $this->assertEquals('w', cstr::charAt('Привет, world!', 8));
        $this->assertEquals('」', cstr::charAt('「こんにちは世界」', -1));
        $this->assertEquals(null, cstr::charAt('「こんにちは世界」', -200));
        $this->assertEquals(null, cstr::charAt('Привет, мир!', 100));
    }

    public function testSubstr() {
        $this->assertSame('Ё', cstr::substr('БГДЖИЛЁ', -1));
        $this->assertSame('ЛЁ', cstr::substr('БГДЖИЛЁ', -2));
        $this->assertSame('И', cstr::substr('БГДЖИЛЁ', -3, 1));
        $this->assertSame('ДЖИЛ', cstr::substr('БГДЖИЛЁ', 2, -1));
        $this->assertEmpty(cstr::substr('БГДЖИЛЁ', 4, -4));
        $this->assertSame('ИЛ', cstr::substr('БГДЖИЛЁ', -3, -1));
        $this->assertSame('ГДЖИЛЁ', cstr::substr('БГДЖИЛЁ', 1));
        $this->assertSame('ГДЖ', cstr::substr('БГДЖИЛЁ', 1, 3));
        $this->assertSame('БГДЖ', cstr::substr('БГДЖИЛЁ', 0, 4));
        $this->assertSame('Ё', cstr::substr('БГДЖИЛЁ', -1, 1));
        $this->assertEmpty(cstr::substr('Б', 2));
    }

    public function testSubstrCount() {
        $this->assertSame(3, cstr::substrCount('cresenityPHPFramework', 'e'));
        $this->assertSame(0, cstr::substrCount('cresenityPHPFramework', 'z'));
        $this->assertSame(1, cstr::substrCount('cresenityPHPFramework', 'e', 5));
        $this->assertSame(0, cstr::substrCount('cresenityPHPFramework', 'e', 5, 5));
        $this->assertSame(1, cstr::substrCount('cresenityPHPFramework', 'e', 5, 12));
    }

    public function testSubstrReplace() {
        $this->assertSame('12:00', cstr::substrReplace('1200', ':', 2, 0));
        $this->assertSame('The Cresenity Framework', cstr::substrReplace('The Framework', 'Cresenity ', 4, 0));
        $this->assertSame('Cresenity – The PHP Framework for Web Artisans', cstr::substrReplace('Cresenity Framework', '– The PHP Framework for Web Artisans', 10));
    }

    public function testLcfirstUcfirst() {
        $this->assertSame('cresenity', cstr::lcfirst('Cresenity'));
        $this->assertSame('cresenity framework', cstr::lcfirst('Cresenity framework'));
        $this->assertSame('мама', cstr::lcfirst('Мама'));
        $this->assertSame('мама мыла раму', cstr::lcfirst('Мама мыла раму'));

        $this->assertSame('Cresenity', cstr::ucfirst('cresenity'));
        $this->assertSame('Cresenity framework', cstr::ucfirst('cresenity framework'));
        $this->assertSame('Мама', cstr::ucfirst('мама'));
        $this->assertSame('Мама мыла раму', cstr::ucfirst('мама мыла раму'));
    }

    public function testUcsplit() {
        $this->assertSame(['Cresenity_p_h_p_framework'], cstr::ucsplit('Cresenity_p_h_p_framework'));
        $this->assertSame(['Cresenity_', 'P_h_p_framework'], cstr::ucsplit('Cresenity_P_h_p_framework'));
        $this->assertSame(['cresenity', 'P', 'H', 'P', 'Framework'], cstr::ucsplit('cresenityPHPFramework'));
        $this->assertSame(['Cresenity-ph', 'P-framework'], cstr::ucsplit('Cresenity-phP-framework'));
        $this->assertSame(['Żółta', 'Łódka'], cstr::ucsplit('ŻółtaŁódka'));
        $this->assertSame(['sind', 'Öde', 'Und', 'So'], cstr::ucsplit('sindÖdeUndSo'));
        $this->assertSame(['Öffentliche', 'Überraschungen'], cstr::ucsplit('ÖffentlicheÜberraschungen'));
    }

    public function testUuid() {
        $this->assertTrue(cstr::isUuid((string) cstr::uuid()));
        $this->assertTrue(cstr::isUuid((string) cstr::orderedUuid()));
        $this->assertNotSame((string) cstr::uuid(), (string) cstr::uuid());
    }

    public function testUlid() {
        $this->assertTrue(cstr::isUlid((string) cstr::ulid()));
        $this->assertNotSame((string) cstr::ulid(), (string) cstr::ulid());
    }

    public function testPadBoth() {
        $this->assertSame('__Alien___', cstr::padBoth('Alien', 10, '_'));
        $this->assertSame('  Alien   ', cstr::padBoth('Alien', 10));
        $this->assertSame('  ❤MultiByte☆   ', cstr::padBoth('❤MultiByte☆', 16));
        $this->assertSame('❤☆❤MultiByte☆❤☆❤', cstr::padBoth('❤MultiByte☆', 16, '❤☆'));
    }

    public function testPadLeft() {
        $this->assertSame('-=-=-Alien', cstr::padLeft('Alien', 10, '-='));
        $this->assertSame('     Alien', cstr::padLeft('Alien', 10));
        $this->assertSame('     ❤MultiByte☆', cstr::padLeft('❤MultiByte☆', 16));
        $this->assertSame('❤☆❤☆❤❤MultiByte☆', cstr::padLeft('❤MultiByte☆', 16, '❤☆'));
    }

    public function testPadRight() {
        $this->assertSame('Alien-=-=-', cstr::padRight('Alien', 10, '-='));
        $this->assertSame('Alien     ', cstr::padRight('Alien', 10));
        $this->assertSame('❤MultiByte☆     ', cstr::padRight('❤MultiByte☆', 16));
        $this->assertSame('❤MultiByte☆❤☆❤☆❤', cstr::padRight('❤MultiByte☆', 16, '❤☆'));
    }

    public function testSwap() {
        $this->assertSame('PHP 8 is fantastic', cstr::swap(['PHP' => 'PHP 8', 'awesome' => 'fantastic'], 'PHP is awesome'));
        $this->assertSame('foo bar baz', cstr::swap(['ⓐⓑ' => 'baz'], 'foo bar ⓐⓑ'));
    }

    public function testWordCount() {
        $this->assertEquals(2, cstr::wordCount('Hello, world!'));
        $this->assertEquals(9, cstr::wordCount('Hi, this is my first contribution to the framework.'));
        $this->assertEquals(0, cstr::wordCount('мама'));
        $this->assertEquals(0, cstr::wordCount('мама мыла раму'));
        //beda dari hulu: belum ada parameter daftar karakter tambahan
    }

    public function testRepeat() {
        $this->assertSame('aaaaa', cstr::repeat('a', 5));
        $this->assertSame('', cstr::repeat('', 5));
    }

    public function testPlural() {
        $this->assertSame('children', cstr::plural('child'));
        $this->assertSame('cod', cstr::plural('cod'));
        $this->assertSame('The words', cstr::plural('The word'));
        $this->assertSame('Bouquetés', cstr::plural('Bouqueté'));
        $this->assertSame('cars', cstr::plural('car'));
        $this->assertSame('car', cstr::plural('car', 1));
        $this->assertSame('cars', cstr::plural('car', 2));
        $this->assertSame('cars', cstr::plural('car', 0));
        //beda dari hulu: $count harus angka, belum menerima Countable/array
    }

    public function testPluralStudly() {
        $this->assertSame('RealHumans', cstr::pluralStudly('RealHuman'));
        $this->assertSame('Models', cstr::pluralStudly('Model'));
        $this->assertSame('VortexFields', cstr::pluralStudly('VortexField'));
        $this->assertSame('MultipleWordsInOneStrings', cstr::pluralStudly('MultipleWordsInOneString'));
        $this->assertSame('RealHuman', cstr::pluralStudly('RealHuman', 1));
        $this->assertSame('RealHumans', cstr::pluralStudly('RealHuman', 2));
    }

    public function testSingular() {
        $this->assertSame('child', cstr::singular('children'));
        $this->assertSame('car', cstr::singular('cars'));
        $this->assertSame('Bouqueté', cstr::singular('Bouquetés'));
    }

    public function testTransliterate() {
        $this->assertSame('test', cstr::transliterate('ⓣⓔⓢⓣ'));
        $this->assertSame('test', cstr::transliterate('ｔｅｓｔ'));
        $this->assertSame('test@cresenity.com', cstr::transliterate('ⓣⓔⓢⓣ@ⓒⓡⓔⓢⓔⓝⓘⓣⓨ.ⓒⓞⓜ'));
    }
}

class SupportCstrTestStringable {
    /**
     * @var string
     */
    private $value;

    public function __construct($value) {
        $this->value = $value;
    }

    public function __toString() {
        return $this->value;
    }
}
