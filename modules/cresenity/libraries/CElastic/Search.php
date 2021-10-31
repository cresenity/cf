<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Nov 18, 2017, 9:05:59 PM
 */
class CElastic_Search {
    protected $index;

    protected $document_type;

    /*
     * @var Elasticsearch\Client
     */
    protected $elastic;

    protected $client;

    protected $must;

    protected $must_not;

    protected $should;

    protected $select;

    protected $from;

    protected $size;

    protected $sort;

    protected $aggs;

    public function __construct(CElastic $elastic, $index, $document_type = '') {
        $this->elastic = $elastic;
        $this->client = $elastic->client();
        $this->index = $index;
        $this->document_type = $document_type;
        $this->must = [];
        $this->must_not = [];
        $this->should = [];
        $this->select = [];
        $this->from = null;
        $this->size = null;
        $this->sort = [];
        $this->aggs = [];
    }

    /**
     * @return CElastic_Indices
     */
    public function indices() {
        return $this->elastic->indices($this->index, $this->document_type);
    }

    public function must($path, $value = null) {
        $arr = [];
        if (is_array($path)) {
            $arr = $path;
        } else {
            carr::set($arr, $path, $value);
        }
        $this->must[] = $arr;
    }

    public function should($path, $value = null) {
        $arr = [];
        if (is_array($path)) {
            $arr = $path;
        } else {
            carr::set($arr, $path, $value);
        }
        $this->should[] = $arr;
    }

    public function mustNot($path, $value = null) {
        $arr = [];
        if (is_array($path)) {
            $arr = $path;
        } else {
            carr::set($arr, $path, $value);
        }
        $this->must_not[] = $arr;
    }

    public function from($from) {
        $this->from = $from;
    }

    public function size($size) {
        $this->size = $size;
    }

    public function sort($field, $mode = 'asc') {
        $arr = [];
        if (is_array($field)) {
            $arr = $field;
        } else {
            $arr = [$field => ['order' => $mode]];
        }
        $this->sort[] = $arr;
    }

    public function aggs($name, $function, $field, $size = 10, $min_doc_count = 0) {
        $this->aggs[$name] = [
            $function => [
                'field' => $field,
                'size' => $size,
            ]
        ];
        //carr::set_path($this->aggs,'filtered.filter.bool.must_not.0.exists.field','parent_id');
        if ($min_doc_count > 0) {
            $this->aggs[$name]['terms']['min_doc_count'] = $min_doc_count;
        }
    }

    public function aggsOrder($name, $order, $order_type = '', $order_mode = '') {
        if (isset($this->aggs[$name])) {
            if ($order != null) {
                $this->aggs[$name]['terms']['order'] = $order;
            }

            if (strlen($order_type) > 0 && strlen($order_mode) > 0) {
                $this->aggs[$name]['terms']['order'] = [
                    $order_type => $order_mode
                ];
            }
        }
    }

    public function subAggs($name_parent, $name, $field, $type = 'avg', $filter_field = '', $filter_value = '') {
        if (isset($this->aggs[$name_parent])) {
            $this->aggs[$name_parent]['aggs'] = [
                $name => [
                    $type => [
                        'field' => $field
                    ]
                ]
            ];
            if (strlen($filter_field) > 0 && strlen($filter_value) > 0) {
                $this->aggs[$name_parent]['aggs'][$name] = [
                    'filter' => [
                        'term' => [
                            $filter_field => $filter_value
                        ]
                    ]
                ];
            }
        }
    }

    public function buildParams() {
        $params = [];
        $params['index'] = $this->index;
        if (strlen($this->document_type) > 0) {
            $params['type'] = $this->document_type;
        }

        //build the body

        $body = [];
        if (count($this->must) > 0) {
            carr::set($body, 'query.bool.must', $this->must);
        }
        if (count($this->must_not) > 0) {
            carr::set($body, 'query.bool.must_not', $this->must_not);
        }
        if (count($this->should) > 0) {
            carr::set($body, 'query.bool.should', $this->should);
        }

        if ($this->size != null) {
            $body['size'] = $this->size;
        }
        if ($this->from != null) {
            $body['from'] = $this->from;
        }

        if (count($this->sort) > 0) {
            $body['sort'] = $this->sort;
        }
        if (count($this->aggs) > 0) {
            $body['aggs'] = $this->aggs;
        }

        $params['body'] = $body;

        return $params;
    }

    public function exec() {
        $params = $this->buildParams();
        $response = $this->client->search($params);
        $result = new CElastic_Result($response, $this->select);

        return $result;
    }

    public function select($field, $alias = null) {
        if ($alias == null) {
            $alias = $field;
        }
        $arr = ['field' => $field, 'alias' => $alias];
        $this->select[] = $arr;
    }

    public function getAlias($field) {
        foreach ($this->select as $val) {
            $fieldElastic = carr::get($val, 'field');
            if ($fieldElastic == $field) {
                return carr::get($val, 'alias');
            }
        }

        return $field;
    }

    public function getElasticField($alias) {
        foreach ($this->select as $val) {
            $fieldElastic = carr::get($val, 'alias');
            if ($fieldElastic == $alias) {
                return carr::get($val, 'field');
            }
        }

        return $alias;
    }

    public function ajaxData() {
        $data = [];
        $data['index'] = $this->index;
        $data['document_type'] = $this->document_type;
        $data['config'] = $this->elastic->config();
        $data['name'] = $this->elastic->getName();
        $data['domain'] = $this->elastic->getDomain();
        $data['must'] = $this->must;
        $data['must_not'] = $this->must_not;
        $data['should'] = $this->should;
        $data['select'] = $this->select;
        $data['from'] = $this->from;
        $data['size'] = $this->size;

        return $data;
    }
}
