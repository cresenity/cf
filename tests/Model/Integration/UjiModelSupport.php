<?php

use PHPUnit\Framework\TestCase;

/**
 * Dukungan test integrasi CModel di atas SQLite in-memory: koneksi `uji_model` didaftarkan ke
 * CDatabase_Manager, skema fixture dibuat ulang tiap test, dan model fixture memakai konvensi CF
 * (kunci <tabel>_id, kolom audit created/updated, soft delete lewat status).
 */
abstract class UjiModel_IntegrationTestCase extends TestCase {
    const CONNECTION = 'uji_model';

    /**
     * @var CDatabase_Connection
     */
    protected $connection;

    protected function setUp(): void {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('butuh ekstensi pdo_sqlite');
        }
        $manager = CDatabase_Manager::instance();
        $manager->purge(static::CONNECTION);
        $manager->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], static::CONNECTION);
        $this->connection = $manager->connection(static::CONNECTION);
        $this->createSchema();
    }

    protected function tearDown(): void {
        CDatabase_Manager::instance()->purge(static::CONNECTION);
        UjiModel_User::flushEventListeners();
        UjiModel_Post::flushEventListeners();
    }

    /**
     * @return void
     */
    protected function createSchema() {
        $statements = [
            'create table uji_user (uji_user_id integer primary key autoincrement, uji_country_id integer null, name varchar(100) null, email varchar(100) null, is_active tinyint default 1, settings text null, birthday datetime null, created datetime null, createdby varchar(100) null, updated datetime null, updatedby varchar(100) null, deleted datetime null, deletedby varchar(100) null, status integer not null default 1)',
            'create table uji_country (uji_country_id integer primary key autoincrement, name varchar(100) null, created datetime null, updated datetime null, status integer not null default 1)',
            'create table uji_profile (uji_profile_id integer primary key autoincrement, uji_user_id integer null, bio text null, created datetime null, updated datetime null, status integer not null default 1)',
            'create table uji_post (uji_post_id integer primary key autoincrement, uji_user_id integer null, title varchar(200) null, body text null, meta text null, is_published tinyint default 0, published_at datetime null, price decimal(10,2) null, created datetime null, updated datetime null, deleted datetime null, deletedby varchar(100) null, status integer not null default 1)',
            'create table uji_tag (uji_tag_id integer primary key autoincrement, name varchar(100) null, created datetime null, updated datetime null, status integer not null default 1)',
            'create table post_tag (uji_post_id integer, uji_tag_id integer, note varchar(100) null, created datetime null, updated datetime null)',
            'create table uji_comment (uji_comment_id integer primary key autoincrement, commentable_type varchar(100) null, commentable_id integer null, body text null, created datetime null, updated datetime null, status integer not null default 1)',
        ];
        foreach ($statements as $statement) {
            $this->connection->statement($statement);
        }
    }

    /**
     * @param string $table
     *
     * @return CDatabase_Query_Builder
     */
    protected function table($table) {
        return $this->connection->table($table);
    }

    /**
     * @param array $attributes
     *
     * @return UjiModel_User
     */
    protected function createUser(array $attributes = []) {
        return UjiModel_User::create(array_merge(['name' => 'Budi', 'email' => 'budi@example.test'], $attributes));
    }
}

class UjiModel_Base extends CModel {
    protected $connection = UjiModel_IntegrationTestCase::CONNECTION;

    protected $guarded = [];
}

class UjiModel_Country extends UjiModel_Base {
    use CModel_SoftDelete_SoftDeleteTrait;

    protected $table = 'uji_country';

    public function users() {
        return $this->hasMany(UjiModel_User::class);
    }

    public function posts() {
        return $this->hasManyThrough(UjiModel_Post::class, UjiModel_User::class);
    }
}

class UjiModel_User extends UjiModel_Base {
    use CModel_SoftDelete_SoftDeleteTrait;
    use CModel_Deleted_DeletedTrait;

    protected $table = 'uji_user';

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'birthday' => 'datetime',
    ];

    protected $hidden = ['email'];

    protected $appends = ['display_name'];

    public function getDisplayNameAttribute() {
        return strtoupper((string) $this->name);
    }

    public function setNameAttribute($value) {
        $this->attributes['name'] = trim((string) $value);
    }

    public function country() {
        return $this->belongsTo(UjiModel_Country::class);
    }

    public function profile() {
        return $this->hasOne(UjiModel_Profile::class);
    }

    public function posts() {
        return $this->hasMany(UjiModel_Post::class);
    }

    public function scopeActive($query) {
        return $query->where('is_active', 1);
    }

    public function scopeNamed($query, $name) {
        return $query->where('name', $name);
    }
}

class UjiModel_Profile extends UjiModel_Base {
    protected $table = 'uji_profile';

    public function user() {
        return $this->belongsTo(UjiModel_User::class);
    }
}

class UjiModel_Post extends UjiModel_Base {
    use CModel_SoftDelete_SoftDeleteTrait;
    use CModel_Deleted_DeletedTrait;

    protected $table = 'uji_post';

    protected $casts = [
        'meta' => 'json',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'price' => 'decimal:2',
    ];

    public function user() {
        return $this->belongsTo(UjiModel_User::class);
    }

    public function tags() {
        return $this->belongsToMany(UjiModel_Tag::class)->withPivot('note')->withTimestamps();
    }

    public function comments() {
        return $this->morphMany(UjiModel_Comment::class, 'commentable');
    }

    public function scopePublished($query) {
        return $query->where('is_published', 1);
    }
}

class UjiModel_Tag extends UjiModel_Base {
    protected $table = 'uji_tag';

    public function posts() {
        return $this->belongsToMany(UjiModel_Post::class)->withPivot('note');
    }
}

class UjiModel_Comment extends UjiModel_Base {
    protected $table = 'uji_comment';

    public function commentable() {
        return $this->morphTo();
    }
}
