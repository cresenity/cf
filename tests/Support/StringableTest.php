<?php
use PHPUnit\Framework\TestCase;

/**
 * Port SupportStringableTest hulu ke CBase_String (cstr::of()) - antarmuka string fluent.
 */
class StringableTest extends TestCase {
    /**
     * @param string $value
     *
     * @return CBase_String
     */
    protected function of($value) {
        return cstr::of($value);
    }

    public function testOfReturnsStringableAndCastsToString() {
        $s = $this->of('hello');
        $this->assertInstanceOf(CBase_String::class, $s);
        $this->assertSame('hello', (string) $s);
        $this->assertSame('hello', $s->toString());
        $this->assertSame('hello', $s->value());
        $this->assertSame('"hello"', json_encode($s));
        $this->assertSame('', (string) $this->of(null), 'null menjadi string kosong');
        $this->assertSame('12', (string) $this->of(12));
    }

    public function testClassBasename() {
        $this->assertSame('String', (string) $this->of('CBase_String')->classBasename(), 'nama kelas CF berprefiks underscore: segmen terakhir');
        $this->assertSame('Baz', (string) $this->of('Foo\\Bar\\Baz')->classBasename());
    }

    public function testIsAsciiAndIsUuidAndIsJson() {
        $this->assertTrue($this->of('A')->isAscii());
        $this->assertFalse($this->of('ù')->isAscii());
        $this->assertTrue($this->of('a0a2a2d2-0b87-4a18-83f2-2529882be2de')->isUuid());
        $this->assertFalse($this->of('not-a-uuid')->isUuid());
        $this->assertTrue($this->of('{"a":1}')->isJson());
        $this->assertFalse($this->of('{a:1}')->isJson());
        $this->assertFalse($this->of('')->isJson());
    }

    public function testIsEmptyAndIsNotEmpty() {
        $this->assertTrue($this->of('')->isEmpty());
        $this->assertFalse($this->of('A')->isEmpty());
        $this->assertFalse($this->of('0')->isEmpty(), '"0" bukan kosong');
        $this->assertTrue($this->of('A')->isNotEmpty());
    }

    public function testIsMatchAndTest() {
        $this->assertTrue($this->of('Hello, Laravel!')->isMatch('/.*,.*!/'));
        $this->assertTrue($this->of('Hello, Laravel!')->test('/Laravel/'));
        $this->assertFalse($this->of('Hello!')->test('/Laravel/'));
    }

    public function testMatchAndMatchAll() {
        $this->assertSame('bar', (string) $this->of('foo bar')->match('/bar/'));
        $this->assertSame('bar', (string) $this->of('foo bar')->match('/foo (.*)/'), 'grup tangkapan pertama yang dikembalikan');
        $this->assertTrue($this->of('foo bar')->match('/nothing/')->isEmpty());
        $this->assertSame(['bar', 'bar'], $this->of('bar foo bar')->matchAll('/bar/')->all());
        $this->assertSame(['un', 'ly'], $this->of('bar fun bar fly')->matchAll('/f(\w*)/')->all());
        $this->assertTrue($this->of('bar')->matchAll('/nothing/')->isEmpty());
    }

    public function testTrimVariants() {
        $this->assertSame('foo', (string) $this->of(' foo ')->trim());
        $this->assertSame('foo ', (string) $this->of(' foo ')->ltrim());
        $this->assertSame(' foo', (string) $this->of(' foo ')->rtrim());
        $this->assertSame('foo', (string) $this->of('-foo-')->trim('-'));
        $this->assertSame('foo bar', (string) $this->of(" \t foo    bar \n")->squish(), 'squish merapatkan spasi di dalam juga');
    }

    public function testWhenContainsAndWhenContainsAll() {
        $this->assertSame('Tony Stark', (string) $this->of('stark')->whenContains('tar', function ($s) {
            return $s->prepend('Tony ')->title();
        }, function ($s) {
            return $s->prepend('Arno ')->title();
        }));
        $this->assertSame('stark', (string) $this->of('stark')->whenContains('xxx', function ($s) {
            return $s->prepend('Tony ')->title();
        }), 'tanpa default → string apa adanya');
        $this->assertSame('Arno Stark', (string) $this->of('stark')->whenContains('xxx', function ($s) {
            return $s->prepend('Tony ')->title();
        }, function ($s) {
            return $s->prepend('Arno ')->title();
        }));
        $this->assertSame('Tony Stark', (string) $this->of('stark')->whenContains(['xxx', 'tar'], function ($s) {
            return $s->prepend('Tony ')->title();
        }), 'daftar needle: salah satu cukup');
        $this->assertSame('Tony Stark', (string) $this->of('tony stark')->whenContainsAll(['tony', 'stark'], function ($s) {
            return $s->title();
        }));
        $this->assertSame('tony stark', (string) $this->of('tony stark')->whenContainsAll(['tony', 'xxx'], function ($s) {
            return $s->title();
        }));
    }

    public function testWhenEndsWithAndWhenStartsWith() {
        $this->assertSame('Tony Stark', (string) $this->of('tony stark')->whenEndsWith('ark', function ($s) {
            return $s->title();
        }));
        $this->assertSame('tony stark', (string) $this->of('tony stark')->whenEndsWith('xxx', function ($s) {
            return $s->title();
        }));
        $this->assertSame('Tony Stark', (string) $this->of('tony stark')->whenStartsWith('ton', function ($s) {
            return $s->title();
        }));
        $this->assertSame('Tony Stark', (string) $this->of('tony stark')->whenStartsWith(['xxx', 'ton'], function ($s) {
            return $s->title();
        }));
    }

    public function testWhenExactlyWhenIsWhenTest() {
        $this->assertSame('Nailed it...!', (string) $this->of('Tony Stark')->whenExactly('Tony Stark', function ($s) {
            return 'Nailed it...!';
        }, function ($s) {
            return 'Swing and a miss...!';
        }));
        $this->assertSame('Swing and a miss...!', (string) $this->of('Tony Stark')->whenExactly('Iron Man', function ($s) {
            return 'Nailed it...!';
        }, function ($s) {
            return 'Swing and a miss...!';
        }));
        $this->assertSame('Nailed it...!', (string) $this->of('Tony Stark')->whenNotExactly('Iron Man', function ($s) {
            return 'Nailed it...!';
        }));
        $this->assertSame('Winner: /', (string) $this->of('/')->whenIs('/', function ($s) {
            return $s->prepend('Winner: ');
        }, function ($s) {
            return 'Try again';
        }));
        $this->assertSame('Winner: foo/bar/baz', (string) $this->of('foo/bar/baz')->whenIs('foo/*', function ($s) {
            return $s->prepend('Winner: ');
        }), 'pola wildcard');
        $this->assertSame('Tony Stark', (string) $this->of('tony stark')->whenTest('/tony/', function ($s) {
            return $s->title();
        }));
        $this->assertSame('tony stark', (string) $this->of('tony stark')->whenTest('/xxx/', function ($s) {
            return $s->title();
        }));
    }

    public function testWhenIsAsciiAndWhenIsUuid() {
        $this->assertSame('Ascii: A', (string) $this->of('A')->whenIsAscii(function ($s) {
            return $s->prepend('Ascii: ');
        }, function ($s) {
            return $s->prepend('Not Ascii: ');
        }));
        $this->assertSame('Not Ascii: ù', (string) $this->of('ù')->whenIsAscii(function ($s) {
            return $s->prepend('Ascii: ');
        }, function ($s) {
            return $s->prepend('Not Ascii: ');
        }));
        $this->assertSame('Uuid: 2cdc7039-65a6-4ac7-8e5d-d554a98e7b15', (string) $this->of('2cdc7039-65a6-4ac7-8e5d-d554a98e7b15')->whenIsUuid(function ($s) {
            return $s->prepend('Uuid: ');
        }, function ($s) {
            return $s->prepend('Not Uuid: ');
        }));
    }

    public function testWhenEmptyAndWhenNotEmptyAndWhen() {
        $this->assertSame('empty', (string) $this->of('')->whenEmpty(function () {
            return 'empty';
        }));
        $this->assertSame('not-empty', (string) $this->of('not-empty')->whenEmpty(function () {
            return 'empty';
        }));
        $this->assertSame('', (string) $this->of('')->whenEmpty(function ($s) {
            $s->append('x');
        }), 'callback tanpa nilai balik → string awal');
        $this->assertSame('Not Empty', (string) $this->of('not empty')->whenNotEmpty(function ($s) {
            return $s->title();
        }));
        $this->assertSame('', (string) $this->of('')->whenNotEmpty(function ($s) {
            return $s->title();
        }));
        $this->assertSame('Taylor Otwell', (string) $this->of('taylor otwell')->when(true, function ($s) {
            return $s->title();
        }));
        $this->assertSame('taylor otwell', (string) $this->of('taylor otwell')->when(false, function ($s) {
            return $s->title();
        }));
        $this->assertSame('TAYLOR OTWELL', (string) $this->of('taylor otwell')->when(false, function ($s) {
            return $s->title();
        }, function ($s) {
            return $s->upper();
        }));
        $this->assertSame('x-1', (string) $this->of('x')->when(1, function ($s, $value) {
            return $s->append('-' . $value);
        }), 'nilai kondisi diteruskan ke callback');
    }

    public function testTitleHeadlineAndCaseConversions() {
        $this->assertSame('Jefferson Costella', (string) $this->of('jefferson costella')->title());
        $this->assertSame('Jefferson Costella', (string) $this->of('jefFErson coSTella')->title());
        $this->assertSame('Jefferson Costella', (string) $this->of('jefferson_costella')->headline());
        $this->assertSame('Jefferson Costella Uses Laravel', (string) $this->of('jeffersonCostellaUsesLaravel')->headline());
        $this->assertSame('Laravel P H P Framework', (string) $this->of('laravel_p_h_p_framework')->headline());
        $this->assertSame('Laravel Php Framework', (string) $this->of('laravel_php_framework')->headline());
        $this->assertSame('laravel', (string) $this->of('Laravel')->lcfirst());
        $this->assertSame('Laravel', (string) $this->of('laravel')->ucfirst());
        $this->assertSame('LARAVEL', (string) $this->of('laravel')->upper());
        $this->assertSame('laravel', (string) $this->of('LARAVEL')->lower());
        $this->assertSame('LaravelPhpFramework', (string) $this->of('laravel_php_framework')->studly());
        $this->assertSame('laravelPhpFramework', (string) $this->of('Laravel_php_framework')->camel());
        $this->assertSame('laravel_php_framework', (string) $this->of('LaravelPhpFramework')->snake());
        $this->assertSame('laravel-php-framework', (string) $this->of('LaravelPhpFramework')->kebab());
        $this->assertSame('laravel_php_framework', (string) $this->of('LaravelPhpFramework')->snake('_'));
    }

    public function testPluralAndSingular() {
        $this->assertSame('children', (string) $this->of('child')->plural());
        $this->assertSame('child', (string) $this->of('child')->plural(1));
        $this->assertSame('children', (string) $this->of('child')->plural(2));
        $this->assertSame('child', (string) $this->of('children')->singular());
        $this->assertSame('RealHumans', (string) $this->of('RealHuman')->pluralStudly());
        $this->assertSame('RealHuman', (string) $this->of('RealHuman')->pluralStudly(1));
    }

    public function testAsciiSlugAndNewLine() {
        $this->assertSame('@', (string) $this->of('@')->ascii());
        $this->assertSame('u', (string) $this->of('ü')->ascii());
        $this->assertSame('hello-world', (string) $this->of('hello world')->slug());
        $this->assertSame('hello_world', (string) $this->of('hello world')->slug('_'));
        $this->assertSame("Laravel\n", (string) $this->of('Laravel')->newLine());
        $this->assertSame("Laravel\n\n", (string) $this->of('Laravel')->newLine(2));
    }

    public function testStartsWithEndsWithContains() {
        $this->assertTrue($this->of('jason')->startsWith('jas'));
        $this->assertTrue($this->of('jason')->startsWith(['day', 'jas']));
        $this->assertFalse($this->of('jason')->startsWith('day'));
        $this->assertTrue($this->of('jason')->endsWith('on'));
        $this->assertTrue($this->of('jason')->endsWith(['no', 'on']));
        $this->assertFalse($this->of('jason')->endsWith('no'));
        $this->assertTrue($this->of('taylor')->contains('ylo'));
        $this->assertTrue($this->of('taylor')->contains(['xxx', 'ylo']));
        $this->assertFalse($this->of('taylor')->contains(''));
        $this->assertTrue($this->of('taylor otwell')->containsAll(['taylor', 'otwell']));
        $this->assertFalse($this->of('taylor otwell')->containsAll(['taylor', 'xxx']));
        $this->assertTrue($this->of('taylor')->exactly('taylor'));
        $this->assertFalse($this->of('taylor')->exactly('Taylor'));
    }

    public function testBeforeAfterBetween() {
        $this->assertSame('han', (string) $this->of('hannah')->before('nah'));
        $this->assertSame('ha', (string) $this->of('hannah')->before('n'));
        $this->assertSame('hannah', (string) $this->of('hannah')->before('xxx'), 'tidak ditemukan → utuh');
        $this->assertSame('han', (string) $this->of('hannah')->beforeLast('nah'));
        $this->assertSame('hanna', (string) $this->of('hannah')->beforeLast('h'));
        $this->assertSame('nah', (string) $this->of('hannah')->after('han'));
        $this->assertSame('nah', (string) $this->of('hannah')->after('n'));
        $this->assertSame('hannah', (string) $this->of('hannah')->after('xxx'));
        $this->assertSame('', (string) $this->of('hannah')->afterLast('h'));
        $this->assertSame('ah', (string) $this->of('hannah')->afterLast('n'));
        $this->assertSame('b', (string) $this->of('abc')->between('a', 'c'));
        $this->assertSame('b', (string) $this->of('dddabc')->between('a', 'c'));
        $this->assertSame('foo][bar', (string) $this->of('[foo][bar]')->between('[', ']'), 'between = sampai kemunculan terakhir penutup');
        $this->assertSame('foo', (string) $this->of('[foo][bar]')->betweenFirst('[', ']'));
    }

    public function testStartFinishAndIs() {
        $this->assertSame('/test/string', (string) $this->of('test/string')->start('/'));
        $this->assertSame('/test/string', (string) $this->of('/test/string')->start('/'));
        $this->assertSame('/test/string', (string) $this->of('//test/string')->start('/'));
        $this->assertSame('abbc', (string) $this->of('ab')->finish('bc'));
        $this->assertSame('abbc', (string) $this->of('abbcbc')->finish('bc'));
        $this->assertTrue($this->of('/')->is('/'));
        $this->assertFalse($this->of('/')->is(' /'));
        $this->assertTrue($this->of('foo/bar/baz')->is('foo/*'));
        $this->assertTrue($this->of('foo/bar/baz')->is(['xxx', '*/baz']));
    }

    public function testLimitWordsLengthAndCharAt() {
        $this->assertSame('Laravel is...', (string) $this->of('Laravel is a free, open source PHP web application framework.')->limit(10));
        $this->assertSame('这是一...', (string) $this->of('这是一段中文')->limit(6));
        $this->assertSame('Laravel is a (...)', (string) $this->of('Laravel is a free, open source PHP web application framework.')->limit(12, ' (...)'));
        $this->assertSame('Taylor...', (string) $this->of('Taylor Otwell')->words(1));
        $this->assertSame('Taylor___', (string) $this->of('Taylor Otwell')->words(1, '___'));
        $this->assertSame(11, $this->of('foo bar baz')->length());
        $this->assertSame(11, $this->of('foo bar baz')->length('UTF-8'));
        $this->assertSame('a', $this->of('abc')->charAt(0));
        $this->assertSame('c', $this->of('abc')->charAt(-1));
        $this->assertFalse($this->of('abc')->charAt(5));
        $this->assertSame(2, $this->of('Hello, world!')->wordCount());
    }

    public function testReplaceFamily() {
        $this->assertSame('foo/foo/foo', (string) $this->of('?/?/?')->replace('?', 'foo'));
        $this->assertSame('foo/bar/baz', (string) $this->of('?/?/?')->replaceArray('?', ['foo', 'bar', 'baz']));
        $this->assertSame('foo/bar/baz/?', (string) $this->of('?/?/?/?')->replaceArray('?', ['foo', 'bar', 'baz']));
        $this->assertSame('foo/bar', (string) $this->of('?/?')->replaceArray('?', ['foo', 'bar', 'baz']), 'kelebihan pengganti diabaikan');
        $this->assertSame('fooqux foobar', (string) $this->of('foobar foobar')->replaceFirst('bar', 'qux'));
        $this->assertSame('foobar foobar', (string) $this->of('foobar foobar')->replaceFirst('xxx', 'qux'));
        $this->assertSame('foobar fooqux', (string) $this->of('foobar foobar')->replaceLast('bar', 'qux'));
        $this->assertSame('foobar foobar', (string) $this->of('foobar foobar')->replaceLast('', 'qux'), 'pencarian kosong → utuh');
        $this->assertSame('foo', (string) $this->of('foo bar')->replaceMatches('/ bar/', ''));
        $this->assertSame('123', (string) $this->of('(+1) 2-3')->replaceMatches('/[^0-9]++/', ''));
        $this->assertSame('[1][2][3]', (string) $this->of('123')->replaceMatches('/\d/', function ($match) {
            return '[' . $match[0] . ']';
        }));
    }

    public function testSwapAndMask() {
        $this->assertSame('PHP 8 is fantastic', (string) $this->of('PHP is great')->swap(['PHP' => 'PHP 8', 'great' => 'fantastic']));
        $this->assertSame('tay*************', (string) $this->of('taylor@email.com')->mask('*', 3));
        $this->assertSame('******@email.com', (string) $this->of('taylor@email.com')->mask('*', 0, 6));
        $this->assertSame('tay***@email.com', (string) $this->of('taylor@email.com')->mask('*', -13, 3));
        $this->assertSame('taylor@email.com', (string) $this->of('taylor@email.com')->mask('*', 20), 'indeks di luar panjang → utuh');
    }

    public function testSubstrFamilyAndPadding() {
        $this->assertSame('Ё', (string) $this->of('БГДЖИЛЁ')->substr(-1));
        $this->assertSame('ЛЁ', (string) $this->of('БГДЖИЛЁ')->substr(-2));
        $this->assertSame('И', (string) $this->of('БГДЖИЛЁ')->substr(-3, 1));
        $this->assertSame('ДЖИЛ', (string) $this->of('БГДЖИЛЁ')->substr(2, -1));
        $this->assertSame(3, $this->of('laravelPHPFramework')->substrCount('a'));
        $this->assertSame(2, $this->of('laravelPHPFramework')->substrCount('a', 2));
        $this->assertSame(1, $this->of('laravelPHPFramework')->substrCount('a', 2, 3));
        $this->assertSame('1300', (string) $this->of('1200')->substrReplace('3', 1, 1));
        $this->assertSame('The Laravel Framework', (string) $this->of('The Framework')->substrReplace('Laravel ', 4, 0));
        $this->assertSame('  Alien  ', (string) $this->of('Alien')->padBoth(9, ' '));
        $this->assertSame('-=--=Alien-=--=', (string) $this->of('Alien')->padBoth(15, '-=-'), 'pola pad diulang lalu dipotong, seperti str_pad');
        $this->assertSame('-=--=Alien', (string) $this->of('Alien')->padLeft(10, '-=-'));
        $this->assertSame('Alien-=--=', (string) $this->of('Alien')->padRight(10, '-=-'));
        $this->assertSame('     Alien', (string) $this->of('Alien')->padLeft(10));
    }

    public function testExplodeSplitScan() {
        $this->assertSame(['Foo', 'Bar', 'Baz'], $this->of('Foo Bar Baz')->explode(' ')->all());
        $this->assertSame(['Foo', 'Bar Baz'], $this->of('Foo Bar Baz')->explode(' ', 2)->all());
        $this->assertSame(['one', 'two', 'three'], $this->of('one-two-three')->split('/-/')->all());
        $this->assertSame(['one', 'two-three'], $this->of('one-two-three')->split('/-/', 2)->all());
        $this->assertSame(['ab', 'cd', 'e'], $this->of('abcde')->split(2)->all(), 'angka = potong per n karakter');
        $this->assertSame([123, 'Laravel', 'is', 'awesome'], $this->of('123 Laravel is awesome')->scan('%d %s %s %s')->all());
        $this->assertSame([0.123, 'foo'], $this->of('0.123 foo')->scan('%f %s')->all());
    }

    public function testStripTagsWrapPrependAppend() {
        $this->assertSame('beforeafter', (string) $this->of('before<br>after')->stripTags());
        $this->assertSame('before<br>after', (string) $this->of('before<br>after')->stripTags('<br>'));
        $this->assertSame('"value"', (string) $this->of('value')->wrap('"'));
        $this->assertSame('[value]', (string) $this->of('value')->wrap('[', ']'));
        $this->assertSame('foobar', (string) $this->of('bar')->prepend('foo'));
        $this->assertSame('foobar', (string) $this->of('foo')->append('bar'));
        $this->assertSame('foobarbaz', (string) $this->of('foo')->append('bar', 'baz'), 'append menerima banyak argumen');
        $this->assertSame('Hello World', (string) $this->of('World')->prepend('Hello', ' '));
    }

    public function testToHtmlStringAndArrayAccess() {
        $html = $this->of('<b>x</b>')->toHtmlString();
        $this->assertInstanceOf(CBase_HtmlString::class, $html);
        $this->assertSame('<b>x</b>', $html->toHtml());
        $s = $this->of('abc');
        $this->assertTrue(isset($s[1]));
        $this->assertFalse(isset($s[5]));
        $this->assertSame('b', $s[1]);
    }

    public function testMagicPropertyAccessCallsTheMethod() {
        $s = $this->of('Taylor Otwell');
        $this->assertSame('taylor otwell', (string) $s->lower);
        $this->assertSame(13, $s->length);
        $this->assertSame('taylor-otwell', (string) $s->slug);
    }

    public function testToIntegerFloatBooleanDate() {
        $this->assertSame(123, $this->of('123')->toInteger());
        $this->assertSame(0, $this->of('abc')->toInteger());
        $this->assertSame(1.5, $this->of('1.5')->toFloat());
        $this->assertTrue($this->of('true')->toBoolean());
        $this->assertTrue($this->of('1')->toBoolean());
        $this->assertTrue($this->of('on')->toBoolean());
        $this->assertFalse($this->of('false')->toBoolean());
        $this->assertFalse($this->of('0')->toBoolean());
        $this->assertFalse($this->of('')->toBoolean());
        $date = $this->of('2026-01-15 08:30:00')->toDate();
        $this->assertInstanceOf(CCarbon::class, $date);
        $this->assertSame('2026-01-15 08:30:00', $date->format('Y-m-d H:i:s'));
        $this->assertSame('15/01/2026', $this->of('15-01-2026')->toDate('d-m-Y')->format('d/m/Y'));
    }

    public function testUcsplitAndDirnameBasename() {
        $this->assertSame(['Laravel', 'P', 'H', 'P', 'Framework'], $this->of('LaravelPHPFramework')->ucsplit()->all());
        $this->assertSame(['Laravel_p_h_p_framework'], $this->of('Laravel_p_h_p_framework')->ucsplit()->all());
        $this->assertSame('/framework/tests', (string) $this->of('/framework/tests/Support')->dirname());
        $this->assertSame('/framework', (string) $this->of('/framework/tests/Support')->dirname(2));
        $this->assertSame('Support', (string) $this->of('/framework/tests/Support')->basename());
        $this->assertSame('Support', (string) $this->of('/framework/tests/Support.php')->basename('.php'));
    }

    public function testParseCallback() {
        $this->assertSame(['Class', 'method'], $this->of('Class@method')->parseCallback());
        $this->assertSame(['Class', 'default'], $this->of('Class')->parseCallback('default'));
        $this->assertSame(['Class', null], $this->of('Class')->parseCallback());
    }

    public function testChainingProducesNewInstances() {
        $original = $this->of('hello');
        $upper = $original->upper();
        $this->assertNotSame($original, $upper);
        $this->assertSame('hello', (string) $original, 'instance asal tidak berubah');
        $this->assertSame('HELLO', (string) $upper);
    }
}
