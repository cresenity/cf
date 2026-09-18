<?php

/**
 * Description of log.
 */
return [
    'scout' => [
        /*
        |--------------------------------------------------------------------------
        | Default Search Engine
        |--------------------------------------------------------------------------
        |
        | This option controls the default search connection that gets used while
        | using Scout. This connection is used when syncing all models
        | to the search service. You should adjust this based on your needs.
        |
        | Supported: "algolia", "meilisearch", "collection", "null", "tntsearch"
        |
        */

        'driver' => 'tntsearch',

        /*
        |--------------------------------------------------------------------------
        | Index Prefix
        |--------------------------------------------------------------------------
        |
        | Here you may specify a prefix that will be applied to all search index
        | names used by Scout. This prefix may be useful if you have multiple
        | "tenants" or applications sharing the same search infrastructure.
        |
        */

        'prefix' => '',

        /*
        |--------------------------------------------------------------------------
        | Queue Data Syncing
        |--------------------------------------------------------------------------
        |
        | This option allows you to control if the operations that sync your data
        | with your search engines are queued. When this is set to "true" then
        | all automatic data syncing will get queued for better performance.
        |
        */

        'queue' => false,

        /*
        |--------------------------------------------------------------------------
        | Database Transactions
        |--------------------------------------------------------------------------
        |
        | This configuration option determines if your data will only be synced
        | with your search indexes after every open database transaction has
        | been committed, thus preventing any discarded data from syncing.
        |
        */

        'after_commit' => false,

        /*
        |--------------------------------------------------------------------------
        | Chunk Sizes
        |--------------------------------------------------------------------------
        |
        | These options allow you to control the maximum chunk size when you are
        | mass importing data into the search engine. This allows you to fine
        | tune each of these chunk sizes based on the power of the servers.
        |
        */

        'chunk' => [
            'searchable' => 500,
            'unsearchable' => 500,
        ],

        /*
        |--------------------------------------------------------------------------
        | Soft Deletes
        |--------------------------------------------------------------------------
        |
        | This option allows to control whether to keep soft deleted records in
        | the search indexes. Maintaining soft deleted records can be useful
        | if your application still needs to search for the records later.
        |
        */

        'soft_delete' => false,

        /*
        |--------------------------------------------------------------------------
        | Identify User
        |--------------------------------------------------------------------------
        |
        | This option allows you to control whether to notify the search engine
        | of the user performing the search. This is sometimes useful if the
        | engine supports any analytics based on this application's users.
        |
        | Supported engines: "algolia"
        |
        */

        'identify' => false,

        /*
        |--------------------------------------------------------------------------
        | Algolia Configuration
        |--------------------------------------------------------------------------
        |
        | Here you may configure your Algolia settings. Algolia is a cloud hosted
        | search engine which works great with Scout out of the box. Just plug
        | in your application ID and admin API key to get started searching.
        |
        */

        'algolia' => [
            'id' => '',
            'secret' => '',
        ],

        /*
        |--------------------------------------------------------------------------
        | MeiliSearch Configuration
        |--------------------------------------------------------------------------
        |
        | Here you may configure your MeiliSearch settings. MeiliSearch is an open
        | source search engine with minimal configuration. Below, you can state
        | the host and key information for your own MeiliSearch installation.
        |
        | See: https://docs.meilisearch.com/guides/advanced_guides/configuration.html
        |
        */

        'meilisearch' => [
            'host' => 'http://localhost:7700',
            'key' => null,
        ],

        'tntsearch' => [
            'storage' => null, //place where the index files will be stored, default is DOCROOT . 'temp/scout/{appCode}'.
            'fuzziness' => false,
            'fuzzy' => [
                'prefix_length' => 2,
                'max_expansions' => 50,
                'distance' => 2,
                'no_limit' => true
            ],
            'asYouType' => false,
            'searchBoolean' => false,
            'maxDocs' => 500,
        ],
    ],
    'metable' => [
        /*
         * Model class to use for Meta.
         */
        'model' => CModel_Metable_Meta::class,

        /*
         * List of handlers for recognized data types.
         *
         * Handlers will be evaluated in order, so a value will be handled
         * by the first appropriate handler in the list.
         */
        'datatypes' => [
            CModel_Metable_DataType_Handler_BooleanHandler::class,
            CModel_Metable_DataType_Handler_NullHandler::class,
            CModel_Metable_DataType_Handler_IntegerHandler::class,
            CModel_Metable_DataType_Handler_FloatHandler::class,
            CModel_Metable_DataType_Handler_StringHandler::class,
            CModel_Metable_DataType_Handler_DateTimeHandler::class,
            CModel_Metable_DataType_Handler_ArrayHandler::class,
            CModel_Metable_DataType_Handler_ModelHandler::class,
            CModel_Metable_DataType_Handler_ModelCollectionHandler::class,
            CModel_Metable_DataType_Handler_SerializableHandler::class,
            CModel_Metable_DataType_Handler_ObjectHandler::class,
        ],
    ],
    'array_driver' => [

    ],
    'repository' => [
        /*
        |--------------------------------------------------------------------------
        | Repository Pagination Limit Default
        |--------------------------------------------------------------------------
        |
        */
        'pagination' => [
            'limit' => 15
        ],

        /*
        |--------------------------------------------------------------------------
        | Fractal Presenter Config
        |--------------------------------------------------------------------------
        |

        Available serializers:
        ArraySerializer
        DataArraySerializer
        JsonApiSerializer

        */
        'fractal' => [
            'params' => [
                'include' => 'include'
            ],
            'serializer' => League\Fractal\Serializer\DataArraySerializer::class
        ],

        /*
        |--------------------------------------------------------------------------
        | Cache Config
        |--------------------------------------------------------------------------
        |
        */
        'cache' => [
            /*
            |--------------------------------------------------------------------------
            | Cache Status
            |--------------------------------------------------------------------------
            |
            | Enable or disable cache
            |
            */
            'enabled' => false,

            /*
            |--------------------------------------------------------------------------
            | Cache Minutes
            |--------------------------------------------------------------------------
            |
            | Time of expiration cache
            |
            */
            'minutes' => 30,

            /*
            |--------------------------------------------------------------------------
            | Cache Repository
            |--------------------------------------------------------------------------
            |
            | Instance of Illuminate\Contracts\Cache\Repository
            |
            */
            'repository' => 'cache',

            /*
            |--------------------------------------------------------------------------
            | Cache Clean Listener
            |--------------------------------------------------------------------------
            |
            |
            |
            */
            'clean' => [

                /*
                |--------------------------------------------------------------------------
                | Enable clear cache on repository changes
                |--------------------------------------------------------------------------
                |
                */
                'enabled' => true,

                /*
                |--------------------------------------------------------------------------
                | Actions in Repository
                |--------------------------------------------------------------------------
                |
                | create : Clear Cache on create Entry in repository
                | update : Clear Cache on update Entry in repository
                | delete : Clear Cache on delete Entry in repository
                |
                */
                'on' => [
                    'create' => true,
                    'update' => true,
                    'delete' => true,
                ]
            ],

            'params' => [
                /*
                |--------------------------------------------------------------------------
                | Skip Cache Params
                |--------------------------------------------------------------------------
                |
                |
                | Ex: http://prettus.local/?search=lorem&skipCache=true
                |
                */
                'skipCache' => 'skipCache'
            ],

            /*
            |--------------------------------------------------------------------------
            | Methods Allowed
            |--------------------------------------------------------------------------
            |
            | methods cacheable : all, paginate, find, findByField, findWhere, getByCriteria
            |
            | Ex:
            |
            | 'only'  =>['all','paginate'],
            |
            | or
            |
            | 'except'  =>['find'],
            */
            'allowed' => [
                'only' => null,
                'except' => null
            ]
        ],

        /*
        |--------------------------------------------------------------------------
        | Criteria Config
        |--------------------------------------------------------------------------
        |
        | Settings of request parameters names that will be used by Criteria
        |
        */
        'criteria' => [
            /*
            |--------------------------------------------------------------------------
            | Accepted Conditions
            |--------------------------------------------------------------------------
            |
            | Conditions accepted in consultations where the Criteria
            |
            | Ex:
            |
            | 'acceptedConditions'=>['=','like']
            |
            | $query->where('foo','=','bar')
            | $query->where('foo','like','bar')
            |
            */
            'acceptedConditions' => [
                '=',
                'like',
                'in'
            ],
            /*
            |--------------------------------------------------------------------------
            | Request Params
            |--------------------------------------------------------------------------
            |
            | Request parameters that will be used to filter the query in the repository
            |
            | Params :
            |
            | - search : Searched value
            |   Ex: http://prettus.local/?search=lorem
            |
            | - searchFields : Fields in which research should be carried out
            |   Ex:
            |    http://prettus.local/?search=lorem&searchFields=name;email
            |    http://prettus.local/?search=lorem&searchFields=name:like;email
            |    http://prettus.local/?search=lorem&searchFields=name:like
            |
            | - filter : Fields that must be returned to the response object
            |   Ex:
            |   http://prettus.local/?search=lorem&filter=id,name
            |
            | - orderBy : Order By
            |   Ex:
            |   http://prettus.local/?search=lorem&orderBy=id
            |
            | - sortedBy : Sort
            |   Ex:
            |   http://prettus.local/?search=lorem&orderBy=id&sortedBy=asc
            |   http://prettus.local/?search=lorem&orderBy=id&sortedBy=desc
            |
            | - searchJoin: Specifies the search method (AND / OR), by default the
            |               application searches each parameter with OR
            |   EX:
            |   http://prettus.local/?search=lorem&searchJoin=and
            |   http://prettus.local/?search=lorem&searchJoin=or
            |
            */
            'params' => [
                'search' => 'search',
                'searchFields' => 'searchFields',
                'filter' => 'filter',
                'orderBy' => 'orderBy',
                'sortedBy' => 'sortedBy',
                'with' => 'with',
                'searchJoin' => 'searchJoin',
                'withCount' => 'withCount'
            ]
        ],
        /*
        |--------------------------------------------------------------------------
        | Generator Config
        |--------------------------------------------------------------------------
        |
        */
        'generator' => [
            'basePath' => DOCROOT,
            'rootNamespace' => '\\',
            'stubsOverridePath' => DOCROOT,
            'paths' => [
                'models' => 'Entities',
                'repositories' => 'Repositories',
                'interfaces' => 'Repositories',
                'transformers' => 'Transformers',
                'presenters' => 'Presenters',
                'validators' => 'Validators',
                'controllers' => 'Http/Controllers',
                'provider' => 'RepositoryServiceProvider',
                'criteria' => 'Criteria'
            ]
        ]
    ]
];
