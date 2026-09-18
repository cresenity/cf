<?php

use PHPUnit\Framework\TestCase;

/**
 * CHTTP_Request - lanjutan suite hulu untuk input yang belum tercakup HttpRequestTest:
 * isNotFilled/anyFilled, date, enum, string/collect, berkas, flash input, duplicate.
 */
class HttpRequestInputTest extends TestCase {
    protected function tearDown(): void {
        CBase::session()->flashInput([]);
    }

    public function testIsNotFilledMethod() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Hery', 'age' => '', 'city' => null]);

        $this->assertFalse($request->isNotFilled('name'));
        $this->assertTrue($request->isNotFilled('age'));
        $this->assertTrue($request->isNotFilled('city'));
        $this->assertTrue($request->isNotFilled('foo'));
        $this->assertFalse($request->isNotFilled(['name', 'email']));
        $this->assertTrue($request->isNotFilled(['foo', 'age']));
        $this->assertTrue($request->isNotFilled(['age', 'city']));

        $request = CHTTP_Request::create('/', 'GET', ['foo' => ['bar', 'baz' => '0']]);
        $this->assertFalse($request->isNotFilled('foo'));
        $this->assertTrue($request->isNotFilled('foo.bar'));
        $this->assertFalse($request->isNotFilled('foo.baz'));
    }

    public function testAnyFilledMethod() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Hery', 'age' => '', 'city' => null]);

        $this->assertTrue($request->anyFilled(['name']));
        $this->assertTrue($request->anyFilled('name'));
        $this->assertFalse($request->anyFilled(['age']));
        $this->assertFalse($request->anyFilled('age'));
        $this->assertFalse($request->anyFilled('foo'));
        $this->assertTrue($request->anyFilled(['age', 'name']));
        $this->assertTrue($request->anyFilled('age', 'name'));
        $this->assertTrue($request->anyFilled('foo', 'name'));
        $this->assertFalse($request->anyFilled(['age', 'city']));
        $this->assertFalse($request->anyFilled('foo', 'bar'));
    }

    public function testDateMethod() {
        $request = CHTTP_Request::create('/', 'GET', [
            'as_null' => null,
            'as_datetime' => '20-01-01 16:30:25',
            'as_format' => '1577896225',
            'as_timezone' => '20-01-01 13:30:25',
            'as_date' => '2020-01-01',
            'as_time' => '16:30:25',
        ]);
        $current = CCarbon::create(2020, 1, 1, 16, 30, 25);

        $this->assertNull($request->date('as_null'));
        $this->assertNull($request->date('doesnt_exists'));
        $this->assertInstanceOf(CCarbon::class, $request->date('as_datetime'));
        $this->assertEquals($current, $request->date('as_datetime'));
        //format 'U' menghasilkan UTC, zona waktu default aplikasi bukan UTC → bandingkan timestamp
        $this->assertSame(1577896225, $request->date('as_format', 'U')->getTimestamp());
        $this->assertSame('2020-01-01 16:30:25', $request->date('as_format', 'U')->setTimezone('UTC')->format('Y-m-d H:i:s'));
        //13:30:25 di Santiago (-03) = 16:30:25 UTC (hulu menguji ini dengan zona waktu aplikasi UTC)
        $this->assertEquals(CCarbon::create(2020, 1, 1, 16, 30, 25, 'UTC'), $request->date('as_timezone', null, 'America/Santiago'));
        $this->assertTrue($request->date('as_date')->isSameDay($current));
        $this->assertTrue($request->date('as_time')->isSameSecond('16:30:25'));
    }

    public function testDateMethodThrowsWhenTheValueIsInvalid() {
        $request = CHTTP_Request::create('/', 'GET', ['date' => 'invalid']);

        $this->expectException(InvalidArgumentException::class);
        $request->date('date');
    }

    public function testDateMethodThrowsWhenTheFormatIsInvalid() {
        $request = CHTTP_Request::create('/', 'GET', ['date' => '20-01-01 16:30:25']);

        $this->expectException(InvalidArgumentException::class);
        $request->date('date', 'invalid_format');
    }

    public function testEnumMethodIsNullWithoutEnumSupport() {
        $request = CHTTP_Request::create('/', 'GET', ['status' => 'aktif', 'empty' => '']);

        $this->assertNull($request->enum('empty', 'AnyEnum'));
        $this->assertNull($request->enum('missing', 'AnyEnum'));
        //PHP < 8.1 tidak punya enum; kelas biasa bukan enum → null, bukan error
        $this->assertNull($request->enum('status', stdClass::class));
    }

    public function testStringMethod() {
        $request = CHTTP_Request::create('/', 'GET', [
            'int' => 123,
            'int_str' => '456',
            'float' => 123.456,
            'float_str' => '123.456',
            'float_zero' => 0.000,
            'float_str_zero' => '0.000',
            'str' => 'abc',
            'empty_str' => '',
            'null' => null,
        ]);

        $this->assertInstanceOf(CBase_String::class, $request->string('int'));
        $this->assertInstanceOf(CBase_String::class, $request->string('unknown_key'));
        $this->assertSame('123', $request->string('int')->value());
        $this->assertSame('456', $request->string('int_str')->value());
        $this->assertSame('123.456', $request->string('float')->value());
        $this->assertSame('123.456', $request->string('float_str')->value());
        $this->assertSame('0', $request->string('float_zero')->value());
        $this->assertSame('0.000', $request->string('float_str_zero')->value());
        $this->assertSame('', $request->string('empty_str')->value());
        $this->assertSame('', $request->string('null')->value());
        $this->assertSame('', $request->string('unknown_key')->value());
        $this->assertSame('bawaan', $request->str('unknown_key', 'bawaan')->value());
        $this->assertSame('ABC', (string) $request->str('str')->upper());
    }

    public function testBooleanIntegerAndFloatMethods() {
        $request = CHTTP_Request::create('/', 'GET', [
            'with_trashed' => 'false',
            'download' => true,
            'checked' => 1,
            'unchecked' => '0',
            'with_on' => 'on',
            'with_yes' => 'yes',
            'int' => '57',
            'float' => '1.5',
            'text' => 'abc',
        ]);

        $this->assertTrue($request->boolean('checked'));
        $this->assertTrue($request->boolean('download'));
        $this->assertFalse($request->boolean('unchecked'));
        $this->assertFalse($request->boolean('with_trashed'));
        $this->assertTrue($request->boolean('with_on'));
        $this->assertTrue($request->boolean('with_yes'));
        $this->assertFalse($request->boolean('some_undefined_key'));
        $this->assertTrue($request->boolean('some_undefined_key', true));

        $this->assertSame(57, $request->integer('int'));
        $this->assertSame(0, $request->integer('text'));
        $this->assertSame(5, $request->integer('missing', 5));
        $this->assertSame(1.5, $request->float('float'));
        $this->assertSame(0.0, $request->float('text'));
        $this->assertSame(2.5, $request->float('missing', 2.5));
    }

    public function testCollectMethod() {
        $request = CHTTP_Request::create('/', 'GET', ['users' => [1, 2, 3]]);
        $this->assertInstanceOf(CCollection::class, $request->collect('users'));
        $this->assertTrue($request->collect('developers')->isEmpty());
        $this->assertEquals([1, 2, 3], $request->collect('users')->all());
        $this->assertEquals(['users' => [1, 2, 3]], $request->collect()->all());

        $request = CHTTP_Request::create('/', 'GET', ['email' => 'test@example.com']);
        $this->assertEquals(['test@example.com'], $request->collect('email')->all());

        $request = CHTTP_Request::create('/', 'GET', ['users' => [1, 2, 3], 'roles' => [4, 5, 6], 'foo' => ['bar', 'baz'], 'email' => 'test@example.com']);
        $this->assertTrue($request->collect(['developers'])->isEmpty());
        $this->assertEquals(['roles' => [4, 5, 6]], $request->collect(['roles'])->all());
        $this->assertEquals(['users' => [1, 2, 3], 'email' => 'test@example.com'], $request->collect(['users', 'email'])->all());
    }

    public function testOnlyAndExceptHandleNestedKeys() {
        $request = CHTTP_Request::create('/', 'GET', ['developer' => ['name' => 'Hery', 'age' => 30, 'skills' => ['php', 'js']], 'other' => 1]);

        $this->assertSame(['developer' => ['name' => 'Hery']], $request->only('developer.name'));
        $this->assertSame(['developer' => ['name' => 'Hery', 'age' => 30]], $request->only(['developer.name', 'developer.age']));
        $this->assertSame(['developer' => ['skills' => ['php', 'js']], 'other' => 1], $request->except('developer.name', 'developer.age'));
        $this->assertSame([], $request->only('missing'));
        $this->assertSame(['developer', 'other'], $request->keys());
    }

    public function testAllFilesConvertsUploadedFiles() {
        $file = new Symfony\Component\HttpFoundation\File\UploadedFile(__FILE__, 'input.php', 'text/plain', null, true);
        $request = CHTTP_Request::create('/', 'POST', ['name' => 'x'], [], ['doc' => $file, 'nested' => ['a' => $file]]);

        $files = $request->allFiles();
        $this->assertInstanceOf(CHTTP_UploadedFile::class, $files['doc']);
        $this->assertInstanceOf(CHTTP_UploadedFile::class, $files['nested']['a']);
        $this->assertSame('input.php', $files['doc']->getClientOriginalName());
        $this->assertTrue($request->hasFile('nested.a'));
        $this->assertFalse($request->hasFile('name'));
        $this->assertSame($files, $request->allFiles(), 'hasil konversi di-cache');

        $all = $request->all();
        $this->assertSame('x', $all['name']);
        $this->assertInstanceOf(CHTTP_UploadedFile::class, $all['doc']);
    }

    public function testFlashStoresTheInputAsOldInput() {
        $request = CHTTP_Request::create('/', 'POST', ['name' => 'Hery', 'password' => 'rahasia', 'age' => 30]);

        $request->flash();
        $this->assertSame(['name' => 'Hery', 'password' => 'rahasia', 'age' => 30], $request->session()->getOldInput());
        $this->assertSame('Hery', $request->session()->getOldInput('name'));
        //old() hanya membaca sesi bila request membawa sesi (hasSession()); di sini tidak
        $this->assertSame('bawaan', $request->old('name', 'bawaan'));

        $request->flashOnly('name', 'age');
        $this->assertSame(['name' => 'Hery', 'age' => 30], $request->session()->getOldInput());

        $request->flashExcept(['password']);
        $this->assertSame(['name' => 'Hery', 'age' => 30], $request->session()->getOldInput());

        $request->flush();
        $this->assertSame([], $request->session()->getOldInput());
    }

    public function testDuplicateKeepsTheFrameworkRequestClass() {
        $request = CHTTP_Request::create('/path', 'POST', ['name' => 'Hery'], [], [], ['HTTP_X_FOO' => 'bar']);

        $duplicate = $request->duplicate(['q' => 1]);
        $this->assertInstanceOf(CHTTP_Request::class, $duplicate);
        $this->assertNotSame($request, $duplicate);
        $this->assertSame(1, $duplicate->query('q'));
        $this->assertSame('Hery', $duplicate->input('name'));
        $this->assertSame('bar', $duplicate->header('X-Foo'));
    }

    public function testHasHeaderAndHeaderDefaults() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_X_FOO' => 'bar']);

        $this->assertTrue($request->hasHeader('X-Foo'));
        $this->assertTrue($request->hasHeader('x-foo'));
        $this->assertFalse($request->hasHeader('X-Bar'));
        $this->assertSame('bar', $request->header('X-Foo'));
        $this->assertSame('bawaan', $request->header('X-Bar', 'bawaan'));
        $this->assertIsArray($request->header());
    }

    public function testInputFallsBackToTheDefaultOnlyWhenTheKeyIsAbsent() {
        $request = CHTTP_Request::create('/', 'GET', ['empty' => '', 'zero' => '0', 'list' => ['a', 'b']]);

        $this->assertSame('', $request->input('empty', 'bawaan'));
        $this->assertSame('0', $request->input('zero', 'bawaan'));
        $this->assertSame('bawaan', $request->input('missing', 'bawaan'));
        $this->assertSame('b', $request->input('list.1'));
        $this->assertSame(['a', 'b'], $request->input('list'));
        $this->assertTrue($request->has('list.0'));
        $this->assertTrue($request->missing('list.5'));
        $this->assertTrue($request->hasAny(['missing', 'zero']));
        $this->assertTrue($request->exists('empty'));
    }

    public function testWhenHasAndWhenFilledCallbacks() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Hery', 'empty' => '']);
        $seen = [];

        $this->assertSame($request, $request->whenHas('name', function ($value) use (&$seen) {
            $seen[] = ['has', $value];
        }));
        $request->whenHas('missing', function () use (&$seen) {
            $seen[] = 'tidak-dipanggil';
        }, function () use (&$seen) {
            $seen[] = 'default-has';
        });
        $request->whenFilled('empty', function () use (&$seen) {
            $seen[] = 'tidak-dipanggil';
        }, function () use (&$seen) {
            $seen[] = 'default-filled';
        });
        $request->whenFilled('name', function ($value) use (&$seen) {
            $seen[] = ['filled', $value];
        });

        $this->assertSame([['has', 'Hery'], 'default-has', 'default-filled', ['filled', 'Hery']], $seen);
    }
}
