<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ApiTestSupport.php';

/**
 * CApi_Transformer_Factory + CApi_Transformer_Binding: pendaftaran transformer per kelas,
 * pencarian binding (objek, koleksi, nama kelas), resolusi transformer, meta, dan callback.
 */
class ApiTransformerTest extends TestCase {
    protected function setUp(): void {
        ApiTestSupport::registerGroup();
        CApi::setRequest(CApi_HTTP_Request::createFromBaseHttp(CHTTP_Request::create('/api/x', 'GET')));
    }

    /**
     * @return array [CApi_Transformer_Factory, ApiTransformerTestAdapter]
     */
    private function factory() {
        $adapter = new ApiTransformerTestAdapter();

        return [new CApi_Transformer_Factory($adapter), $adapter];
    }

    public function testRegisterReturnsTheBindingAndKeepsIt() {
        list($factory) = $this->factory();

        $binding = $factory->register(ApiTransformerTestItem::class, ApiTransformerTestTransformer::class, ['p' => 1]);

        $this->assertInstanceOf(CApi_Transformer_Binding::class, $binding);
        $this->assertSame(['p' => 1], $binding->getParameters());
        $this->assertSame([ApiTransformerTestItem::class => $binding], $factory->getTransformerBindings());
    }

    public function testTransformableResponseNeedsAnObjectOrClassNameWithABinding() {
        list($factory) = $this->factory();
        $factory->register(ApiTransformerTestItem::class, new ApiTransformerTestTransformer());

        $this->assertTrue($factory->transformableResponse(new ApiTransformerTestItem('a')));
        $this->assertTrue($factory->transformableResponse(ApiTransformerTestItem::class), 'nama kelas juga transformable');
        $this->assertFalse($factory->transformableResponse(new stdClass()));
        $this->assertFalse($factory->transformableResponse(['array' => 'bukan']));
        $this->assertFalse($factory->transformableResponse(123));
        $this->assertTrue($factory->transformableType('string'));
        $this->assertFalse($factory->transformableType([]));
    }

    public function testCollectionsAreLookedUpByTheirFirstItem() {
        list($factory) = $this->factory();
        $binding = $factory->register(ApiTransformerTestItem::class, new ApiTransformerTestTransformer());

        $collection = c::collect([new ApiTransformerTestItem('a'), new ApiTransformerTestItem('b')]);
        $this->assertTrue($factory->transformableResponse($collection));
        $this->assertSame($binding, $factory->getBinding($collection));

        //koleksi kosong tidak punya item untuk dicek → bukan CCollection yang terdaftar → tidak transformable
        $this->assertFalse($factory->transformableResponse(c::collect([])));
        $this->assertFalse($factory->transformableResponse(c::collect([new stdClass()])));
    }

    public function testGetBindingThrowsForAnUnboundClass() {
        list($factory) = $this->factory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to find bound transformer for "stdClass" class.');
        $factory->getBinding(new stdClass());
    }

    public function testTransformHandsEverythingToTheAdapter() {
        list($factory, $adapter) = $this->factory();
        $transformer = new ApiTransformerTestTransformer();
        $binding = $factory->register(ApiTransformerTestItem::class, $transformer, ['x' => 1]);
        $item = new ApiTransformerTestItem('nilai');

        $result = $factory->transform($item);

        $this->assertSame(['transformed' => 'nilai'], $result);
        $this->assertSame($item, $adapter->lastResponse);
        $this->assertSame($transformer, $adapter->lastTransformer);
        $this->assertSame($binding, $adapter->lastBinding);
        $this->assertInstanceOf(CApi_HTTP_Request::class, $adapter->lastRequest);
        $this->assertSame('/api/x', '/' . $adapter->lastRequest->path());
    }

    public function testGetRequestWrapsAPlainFrameworkRequest() {
        list($factory) = $this->factory();

        $this->assertInstanceOf(CApi_HTTP_Request::class, $factory->getRequest());

        CApi::setRequest(CApi_HTTP_Request::createFromBaseHttp(CHTTP_Request::create('/lain', 'POST', ['k' => 'v'])));
        $this->assertSame('v', $factory->getRequest()->input('k'));
    }

    public function testAdapterCanBeReplacedDirectlyOrThroughACallable() {
        list($factory, $adapter) = $this->factory();
        $this->assertSame($adapter, $factory->getAdapter());

        $other = new ApiTransformerTestAdapter();
        $factory->setAdapter($other);
        $this->assertSame($other, $factory->getAdapter());

        $seen = null;
        $factory->setAdapter(function ($container) use (&$seen, $adapter) {
            $seen = $container;

            return $adapter;
        });
        $this->assertSame($adapter, $factory->getAdapter());
        $this->assertInstanceOf(CContainer_Container::class, $seen, 'callable menerima container');
    }

    public function testUnknownFactoryMethodsAreForwardedToTheAdapter() {
        list($factory) = $this->factory();

        $this->assertSame('dari adapter: halo', $factory->extra('halo'));
    }

    public function testBindingResolvesAClassNameThroughTheContainer() {
        $binding = new CApi_Transformer_Binding(ApiTransformerTestTransformer::class);

        $this->assertInstanceOf(ApiTransformerTestTransformer::class, $binding->resolveTransformer());
        $this->assertNotSame($binding->resolveTransformer(), $binding->resolveTransformer(), 'dibangun ulang setiap kali');
    }

    public function testBindingResolvesAClosureWithTheContainer() {
        $seen = null;
        $transformer = new ApiTransformerTestTransformer();
        $binding = new CApi_Transformer_Binding(function ($container) use (&$seen, $transformer) {
            $seen = $container;

            return $transformer;
        });

        $this->assertSame($transformer, $binding->resolveTransformer());
        $this->assertInstanceOf(CContainer_Container::class, $seen);
    }

    public function testBindingReturnsAnObjectResolverAsIs() {
        $transformer = new ApiTransformerTestTransformer();

        $this->assertSame($transformer, (new CApi_Transformer_Binding($transformer))->resolveTransformer());
    }

    public function testBindingRejectsAnInvalidResolver() {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to resolve transformer binding.');

        (new CApi_Transformer_Binding(12345))->resolveTransformer();
    }

    public function testBindingMetaAndCallback() {
        $calls = [];
        $binding = new CApi_Transformer_Binding('x', ['a' => 1], function ($resource, $fractal = null) use (&$calls) {
            $calls[] = func_get_args();
        });

        $this->assertSame(['a' => 1], $binding->getParameters());
        $this->assertSame([], $binding->getMeta());
        $binding->addMeta('page', 2);
        $binding->addMeta('total', 9);
        $this->assertSame(['page' => 2, 'total' => 9], $binding->getMeta());
        $binding->setMeta(['only' => true]);
        $this->assertSame(['only' => true], $binding->getMeta());

        $binding->fireCallback('res', 'fr');
        $binding->fireCallback('sekali');
        $this->assertSame([['res', 'fr'], ['sekali']], $calls);

        (new CApi_Transformer_Binding('x'))->fireCallback('tanpa callback tidak error');
    }
}

class ApiTransformerTestItem {
    /**
     * @var string
     */
    public $value;

    public function __construct($value) {
        $this->value = $value;
    }
}

class ApiTransformerTestTransformer {
    public function transform(ApiTransformerTestItem $item) {
        return ['transformed' => $item->value];
    }
}

class ApiTransformerTestAdapter implements CApi_Contract_Transformer_AdapterInterface {
    /**
     * @var mixed
     */
    public $lastResponse;

    /**
     * @var mixed
     */
    public $lastTransformer;

    /**
     * @var null|CApi_Transformer_Binding
     */
    public $lastBinding;

    /**
     * @var null|CApi_HTTP_Request
     */
    public $lastRequest;

    public function transform($response, $transformer, CApi_Transformer_Binding $binding, CApi_HTTP_Request $request) {
        $this->lastResponse = $response;
        $this->lastTransformer = $transformer;
        $this->lastBinding = $binding;
        $this->lastRequest = $request;

        return $transformer->transform($response);
    }

    public function extra($value) {
        return 'dari adapter: ' . $value;
    }
}
