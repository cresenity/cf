<?php
use PHPUnit\Framework\TestCase;

class UjiJson_PostResource extends CHTTP_Resources_Json_JsonResource {
    public function toArray(CHTTP_Request $request) {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'custom' => true,
        ];
    }
}

class UjiJson_ConditionalResource extends CHTTP_Resources_Json_JsonResource {
    public function toArray(CHTTP_Request $request) {
        return [
            'id' => $this->id,
            'secret' => $this->when($this->is_admin, 'rahasia'),
            'fallback' => $this->when(false, 'x', 'default'),
            'null_only' => $this->whenNull($this->maybe, 'kosong'),
            'not_null' => $this->whenNotNull($this->maybe),
            'has_email' => $this->whenHas('email'),
            'has_email_value' => $this->whenHas('email', 'ada', 'tidak'),
            'unless' => $this->unless($this->is_admin, 'bukan-admin'),
            $this->mergeWhen($this->is_admin, ['role' => 'admin', 'level' => 9]),
            $this->mergeUnless($this->is_admin, ['role' => 'user']),
            $this->merge(['always' => 1]),
            'transformed' => $this->transform($this->maybe, function ($v) {
                return strtoupper($v);
            }, '-'),
            $this->attributes(['email', 'title']),
        ];
    }
}

class UjiJson_WithResource extends CHTTP_Resources_Json_JsonResource {
    public function toArray(CHTTP_Request $request) {
        return ['id' => $this->id];
    }

    public function with(CHTTP_Request $request) {
        return ['meta' => ['versi' => 2]];
    }

    public function withResponse(CHTTP_Request $request, CHTTP_JsonResponse $response) {
        $response->header('X-Uji', 'resource');
    }
}

class UjiJson_Author extends CModel {
    protected $table = 'uji_json_author';

    protected $primaryKey = 'id';

    protected $guarded = [];

    /**
     * @return CModel_Relation_HasMany
     */
    public function posts() {
        return $this->hasMany(UjiJson_Post::class, 'author_id');
    }
}

class UjiJson_Post extends CModel {
    protected $table = 'uji_json_post';

    protected $primaryKey = 'id';

    protected $guarded = [];
}

class UjiJson_AuthorResource extends CHTTP_Resources_Json_JsonResource {
    public function toArray(CHTTP_Request $request) {
        return [
            'id' => $this->id,
            'posts' => UjiJson_PostResource::collection($this->whenLoaded('posts')),
            'posts_count' => $this->whenCounted('posts'),
        ];
    }
}

class UjiJson_PostCollection extends CHTTP_Resources_Json_ResourceCollection {
    public $collects = UjiJson_PostResource::class;

    public function toArray(CHTTP_Request $request) {
        return ['posts' => $this->collection, 'total' => $this->collection->count()];
    }
}

/**
 * Port tests/Http/Resources hulu: JsonResource, ResourceCollection, pembungkus data, atribut kondisional,
 * whenLoaded/whenCounted, paginasi meta/links, ArrayAccess/delegasi.
 */
class JsonResourceTest extends TestCase {
    /** @var CHTTP_Request */
    protected $request;

    protected function setUp(): void {
        $this->request = CHTTP_Request::create('/uji', 'GET');
        CHTTP_Resources_Json_JsonResource::wrap('data');
    }

    protected function tearDown(): void {
        CHTTP_Resources_Json_JsonResource::wrap('data');
    }

    /**
     * @param CInterface_Responsable $resource
     *
     * @return array [status, decoded json, response]
     */
    protected function respond($resource) {
        $response = $resource->toResponse($this->request);
        $this->assertInstanceOf(CHTTP_JsonResponse::class, $response);

        return [$response->getStatusCode(), json_decode($response->getContent(), true), $response];
    }

    public function testResourceFromArrayIsWrappedInData() {
        list($status, $json) = $this->respond(new UjiJson_PostResource(new UjiJson_Post(['id' => 5, 'title' => 'Halo'])));
        $this->assertSame(200, $status);
        $this->assertSame(['data' => ['id' => 5, 'title' => 'Halo', 'custom' => true]], $json);
    }

    public function testDefaultToArrayUsesTheUnderlyingArrayOrModel() {
        $plain = new CHTTP_Resources_Json_JsonResource(['a' => 1]);
        $this->assertSame(['a' => 1], $plain->resolve($this->request));
        $model = new UjiJson_Post(['id' => 1, 'title' => 'T']);
        $this->assertSame(['id' => 1, 'title' => 'T'], (new CHTTP_Resources_Json_JsonResource($model))->resolve($this->request));
        $this->assertSame([], (new CHTTP_Resources_Json_JsonResource(null))->resolve($this->request), 'resource null → array kosong');
    }

    public function testMakeAndDelegationToTheUnderlyingResource() {
        $resource = UjiJson_PostResource::make(new UjiJson_Post(['id' => 7, 'title' => 'Judul']));
        $this->assertSame(7, $resource->id, '__get diteruskan ke resource');
        $this->assertTrue(isset($resource['title']), 'ArrayAccess diteruskan');
        $this->assertSame('Judul', $resource['title']);
        $model = new UjiJson_Post(['id' => 3]);
        $wrapped = new UjiJson_PostResource($model);
        $this->assertSame(3, $wrapped->getKey(), '__call diteruskan ke model');
        $this->assertSame(3, $wrapped->getRouteKey());
    }

    public function testWithoutWrappingAndCustomWrapper() {
        CHTTP_Resources_Json_JsonResource::withoutWrapping();
        list($status, $json) = $this->respond(new UjiJson_PostResource(new UjiJson_Post(['id' => 1, 'title' => 'x'])));
        $this->assertSame(['id' => 1, 'title' => 'x', 'custom' => true], $json);

        CHTTP_Resources_Json_JsonResource::wrap('post');
        list($status, $json) = $this->respond(new UjiJson_PostResource(new UjiJson_Post(['id' => 1, 'title' => 'x'])));
        $this->assertArrayHasKey('post', $json);
    }

    public function testDataAlreadyWrappedIsNotWrappedTwice() {
        list($status, $json) = $this->respond(new CHTTP_Resources_Json_JsonResource(['data' => ['id' => 1]]));
        $this->assertSame(['data' => ['id' => 1]], $json);
    }

    public function testWithAndAdditionalAreMergedOutsideTheWrapper() {
        $resource = (new UjiJson_WithResource(new UjiJson_Post(['id' => 1])))->additional(['status' => 'ok']);
        list($status, $json, $response) = $this->respond($resource);
        $this->assertSame(['data' => ['id' => 1], 'meta' => ['versi' => 2], 'status' => 'ok'], $json);
        $this->assertSame('resource', $response->headers->get('X-Uji'), 'withResponse() dipanggil');
        $this->assertInstanceOf(UjiJson_Post::class, $response->original);
    }

    public function testUnwrappedDataWithAdditionalIsForcedIntoTheWrapper() {
        CHTTP_Resources_Json_JsonResource::withoutWrapping();
        $resource = (new UjiJson_PostResource(new UjiJson_Post(['id' => 1, 'title' => 'x'])))->additional(['extra' => 1]);
        list($status, $json) = $this->respond($resource);
        $this->assertSame(['data' => ['id' => 1, 'title' => 'x', 'custom' => true], 'extra' => 1], $json, 'tanpa pembungkus tapi ada additional → dibungkus data supaya tidak bertabrakan');
    }

    public function testConditionalAttributes() {
        $admin = new UjiJson_ConditionalResource(new UjiJson_Post(['id' => 1, 'is_admin' => true, 'maybe' => null, 'email' => 'a@b.c', 'title' => 'T']));
        $data = $admin->resolve($this->request);
        $this->assertSame('rahasia', $data['secret']);
        $this->assertSame('default', $data['fallback']);
        $this->assertNull($data['null_only'], 'whenNull: nilai null dikembalikan apa adanya saat null');
        $this->assertArrayNotHasKey('not_null', $data, 'whenNotNull tanpa default → hilang');
        $this->assertSame('a@b.c', $data['has_email'], 'whenHas tanpa value → nilai atribut');
        $this->assertSame('ada', $data['has_email_value']);
        $this->assertArrayNotHasKey('unless', $data);
        $this->assertSame('admin', $data['role']);
        $this->assertSame(9, $data['level']);
        $this->assertSame(1, $data['always']);
        $this->assertSame('-', $data['transformed']);
        $this->assertSame('T', $data['title'], 'attributes() adalah MergeValue: kunci yang diminta digabung ke induk');
        $this->assertSame('a@b.c', $data['email']);

        $user = new UjiJson_ConditionalResource(new UjiJson_Post(['id' => 2, 'is_admin' => false, 'maybe' => 'isi']));
        $data = $user->resolve($this->request);
        $this->assertArrayNotHasKey('secret', $data);
        $this->assertSame('kosong', $data['null_only'], 'whenNull: default saat tidak null');
        $this->assertSame('isi', $data['not_null']);
        $this->assertArrayNotHasKey('has_email', $data);
        $this->assertSame('tidak', $data['has_email_value']);
        $this->assertSame('bukan-admin', $data['unless']);
        $this->assertSame('user', $data['role']);
        $this->assertArrayNotHasKey('level', $data);
        $this->assertSame('ISI', $data['transformed']);
    }

    public function testMergeValueKeepsNumericKeysSequential() {
        $resource = new class([]) extends CHTTP_Resources_Json_JsonResource {
            public function toArray(CHTTP_Request $request) {
                return ['a', $this->merge(['b', 'c']), 'd', $this->when(false, 'e'), 'f'];
            }
        };
        $this->assertSame(['a', 'b', 'c', 'd', 'f'], $resource->resolve($this->request));
    }

    public function testMissingValueHelpers() {
        $missing = new CHTTP_Resources_MissingValue();
        $this->assertTrue($missing->isMissing());
        $merge = new CHTTP_Resources_MergeValue(['a' => 1]);
        $this->assertSame(['a' => 1], $merge->data);
        $this->assertSame(['x'], (new CHTTP_Resources_MergeValue(c::collect(['x'])))->data, 'koleksi diubah ke array');
    }

    public function testWhenLoadedAndWhenCountedFollowTheModelState() {
        $author = new UjiJson_Author(['id' => 1]);
        $data = (new UjiJson_AuthorResource($author))->resolve($this->request);
        $this->assertSame(['id' => 1], $data, 'relasi belum dimuat → kunci hilang');

        $author->setRelation('posts', new CModel_Collection([new UjiJson_Post(['id' => 10, 'title' => 'A']), new UjiJson_Post(['id' => 11, 'title' => 'B'])]));
        $author->setAttribute('posts_count', 2);
        $data = json_decode((new UjiJson_AuthorResource($author))->toJson(), true);
        $this->assertCount(2, $data['posts']);
        $this->assertSame(['id' => 10, 'title' => 'A', 'custom' => true], $data['posts'][0], 'resource bertingkat ikut terserialisasi');
        $this->assertSame(2, $data['posts_count']);
    }

    public function testWhenLoadedWithNullRelationReturnsNull() {
        $author = new UjiJson_Author(['id' => 1]);
        $author->setRelation('posts', null);
        $resource = new class($author) extends CHTTP_Resources_Json_JsonResource {
            public function toArray(CHTTP_Request $request) {
                return ['posts' => $this->whenLoaded('posts'), 'alt' => $this->whenLoaded('posts', 'ada', 'tidak')];
            }
        };
        $this->assertSame(['posts' => null, 'alt' => null], $resource->resolve($this->request), 'relasi dimuat tapi null → null, default hanya dipakai bila belum dimuat');
    }

    public function testAnonymousCollectionWrapsEachItem() {
        $collection = UjiJson_PostResource::collection([new UjiJson_Post(['id' => 1, 'title' => 'a']), new UjiJson_Post(['id' => 2, 'title' => 'b'])]);
        $this->assertInstanceOf(CHTTP_Resources_Json_AnonymousResourceCollection::class, $collection);
        $this->assertSame(2, $collection->count());
        list($status, $json) = $this->respond($collection);
        $this->assertSame(['data' => [['id' => 1, 'title' => 'a', 'custom' => true], ['id' => 2, 'title' => 'b', 'custom' => true]]], $json);
    }

    public function testCollectionFromCModelCollectionAndIteration() {
        $models = new CModel_Collection([new UjiJson_Post(['id' => 1, 'title' => 'a'])]);
        $collection = UjiJson_PostResource::collection($models);
        foreach ($collection as $item) {
            $this->assertInstanceOf(UjiJson_PostResource::class, $item);
        }
        $this->assertSame([['id' => 1, 'title' => 'a', 'custom' => true]], $collection->resolve($this->request));
    }

    public function testCustomResourceCollectionUsesCollectsAndItsOwnShape() {
        $collection = new UjiJson_PostCollection([new UjiJson_Post(['id' => 1, 'title' => 'a']), new UjiJson_Post(['id' => 2, 'title' => 'b'])]);
        list($status, $json) = $this->respond($collection);
        $this->assertSame(2, $json['data']['total']);
        $this->assertSame('a', $json['data']['posts'][0]['title']);
        $this->assertTrue($json['data']['posts'][0]['custom'], 'tiap item lewat UjiJson_PostResource');
    }

    public function testCollectsIsGuessedFromTheClassName() {
        $collection = new class([new UjiJson_Post(['id' => 9, 'title' => 'z'])]) extends CHTTP_Resources_Json_ResourceCollection {
            public $collects = 'UjiJson_PostResource';
        };
        $this->assertSame([['id' => 9, 'title' => 'z', 'custom' => true]], $collection->resolve($this->request));
    }

    public function testPaginatedCollectionAddsLinksAndMeta() {
        $paginator = new CPagination_LengthAwarePaginator([new UjiJson_Post(['id' => 3, 'title' => 'c']), new UjiJson_Post(['id' => 4, 'title' => 'd'])], 10, 2, 2, ['path' => 'http://uji.test/posts']);
        $collection = UjiJson_PostResource::collection($paginator);
        list($status, $json) = $this->respond($collection);
        $this->assertCount(2, $json['data']);
        $this->assertSame('http://uji.test/posts?page=1', $json['links']['first']);
        $this->assertSame('http://uji.test/posts?page=5', $json['links']['last']);
        $this->assertSame('http://uji.test/posts?page=1', $json['links']['prev']);
        $this->assertSame('http://uji.test/posts?page=3', $json['links']['next']);
        $this->assertSame(2, $json['meta']['current_page']);
        $this->assertSame(10, $json['meta']['total']);
        $this->assertSame(2, $json['meta']['per_page']);
        $this->assertSame(5, $json['meta']['last_page']);
        $this->assertSame(3, $json['meta']['from']);
        $this->assertSame(4, $json['meta']['to']);
        $this->assertArrayNotHasKey('data', $json['meta']);
    }

    public function testPaginatedCollectionPreservesQueryAndAdditional() {
        $paginator = new CPagination_LengthAwarePaginator([new UjiJson_Post(['id' => 1, 'title' => 'a'])], 3, 1, 1, ['path' => 'http://uji.test/posts']);
        $collection = UjiJson_PostResource::collection($paginator)->withQuery(['sort' => 'judul'])->additional(['status' => 'ok']);
        list($status, $json) = $this->respond($collection);
        $this->assertStringContainsString('sort=judul', $json['links']['next']);
        $this->assertSame('ok', $json['status']);
    }

    public function testSimplePaginatorHasNoTotalMeta() {
        $paginator = new CPagination_Paginator([new UjiJson_Post(['id' => 1, 'title' => 'a']), new UjiJson_Post(['id' => 2, 'title' => 'b'])], 1, 1, ['path' => 'http://uji.test/posts']);
        list($status, $json) = $this->respond(UjiJson_PostResource::collection($paginator));
        $this->assertCount(1, $json['data'], 'Paginator sederhana memotong ke per_page');
        $this->assertArrayNotHasKey('total', $json['meta']);
        $this->assertNull($json['links']['last']);
        $this->assertSame('http://uji.test/posts?page=2', $json['links']['next']);
    }

    public function testResponseStatusIs201ForRecentlyCreatedModels() {
        $model = new UjiJson_Post(['id' => 1, 'title' => 'baru']);
        $model->wasRecentlyCreated = true;
        list($status) = $this->respond(new UjiJson_PostResource($model));
        $this->assertSame(201, $status);
        $model->wasRecentlyCreated = false;
        list($status) = $this->respond(new UjiJson_PostResource($model));
        $this->assertSame(200, $status);
    }

    public function testJsonSerializeAndToJson() {
        $resource = new UjiJson_PostResource(new UjiJson_Post(['id' => 1, 'title' => 'a']));
        $this->assertSame(['id' => 1, 'title' => 'a', 'custom' => true], $resource->jsonSerialize());
        $this->assertSame('{"id":1,"title":"a","custom":true}', $resource->toJson());
        $this->assertSame(json_encode(['id' => 1, 'title' => 'a', 'custom' => true], JSON_PRETTY_PRINT), $resource->toJson(JSON_PRETTY_PRINT));
    }

    public function testResponseHelperReturnsJsonResponse() {
        $response = (new UjiJson_PostResource(new UjiJson_Post(['id' => 1, 'title' => 'a'])))->response($this->request);
        $this->assertInstanceOf(CHTTP_JsonResponse::class, $response);
        $this->assertSame(['data' => ['id' => 1, 'title' => 'a', 'custom' => true]], json_decode($response->getContent(), true));
    }

    public function testPreserveKeysOnCollections() {
        $resource = new class(null) extends CHTTP_Resources_Json_JsonResource {
            public $preserveKeys = true;

            public function toArray(CHTTP_Request $request) {
                return ['id' => $this->id];
            }
        };
        $collection = $resource::collection(c::collect([5 => new UjiJson_Post(['id' => 1]), 9 => new UjiJson_Post(['id' => 2])]));
        $this->assertSame([5 => ['id' => 1], 9 => ['id' => 2]], json_decode($collection->toJson(), true), 'kunci numerik koleksi dipertahankan');
        $this->assertSame([['id' => 1, 'title' => 'a', 'custom' => true]], json_decode(UjiJson_PostResource::collection(c::collect([7 => new UjiJson_Post(['id' => 1, 'title' => 'a'])]))->toJson(), true), 'default: kunci numerik diurutkan ulang');
        $this->assertSame(['k' => ['id' => 1, 'title' => 'a', 'custom' => true]], json_decode(UjiJson_PostResource::collection(c::collect(['k' => new UjiJson_Post(['id' => 1, 'title' => 'a'])]))->toJson(), true), 'kunci string selalu dipertahankan');
    }
}
