<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @template TModelClass
 *
 * @see CDatabase_Query_Builder
 * @see CModel_Query
 */
trait CDatabase_Trait_Builder {
    use CTrait_Conditionable;

    /**
     * Chunk the results of the query.
     *
     * @param int      $count
     * @param callable $callback
     *
     * @return bool
     */
    public function chunk($count, callable $callback) {
        $this->enforceOrderBy();
        $page = 1;
        do {
            // We'll execute the query for the given page and get the results. If there are
            // no results we can just break and return from here. When there are results
            // we will call the callback with the current chunk of these results here.
            $results = $this->forPage($page, $count)->get();
            $countResults = $results->count();
            if ($countResults == 0) {
                break;
            }
            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if ($callback($results, $page) === false) {
                return false;
            }
            unset($results);
            $page++;
        } while ($countResults == $count);

        return true;
    }

    /**
     * Run a map over each item while chunking.
     *
     * @template TReturn
     *
     * @param callable(TValue): TReturn $callback
     * @param int                       $count
     *
     * @return \CCollection<int, TReturn>
     */
    public function chunkMap($callback, $count = 1000) {
        $collection = new CCollection();

        $this->chunk($count, function ($items) use ($collection, $callback) {
            $items->each(function ($item) use ($collection, $callback) {
                $collection->push($callback($item));
            });
        });

        return $collection;
    }

    /**
     * Execute a callback over each item while chunking.
     *
     * @param callable $callback
     * @param int      $count
     *
     * @return bool
     */
    public function each(callable $callback, $count = 1000) {
        return $this->chunk($count, function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }
        });
    }

    /**
     * Chunk the results of a query by comparing IDs.
     *
     * @param int         $count
     * @param callable    $callback
     * @param null|string $column
     * @param null|string $alias
     *
     * @return bool
     */
    public function chunkById($count, $callback, $column = null, $alias = null) {
        return $this->orderedChunkById($count, $callback, $column, $alias);
    }

    /**
     * Chunk the results of a query by comparing IDs in descending order.
     *
     * @param int                                             $count
     * @param callable(\CCollection<int, TValue>, int): mixed $callback
     * @param null|string                                     $column
     * @param null|string                                     $alias
     *
     * @return bool
     */
    public function chunkByIdDesc($count, $callback, $column = null, $alias = null) {
        return $this->orderedChunkById($count, $callback, $column, $alias, true);
    }

    /**
     * Chunk the results of a query by comparing IDs in a given order.
     *
     * @param int                                             $count
     * @param callable(\CCollection<int, TValue>, int): mixed $callback
     * @param null|string                                     $column
     * @param null|string                                     $alias
     * @param bool                                            $descending
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public function orderedChunkById($count, $callback, $column = null, $alias = null, $descending = false) {
        $column ??= $this->defaultKeyName();

        $alias ??= $column;

        $lastId = null;

        $page = 1;

        do {
            $clone = clone $this;

            // We'll execute the query for the given page and get the results. If there are
            // no results we can just break and return from here. When there are results
            // we will call the callback with the current chunk of these results here.
            if ($descending) {
                $results = $clone->forPageBeforeId($count, $lastId, $column)->get();
            } else {
                $results = $clone->forPageAfterId($count, $lastId, $column)->get();
            }

            $countResults = $results->count();

            if ($countResults == 0) {
                break;
            }

            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if ($callback($results, $page) === false) {
                return false;
            }

            $lastId = c::get($results->last(), $alias);

            if ($lastId === null) {
                throw new RuntimeException("The chunkById operation was aborted because the [{$alias}] column is not present in the query result.");
            }

            unset($results);

            $page++;
        } while ($countResults == $count);

        return true;
    }

    /**
     * Execute a callback over each item while chunking by ID.
     *
     * @param callable    $callback
     * @param int         $count
     * @param null|string $column
     * @param null|string $alias
     *
     * @return bool
     */
    public function eachById(callable $callback, $count = 1000, $column = null, $alias = null) {
        return $this->chunkById($count, function ($results, $page) use ($callback, $count) {
            foreach ($results as $key => $value) {
                if ($callback($value, (($page - 1) * $count) + $key) === false) {
                    return false;
                }
            }
        }, $column, $alias);
    }

    /**
     * Query lazily, by chunks of the given size.
     *
     * @param int $chunkSize
     *
     * @throws \InvalidArgumentException
     *
     * @return \CCollection_LazyCollection
     */
    public function lazy($chunkSize = 1000) {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('The chunk size should be at least 1');
        }

        $this->enforceOrderBy();

        return CCollection_LazyCollection::make(function () use ($chunkSize) {
            $page = 1;

            while (true) {
                $results = $this->forPage($page++, $chunkSize)->get();

                foreach ($results as $result) {
                    yield $result;
                }

                if ($results->count() < $chunkSize) {
                    return;
                }
            }
        });
    }

    /**
     * Query lazily, by chunking the results of a query by comparing IDs.
     *
     * @param int         $chunkSize
     * @param null|string $column
     * @param null|string $alias
     *
     * @throws \InvalidArgumentException
     *
     * @return \CCollection_LazyCollection
     */
    public function lazyById($chunkSize = 1000, $column = null, $alias = null) {
        return $this->orderedLazyById($chunkSize, $column, $alias);
    }

    /**
     * Query lazily, by chunking the results of a query by comparing IDs in descending order.
     *
     * @param int         $chunkSize
     * @param null|string $column
     * @param null|string $alias
     *
     * @throws \InvalidArgumentException
     *
     * @return \CCollection_LazyCollection
     */
    public function lazyByIdDesc($chunkSize = 1000, $column = null, $alias = null) {
        return $this->orderedLazyById($chunkSize, $column, $alias, true);
    }

    /**
     * Query lazily, by chunking the results of a query by comparing IDs in a given order.
     *
     * @param int         $chunkSize
     * @param null|string $column
     * @param null|string $alias
     * @param bool        $descending
     *
     * @throws \InvalidArgumentException
     *
     * @return \CCollection_LazyCollection
     */
    protected function orderedLazyById($chunkSize = 1000, $column = null, $alias = null, $descending = false) {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('The chunk size should be at least 1');
        }

        if ($column == null) {
            $column = $this->defaultKeyName();
        }

        if ($alias == null) {
            $alias = $column;
        }

        return CCollection_LazyCollection::make(function () use ($chunkSize, $column, $alias, $descending) {
            $lastId = null;

            while (true) {
                $clone = clone $this;

                if ($descending) {
                    $results = $clone->forPageBeforeId($chunkSize, $lastId, $column)->get();
                } else {
                    $results = $clone->forPageAfterId($chunkSize, $lastId, $column)->get();
                }

                foreach ($results as $result) {
                    yield $result;
                }

                if ($results->count() < $chunkSize) {
                    return;
                }

                $lastId = $results->last()->{$alias};
            }
        });
    }

    /**
     * Execute the query and get the first result.
     *
     * @param array $columns
     *
     * @return null|CModel|object|static
     *
     * @phpstan-return TModelClass|null
     */
    public function first($columns = ['*']) {
        return $this->take(1)->get($columns)->first();
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @param array|string $columns
     * @param null|string  $message
     *
     * @throws \CDatabase_Exception_RecordNotFoundException
     *
     * @return TValue
     */
    public function firstOrFail($columns = ['*'], $message = null) {
        if (!is_null($result = $this->first($columns))) {
            return $result;
        }

        throw new CDatabase_Exception_RecordNotFoundException($message ?: 'No record found for the given query.');
    }

    /**
     * Execute the query and get the first result if it's the sole matching record.
     *
     * @param array|string $columns
     *
     * @throws \CDatabase_Exception_RecordsNotFoundException
     * @throws \CDatabase_Exception_MultipleRecordsFoundException
     *
     * @return null|\CModel|object|static
     */
    public function sole($columns = ['*']) {
        $result = $this->take(2)->get($columns);

        if ($result->isEmpty()) {
            throw new CDatabase_Exception_RecordsNotFoundException();
        }

        if ($result->count() > 1) {
            throw new CDatabase_Exception_MultipleRecordsFoundException($result->count());
        }

        return $result->first();
    }

    /**
     * Paginate the given query using a cursor paginator.
     *
     * @param int                             $perPage
     * @param array|string                    $columns
     * @param string                          $cursorName
     * @param null|\CPagination_Cursor|string $cursor
     *
     * @return \CPagination_CursorPaginatorInterface
     */
    protected function paginateUsingCursor($perPage, $columns = ['*'], $cursorName = 'cursor', $cursor = null) {
        if (!$cursor instanceof CPagination_Cursor) {
            $cursor = is_string($cursor)
                ? CPagination_Cursor::fromEncoded($cursor)
                : CPagination_CursorPaginator::resolveCurrentCursor($cursorName, $cursor);
        }

        $orders = $this->ensureOrderForCursorPagination(!is_null($cursor) && $cursor->pointsToPreviousItems());

        if (!is_null($cursor)) {
            $addCursorConditions = function (self $builder, $previousColumn, $i) use (&$addCursorConditions, $cursor, $orders) {
                $unionBuilders = isset($builder->unions) ? c::collect($builder->unions)->pluck('query') : c::collect();

                if (!is_null($previousColumn)) {
                    $originalColumn = $this->getOriginalColumnNameForCursorPagination($this, $previousColumn);

                    $builder->where(
                        cstr::contains($originalColumn, ['(', ')']) ? new CDatabase_Query_Expression($originalColumn) : $originalColumn,
                        '=',
                        $cursor->parameter($previousColumn)
                    );

                    $unionBuilders->each(function ($unionBuilder) use ($previousColumn, $cursor) {
                        $unionBuilder->where(
                            $this->getOriginalColumnNameForCursorPagination($this, $previousColumn),
                            '=',
                            $cursor->parameter($previousColumn)
                        );

                        $this->addBinding($unionBuilder->getRawBindings()['where'], 'union');
                    });
                }

                $builder->where(function (self $builder) use ($addCursorConditions, $cursor, $orders, $i, $unionBuilders) {
                    list('column' => $column, 'direction' => $direction) = $orders[$i];

                    $originalColumn = $this->getOriginalColumnNameForCursorPagination($this, $column);

                    $builder->where(
                        cstr::contains($originalColumn, ['(', ')']) ? new CDatabase_Query_Expression($originalColumn) : $originalColumn,
                        $direction === 'asc' ? '>' : '<',
                        $cursor->parameter($column)
                    );

                    if ($i < $orders->count() - 1) {
                        $builder->orWhere(function (self $builder) use ($addCursorConditions, $column, $i) {
                            $addCursorConditions($builder, $column, $i + 1);
                        });
                    }

                    $unionBuilders->each(function ($unionBuilder) use ($column, $direction, $cursor, $i, $orders, $addCursorConditions) {
                        $unionBuilder->where(function ($unionBuilder) use ($column, $direction, $cursor, $i, $orders, $addCursorConditions) {
                            $unionBuilder->where(
                                $this->getOriginalColumnNameForCursorPagination($this, $column),
                                $direction === 'asc' ? '>' : '<',
                                $cursor->parameter($column)
                            );

                            if ($i < $orders->count() - 1) {
                                $unionBuilder->orWhere(function (self $builder) use ($addCursorConditions, $column, $i) {
                                    $addCursorConditions($builder, $column, $i + 1);
                                });
                            }

                            $this->addBinding($unionBuilder->getRawBindings()['where'], 'union');
                        });
                    });
                });
            };

            $addCursorConditions($this, null, 0);
        }

        $this->limit($perPage + 1);

        return $this->cursorPaginator($this->get($columns), $perPage, $cursor, [
            'path' => CPagination_Paginator::resolveCurrentPath(),
            'cursorName' => $cursorName,
            'parameters' => $orders->pluck('column')->toArray(),
        ]);
    }

    /**
     * Get the original column name of the given column, without any aliasing.
     *
     * @param \CDatabase_Query_Builder|\CModel_Query $builder
     * @param string                                 $parameter
     *
     * @return string
     */
    protected function getOriginalColumnNameForCursorPagination($builder, string $parameter) {
        $columns = $builder instanceof CModel_Query ? $builder->getQuery()->getColumns() : $builder->getColumns();

        if (!is_null($columns)) {
            foreach ($columns as $column) {
                if (($position = strripos($column, ' as ')) !== false) {
                    $original = substr($column, 0, $position);

                    $alias = substr($column, $position + 4);

                    if ($parameter === $alias || $builder->getGrammar()->wrap($parameter) === $alias) {
                        return $original;
                    }
                }
            }
        }

        return $parameter;
    }

    /**
     * Create a new length-aware paginator instance.
     *
     * @param CCollection $items
     * @param int         $total
     * @param int         $perPage
     * @param int         $currentPage
     * @param array       $options
     *
     * @return CPagination_LengthAwarePaginator
     */
    protected function paginator($items, $total, $perPage, $currentPage, $options) {
        return CContainer::getInstance()->makeWith(CPagination_LengthAwarePaginator::class, compact(
            'items',
            'total',
            'perPage',
            'currentPage',
            'options'
        ));
    }

    /**
     * Create a new simple paginator instance.
     *
     * @param CCollection $items
     * @param int         $perPage
     * @param int         $currentPage
     * @param array       $options
     *
     * @return CPagination_Paginator
     */
    protected function simplePaginator($items, $perPage, $currentPage, $options) {
        return CContainer::getInstance()->makeWith(CPagination_Paginator::class, compact(
            'items',
            'perPage',
            'currentPage',
            'options'
        ));
    }

    /**
     * Create a new cursor paginator instance.
     *
     * @param \CCollection        $items
     * @param int                 $perPage
     * @param \CPagination_Cursor $cursor
     * @param array               $options
     *
     * @return \CPagination_CursorPaginator
     */
    protected function cursorPaginator($items, $perPage, $cursor, $options) {
        return CContainer::getInstance()->makeWith(CPagination_CursorPaginator::class, compact(
            'items',
            'perPage',
            'cursor',
            'options'
        ));
    }

    /**
     * Pass the query to a given callback.
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function tap($callback) {
        $callback($this);

        return $this;
    }
}
