<?php

/**
 * Move.
 */
class CModel_Nested_Move {
    /**
     * Node on which the move operation will be performed.
     *
     * @var CModel
     */
    protected $node = null;

    /**
     * Destination node.
     *
     * @var CModel|int
     */
    protected $target = null;

    /**
     * Move target position, one of: child, left, right, root.
     *
     * @var string
     */
    protected $position = null;

    /**
     * Memoized 1st boundary.
     *
     * @var int
     */
    protected $bound1 = null;

    /**
     * Memoized 2nd boundary.
     *
     * @var int
     */
    protected $bound2 = null;

    /**
     * Memoized boundaries array.
     *
     * @var array
     */
    protected $boundaries = null;

    /**
     * The event dispatcher instance.
     *
     * @var \CEvent_DispatcherInterface
     */
    protected static $dispatcher;

    /**
     * Create a new Move class instance.
     *
     * @param CModel     $node
     * @param CModel|int $target
     * @param string     $position
     *
     * @return void
     */
    public function __construct($node, $target, $position) {
        $this->node = $node;
        $this->target = $this->resolveNode($target);
        $this->position = $position;

        $this->setEventDispatcher($node->getEventDispatcher());
    }

    /**
     * Easy static accessor for performing a move operation.
     *
     * @param CModel     $node
     * @param CModel|int $target
     * @param string     $position
     *
     * @return CModel
     */
    public static function to($node, $target, $position) {
        $instance = new static($node, $target, $position);

        return $instance->perform();
    }

    /**
     * Perform the move operation.
     *
     * @return \Baum\Node
     */
    public function perform() {
        $this->guardAgainstImpossibleMove();

        if ($this->fireMoveEvent('moving') === false) {
            return $this->node;
        }

        if ($this->hasChange()) {
            $self = $this;

            $this->node->getConnection()->transaction(function () use ($self) {
                $self->updateStructure();
            });

            $this->target->reload();

            $this->node->setDepthWithSubtree();

            $this->node->reload();
        }

        $this->fireMoveEvent('moved', false);

        return $this->node;
    }

    /**
     * Runs the SQL query associated with the update of the indexes affected
     * by the move operation.
     *
     * @return int
     */
    public function updateStructure() {
        list($a, $b, $c, $d) = $this->boundaries();

        // select the rows between the leftmost & the rightmost boundaries and apply a lock
        $this->applyLockBetween($a, $d);

        $connection = $this->node->getConnection();
        $grammar = $connection->getQueryGrammar();

        $currentId = $this->quoteIdentifier($this->node->getKey());
        $parentId = $this->quoteIdentifier($this->parentId());
        $leftColumn = $this->node->getLeftColumnName();
        $rightColumn = $this->node->getRightColumnName();
        $parentColumn = $this->node->getParentColumnName();
        $wrappedLeft = $grammar->wrap($leftColumn);
        $wrappedRight = $grammar->wrap($rightColumn);
        $wrappedParent = $grammar->wrap($parentColumn);
        $wrappedId = $grammar->wrap($this->node->getKeyName());

        $lftSql = "CASE
      WHEN ${wrappedLeft} BETWEEN ${a} AND ${b} THEN ${wrappedLeft} + ${d} - ${b}
      WHEN ${wrappedLeft} BETWEEN ${c} AND ${d} THEN ${wrappedLeft} + ${a} - ${c}
      ELSE ${wrappedLeft} END";

        $rgtSql = "CASE
      WHEN ${wrappedRight} BETWEEN ${a} AND ${b} THEN ${wrappedRight} + ${d} - ${b}
      WHEN ${wrappedRight} BETWEEN ${c} AND ${d} THEN ${wrappedRight} + ${a} - ${c}
      ELSE ${wrappedRight} END";

        $parentSql = "CASE
      WHEN ${wrappedId} = ${currentId} THEN ${parentId}
      ELSE ${wrappedParent} END";

        $updateConditions = [
            $leftColumn => $connection->raw($lftSql),
            $rightColumn => $connection->raw($rgtSql),
            $parentColumn => $connection->raw($parentSql)
        ];

        if ($this->node->timestamps) {
            $updateConditions[$this->node->getUpdatedAtColumn()] = $this->node->freshTimestamp();
        }

        return $this->node
            ->newNestedSetQuery()
            ->where(function ($query) use ($leftColumn, $rightColumn, $a, $d) {
                $query->whereBetween($leftColumn, [$a, $d])
                    ->orWhereBetween($rightColumn, [$a, $d]);
            })
            ->update($updateConditions);
    }

    /**
     * Resolves suplied node. Basically returns the node unchanged if
     * supplied parameter is an instance of \Baum\Node. Otherwise it will try
     * to find the node in the database.
     *
     * @param mixed $node
     *
     * @return \Baum\Node
     */
    protected function resolveNode($node) {
        if (c::hasTrait($node, 'CModel_Nested_NestedTrait')) {
            return $node->reload();
        }

        return $this->node->newNestedSetQuery()->find($node);
    }

    /**
     * Check wether the current move is possible and if not, rais an exception.
     *
     * @return void
     */
    protected function guardAgainstImpossibleMove() {
        if (!$this->node->exists) {
            throw new CModel_Nested_Exception_MoveNotPossibleException('A new node cannot be moved.');
        }
        if (array_search($this->position, ['child', 'left', 'right', 'root']) === false) {
            throw new CModel_Nested_Exception_MoveNotPossibleException("Position should be one of ['child', 'left', 'right'] but is {$this->position}.");
        }
        if (!$this->promotingToRoot()) {
            if (is_null($this->target)) {
                if ($this->position === 'left' || $this->position === 'right') {
                    throw new CModel_Nested_Exception_MoveNotPossibleException("Could not resolve target node. This node cannot move any further to the {$this->position}.");
                } else {
                    throw new CModel_Nested_Exception_MoveNotPossibleException('Could not resolve target node.');
                }
            }

            if ($this->node->equals($this->target)) {
                throw new CModel_Nested_Exception_MoveNotPossibleException('A node cannot be moved to itself.');
            }
            if ($this->target->insideSubtree($this->node)) {
                throw new CModel_Nested_Exception_MoveNotPossibleException('A node cannot be moved to a descendant of itself (inside moved tree).');
            }
            if (!$this->node->inSameScope($this->target)) {
                throw new CModel_Nested_Exception_MoveNotPossibleException('A node cannot be moved to a different scope.');
            }
        }
    }

    /**
     * Computes the boundary.
     *
     * @return int
     */
    protected function bound1() {
        if (!is_null($this->bound1)) {
            return $this->bound1;
        }

        switch ($this->position) {
            case 'child':
                $this->bound1 = $this->target->getRight();

                break;

            case 'left':
                $this->bound1 = $this->target->getLeft();

                break;

            case 'right':
                $this->bound1 = $this->target->getRight() + 1;

                break;

            case 'root':
                $this->bound1 = $this->node->newNestedSetQuery()->max($this->node->getRightColumnName()) + 1;

                break;
        }

        $this->bound1 = (($this->bound1 > $this->node->getRight()) ? $this->bound1 - 1 : $this->bound1);

        return $this->bound1;
    }

    /**
     * Computes the other boundary.
     * TODO: Maybe find a better name for this... ¿?
     *
     * @return int
     */
    protected function bound2() {
        if (!is_null($this->bound2)) {
            return $this->bound2;
        }

        $this->bound2 = (($this->bound1() > $this->node->getRight()) ? $this->node->getRight() + 1 : $this->node->getLeft() - 1);

        return $this->bound2;
    }

    /**
     * Computes the boundaries array.
     *
     * @return array
     */
    protected function boundaries() {
        if (!is_null($this->boundaries)) {
            return $this->boundaries;
        }

        // we have defined the boundaries of two non-overlapping intervals,
        // so sorting puts both the intervals and their boundaries in order
        $this->boundaries = [
            $this->node->getLeft(),
            $this->node->getRight(),
            $this->bound1(),
            $this->bound2()
        ];
        sort($this->boundaries);

        return $this->boundaries;
    }

    /**
     * Computes the new parent id for the node being moved.
     *
     * @return int
     */
    protected function parentId() {
        switch ($this->position) {
            case 'root':
                return null;
            case 'child':
                return $this->target->getKey();
            default:
                return $this->target->getParentId();
        }
    }

    /**
     * Check wether there should be changes in the downward tree structure.
     *
     * @return bool
     */
    protected function hasChange() {
        return !($this->bound1() == $this->node->getRight() || $this->bound1() == $this->node->getLeft());
    }

    /**
     * Check if we are promoting the provided instance to a root node.
     *
     * @return bool
     */
    protected function promotingToRoot() {
        return $this->position == 'root';
    }

    /**
     * Get the event dispatcher instance.
     *
     * @return \CEvent_DispatcherInterface
     */
    public static function getEventDispatcher() {
        return static::$dispatcher;
    }

    /**
     * Set the event dispatcher instance.
     *
     * @param  CEvent_Dispatcher
     *
     * @return void
     */
    public static function setEventDispatcher(CEvent_Dispatcher $dispatcher) {
        static::$dispatcher = $dispatcher;
    }

    /**
     * Fire the given move event for the model.
     *
     * @param string $event
     * @param bool   $halt
     *
     * @return mixed
     */
    protected function fireMoveEvent($event, $halt = true) {
        if (!isset(static::$dispatcher)) {
            return true;
        }

        // Basically the same as \CModel->fireModelEvent
        // but we relay the event into the node instance.
        $event = "model.{$event}: " . get_class($this->node);

        $method = $halt ? 'until' : 'fire';

        return static::$dispatcher->$method($event, $this->node);
    }

    /**
     * Quotes an identifier for being used in a database query.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function quoteIdentifier($value) {
        if (is_null($value)) {
            return 'NULL';
        }

        $connection = $this->node->getConnection();

        return $connection->escape($value);
    }

    /**
     * Applies a lock to the rows between the supplied index boundaries.
     *
     * @param int $lft
     * @param int $rgt
     *
     * @return void
     */
    protected function applyLockBetween($lft, $rgt) {
        $this->node->newQuery()
            ->where($this->node->getLeftColumnName(), '>=', $lft)
            ->where($this->node->getRightColumnName(), '<=', $rgt)
            ->select($this->node->getKeyName())
            ->lockForUpdate()
            ->get();
    }
}
