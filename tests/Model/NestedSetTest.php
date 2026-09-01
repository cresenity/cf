<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for CModel_Nested_* - a hand-modified port of
 * lazychaser/laravel-nestedset with an added `depth` column - run against a
 * REAL sqlite in-memory database rather than mocks, since the entire point
 * of a nested-set implementation is verifying actual lft/rgt/depth/parent_id
 * invariants after real tree mutations (a mocked query builder can't catch
 * an SQL-level off-by-one or a stale value left in the database).
 *
 * This sandbox's default `php` binary (8.2) has no pdo_sqlite extension, so
 * this file must be run with:
 *   php7.4 vendor/bin/phpcf test --filter=NestedSetTest
 * (php7.4 is already installed and has pdo_sqlite, and the framework's own
 * documented minimum supported version is PHP 7.4, so this is a fully
 * representative way to run it - not a workaround around unsupported code.)
 */
class NestedSetTest extends TestCase {
    /** @var CDatabase_Connection_Pdo_SqliteConnection */
    protected $connection;

    protected function setUp(): void {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped(
                'pdo_sqlite is not available for the active PHP CLI - run under a PHP binary that has it '
                . '(e.g. `php7.4 vendor/bin/phpcf test --filter=NestedSetTest`) to actually execute this suite.'
            );
        }

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->connection = new CDatabase_Connection_Pdo_SqliteConnection($pdo, '', '', ['driver' => 'sqlite']);
        $this->connection->setEventDispatcher(new CEvent_Dispatcher());

        NestedSetTestCategory::$conn = $this->connection;
        NestedSetTestSoftCategory::$conn = $this->connection;

        $schema = $this->connection->getSchemaBuilder();

        $schema->create('nestedset_categories', function (CDatabase_Schema_Blueprint $table) {
            $table->increments('nestedset_categories_id');
            $table->string('name');
            CModel_Nested_NestedSet::columns($table);
        });

        $schema->create('nestedset_soft_categories', function (CDatabase_Schema_Blueprint $table) {
            $table->increments('nestedset_soft_categories_id');
            $table->string('name');
            $table->unsignedInteger('status')->default(1);
            CModel_Nested_NestedSet::columns($table);
        });
    }

    /**
     * @return CDatabase_Query_Builder
     */
    protected function rawRows($table = 'nestedset_categories') {
        return $this->connection->table($table);
    }

    // -----------------------------------------------------------------
    // Basic tree construction
    // -----------------------------------------------------------------

    public function testCreatingRootNodeSetsCorrectBoundsAndDepth() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);

        $this->assertSame(1, $root->getLft());
        $this->assertSame(2, $root->getRgt());
        $this->assertSame(0, $root->getDepth());
        $this->assertNull($root->getParentId());
        $this->assertTrue($root->isRoot());
        $this->assertTrue($root->isLeaf());
    }

    public function testAppendToNodeSetsCorrectBoundsDepthAndParent() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $child = NestedSetTestCategory::create(['name' => 'Child'], $root);

        $this->assertSame($root->getKey(), $child->getParentId());
        $this->assertSame(1, $child->getDepth());
        $this->assertSame(2, $child->getLft());
        $this->assertSame(3, $child->getRgt());

        $root->refreshNode();
        $this->assertSame(1, $root->getLft());
        $this->assertSame(4, $root->getRgt());
        $this->assertFalse($root->isLeaf());
    }

    public function testPrependToNodePlacesBeforeExistingChildren() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $first = NestedSetTestCategory::create(['name' => 'First'], $root);
        $second = new NestedSetTestCategory(['name' => 'Second']);
        $second->prependToNode($root)->save();

        $first->refreshNode();
        $this->assertLessThan($first->getLft(), $second->getLft());
        $this->assertSame(1, $second->getDepth());
    }

    // -----------------------------------------------------------------
    // isNode() - was broken (in_array against an object-cast-to-array,
    // which checks property VALUES, never trait names) - fixed to use
    // c::hasTrait()
    // -----------------------------------------------------------------

    public function testIsNodeReturnsTrueForARealNodeInstance() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);

        $this->assertTrue(CModel_Nested_NestedSet::isNode($root));
    }

    public function testIsNodeReturnsFalseForNonObjectsAndUnrelatedObjects() {
        $this->assertFalse(CModel_Nested_NestedSet::isNode(5));
        $this->assertFalse(CModel_Nested_NestedSet::isNode('5'));
        $this->assertFalse(CModel_Nested_NestedSet::isNode(null));
        $this->assertFalse(CModel_Nested_NestedSet::isNode(new stdClass()));
    }

    public function testWhereAncestorOfAcceptsANodeInstanceDirectly() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $child = NestedSetTestCategory::create(['name' => 'Child'], $root);
        $grandchild = NestedSetTestCategory::create(['name' => 'Grandchild'], $child);

        // Before the isNode() fix, passing a node instance here fell through
        // to the "raw id" branch (the object itself would be bound as a query
        // param), so this exercises the fixed branch specifically.
        $ancestors = NestedSetTestCategory::query()->whereAncestorOf($grandchild)->defaultOrder()->get();

        $this->assertCount(2, $ancestors);
        $this->assertSame('Root', $ancestors[0]->name);
        $this->assertSame('Child', $ancestors[1]->name);
    }

    public function testWhereDescendantOfAcceptsANodeInstanceDirectly() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $child = NestedSetTestCategory::create(['name' => 'Child'], $root);
        NestedSetTestCategory::create(['name' => 'Grandchild'], $child);

        $descendants = NestedSetTestCategory::query()->whereDescendantOf($root)->defaultOrder()->get();

        $this->assertCount(2, $descendants);
        $this->assertSame('Child', $descendants[0]->name);
        $this->assertSame('Grandchild', $descendants[1]->name);
    }

    public function testAncestorsAndDescendantsRelations() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $child = NestedSetTestCategory::create(['name' => 'Child'], $root);
        $grandchild = NestedSetTestCategory::create(['name' => 'Grandchild'], $child);

        $this->assertCount(2, $grandchild->ancestors()->get());
        $this->assertCount(2, $root->descendants()->get());
    }

    // -----------------------------------------------------------------
    // Depth cascade on move - actionAppendOrPrepend always cascaded via
    // setDepthWithSubtree(); actionBeforeOrAfter() and actionRoot() did NOT
    // until this session's fix, so moving a subtree via beforeNode()/
    // afterNode()/makeRoot() used to leave every descendant's `depth`
    // column stale.
    // -----------------------------------------------------------------

    public function testAppendToNodeCascadesDepthToDescendantsOfMovedSubtree() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $branchA = NestedSetTestCategory::create(['name' => 'BranchA'], $root);
        $branchB = NestedSetTestCategory::create(['name' => 'BranchB'], $root);
        $leaf = NestedSetTestCategory::create(['name' => 'Leaf'], $branchA);

        $this->assertSame(1, $branchA->getDepth());
        $this->assertSame(2, $leaf->getDepth());

        // Leaf's insertion shifted BranchB's bounds; refresh before reuse.
        $branchB->refreshNode();

        // Move BranchA (with its child Leaf) to be a child of BranchB.
        $branchA->appendToNode($branchB)->save();

        $branchA->refreshNode();
        $leaf->refreshNode();

        $this->assertSame(2, $branchA->getDepth());
        $this->assertSame(3, $leaf->getDepth(), 'descendant depth must cascade when the subtree moves via appendToNode()');
    }

    public function testBeforeNodeCascadesDepthToDescendantsOfMovedSubtree() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $branchA = NestedSetTestCategory::create(['name' => 'BranchA'], $root);
        $branchB = NestedSetTestCategory::create(['name' => 'BranchB'], $root);
        $leaf = NestedSetTestCategory::create(['name' => 'Leaf'], $branchA);
        $branchBChild = NestedSetTestCategory::create(['name' => 'BranchBChild'], $branchB);
        $branchB->refreshNode();

        // Move BranchA (with its child Leaf) to just before BranchB's child,
        // i.e. re-parent it one level deeper via beforeNode() rather than
        // appendToNode().
        $branchA->beforeNode($branchBChild)->save();

        $branchA->refreshNode();
        $leaf->refreshNode();

        $this->assertSame($branchB->getKey(), $branchA->getParentId());
        $this->assertSame(2, $branchA->getDepth());
        $this->assertSame(3, $leaf->getDepth(), 'descendant depth must cascade when the subtree moves via beforeNode()');
    }

    public function testAfterNodeCascadesDepthToDescendantsOfMovedSubtree() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $branchA = NestedSetTestCategory::create(['name' => 'BranchA'], $root);
        $branchB = NestedSetTestCategory::create(['name' => 'BranchB'], $root);
        $leaf = NestedSetTestCategory::create(['name' => 'Leaf'], $branchA);
        $branchBChild = NestedSetTestCategory::create(['name' => 'BranchBChild'], $branchB);
        $branchB->refreshNode();

        $branchA->afterNode($branchBChild)->save();

        $branchA->refreshNode();
        $leaf->refreshNode();

        $this->assertSame($branchB->getKey(), $branchA->getParentId());
        $this->assertSame(2, $branchA->getDepth());
        $this->assertSame(3, $leaf->getDepth(), 'descendant depth must cascade when the subtree moves via afterNode()');
    }

    public function testMakeRootOnExistingNodeWithDescendantsCascadesDepthToZero() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $branch = NestedSetTestCategory::create(['name' => 'Branch'], $root);
        $leaf = NestedSetTestCategory::create(['name' => 'Leaf'], $branch);

        $this->assertSame(1, $branch->getDepth());
        $this->assertSame(2, $leaf->getDepth());

        $branch->saveAsRoot();

        $branch->refreshNode();
        $leaf->refreshNode();

        $this->assertTrue($branch->isRoot());
        $this->assertSame(0, $branch->getDepth());
        $this->assertSame(1, $leaf->getDepth(), 'descendant depth must cascade when the subtree is promoted to root via saveAsRoot()');
    }

    public function testUpMovesNodeBeforePreviousSibling() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $first = NestedSetTestCategory::create(['name' => 'First'], $root);
        $second = NestedSetTestCategory::create(['name' => 'Second'], $root);

        $second->up();

        $ordered = NestedSetTestCategory::query()->whereDescendantOf($root)->defaultOrder()->get()->pluck('name')->all();
        $this->assertSame(['Second', 'First'], $ordered);
    }

    public function testDownMovesNodeAfterNextSibling() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $first = NestedSetTestCategory::create(['name' => 'First'], $root);
        $second = NestedSetTestCategory::create(['name' => 'Second'], $root);

        $first->down();

        $ordered = NestedSetTestCategory::query()->whereDescendantOf($root)->defaultOrder()->get()->pluck('name')->all();
        $this->assertSame(['Second', 'First'], $ordered);
    }

    public function testMovingANodeIntoItsOwnDescendantIsRejected() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $child = NestedSetTestCategory::create(['name' => 'Child'], $root);

        $this->expectException(LogicException::class);

        $root->appendToNode($child)->save();
    }

    // -----------------------------------------------------------------
    // Deleting a node - `deleted`/`restored` events were never registered
    // in bootNestedTrait(), so deleteDescendants()/restoreDescendants()
    // (both fully implemented) were unreachable dead code before this
    // session's fix: deleting a parent left every descendant row orphaned
    // in the database and never closed the lft/rgt gap.
    // -----------------------------------------------------------------

    public function testDeletingANodeAlsoDeletesItsDescendants() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $child = NestedSetTestCategory::create(['name' => 'Child'], $root);
        $grandchild = NestedSetTestCategory::create(['name' => 'Grandchild'], $child);

        $root->delete();

        $this->assertSame(0, $this->rawRows()->count());
        $this->assertNull(NestedSetTestCategory::find($child->getKey()));
        $this->assertNull(NestedSetTestCategory::find($grandchild->getKey()));
    }

    public function testDeletingANodeClosesTheGapForRemainingSiblings() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $branch = NestedSetTestCategory::create(['name' => 'Branch'], $root);
        NestedSetTestCategory::create(['name' => 'Leaf'], $branch);
        $sibling = NestedSetTestCategory::create(['name' => 'Sibling'], $root);

        $siblingLftBefore = $sibling->getLft();

        $branch->delete();

        $sibling->refreshNode();

        // Branch + its Leaf occupied 4 lft/rgt slots; deleting them should
        // shift Sibling's bounds down by that height, not leave a gap.
        $this->assertSame($siblingLftBefore - 4, $sibling->getLft());

        $root->refreshNode();
        $this->assertSame(1, $root->getLft());
        $this->assertSame(4, $root->getRgt());
    }

    public function testSoftDeletingANodeAlsoSoftDeletesItsDescendants() {
        $root = NestedSetTestSoftCategory::create(['name' => 'Root']);
        $child = NestedSetTestSoftCategory::create(['name' => 'Child'], $root);

        $root->delete();

        // Both rows still physically exist...
        $this->assertSame(2, $this->rawRows('nestedset_soft_categories')->count());
        // ...but are excluded from the default (not-deleted) scope.
        $this->assertNull(NestedSetTestSoftCategory::find($child->getKey()));
        $this->assertNotNull(NestedSetTestSoftCategory::withTrashed()->find($child->getKey()));
    }

    public function testRestoringASoftDeletedNodeAlsoRestoresItsDescendants() {
        $root = NestedSetTestSoftCategory::create(['name' => 'Root']);
        $child = NestedSetTestSoftCategory::create(['name' => 'Child'], $root);
        $root->delete();

        $root->restore();

        $this->assertNotNull(NestedSetTestSoftCategory::find($root->getKey()));
        $this->assertNotNull(NestedSetTestSoftCategory::find($child->getKey()), 'restoring a node must also restore its descendants');
    }

    // -----------------------------------------------------------------
    // fixTree() - rawNode()'s depth fallback used to derive each node's
    // depth from `static::find($parentId)->getDepth()`, a FRESH query
    // against whatever the parent's depth currently is in the database.
    // Since reorderNodes() only calls ->save() after the entire recursive
    // pass finishes, a parent's freshly-recomputed depth was never visible
    // to that lookup - children inherited the parent's stale PRE-fix depth
    // instead. Fixed by threading the freshly-computed depth down through
    // the recursion instead of re-querying mid-fix.
    // -----------------------------------------------------------------

    public function testFixTreeRecomputesDepthTopDownAcrossMultipleLevels() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $child = NestedSetTestCategory::create(['name' => 'Child'], $root);
        $grandchild = NestedSetTestCategory::create(['name' => 'Grandchild'], $child);

        // Corrupt depth only (parent_id/lft/rgt stay as-is - fixTree()
        // rebuilds lft/rgt from parent_id order and is not what we're
        // testing here). This simulates exactly the scenario fixTree()
        // exists for: a tree whose stored depth no longer matches its
        // actual structure.
        $this->rawRows()->where('nestedset_categories_id', $child->getKey())->update(['depth' => 50]);
        $this->rawRows()->where('nestedset_categories_id', $grandchild->getKey())->update(['depth' => 99]);

        NestedSetTestCategory::query()->fixTree();

        $child->refreshNode();
        $grandchild->refreshNode();

        $this->assertSame(0, $root->fresh()->getDepth());
        $this->assertSame(1, $child->getDepth(), 'child depth must be freshly recomputed, not inherited from a stale pre-fix parent depth');
        $this->assertSame(2, $grandchild->getDepth(), 'grandchild depth must be freshly recomputed top-down, not compounded from the corrupted child depth');
    }

    public function testRebuildTreeFromNestedArray() {
        NestedSetTestCategory::query()->rebuildTree([
            [
                'name' => 'Root',
                'children' => [
                    ['name' => 'Child A'],
                    [
                        'name' => 'Child B',
                        'children' => [
                            ['name' => 'Grandchild'],
                        ],
                    ],
                ],
            ],
        ]);

        $all = NestedSetTestCategory::query()->defaultOrder()->get()->keyBy('name');

        $this->assertCount(4, $all);
        $this->assertSame(0, $all['Root']->getDepth());
        $this->assertSame(1, $all['Child A']->getDepth());
        $this->assertSame(1, $all['Child B']->getDepth());
        $this->assertSame(2, $all['Grandchild']->getDepth());
        $this->assertSame($all['Child B']->getKey(), $all['Grandchild']->getParentId());
    }

    // -----------------------------------------------------------------
    // Pure in-memory logic (bounds/predicates) - no DB round trip needed
    // beyond the initial fixture, since these read already-loaded attributes.
    // -----------------------------------------------------------------

    public function testBoundsHeightAndDescendantCount() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        NestedSetTestCategory::create(['name' => 'A'], $root);
        NestedSetTestCategory::create(['name' => 'B'], $root);
        $root->refreshNode();

        $this->assertSame([1, 6], $root->getBounds());
        $this->assertSame(6, $root->getNodeHeight());
        $this->assertSame(2.0, $root->getDescendantCount());
    }

    public function testRootLeafTrunkChildPredicates() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $branch = NestedSetTestCategory::create(['name' => 'Branch'], $root);
        $leaf = NestedSetTestCategory::create(['name' => 'Leaf'], $branch);
        $branch->refreshNode();

        $this->assertTrue($root->isRoot());
        $this->assertFalse($root->isChild());
        $this->assertTrue($leaf->isLeaf());
        $this->assertTrue($branch->isTrunk());
        $this->assertFalse($root->isTrunk());
        $this->assertTrue($leaf->isChild());
    }

    public function testDescendantAncestorSiblingPredicates() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $a = NestedSetTestCategory::create(['name' => 'A'], $root);
        $b = NestedSetTestCategory::create(['name' => 'B'], $root);
        $grandchild = NestedSetTestCategory::create(['name' => 'Grandchild'], $a);

        $this->assertTrue($grandchild->isDescendantOf($root));
        $this->assertTrue($root->isAncestorOf($grandchild));
        $this->assertTrue($a->isChildOf($root));
        $this->assertTrue($a->isSiblingOf($b));
        $this->assertFalse($grandchild->isSiblingOf($b));
    }

    // -----------------------------------------------------------------
    // Collection tree-building helpers
    // -----------------------------------------------------------------

    public function testCollectionToTreeBuildsChildrenRelation() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $a = NestedSetTestCategory::create(['name' => 'A'], $root);
        NestedSetTestCategory::create(['name' => 'B'], $a);

        $tree = NestedSetTestCategory::query()->defaultOrder()->get()->toTree();

        $this->assertCount(1, $tree);
        $treeRoot = $tree->first();
        $this->assertSame('Root', $treeRoot->name);
        $this->assertCount(1, $treeRoot->children);
        $this->assertCount(1, $treeRoot->children->first()->children);
    }

    public function testCollectionToFlatTreePreservesDepthFirstOrder() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $a = NestedSetTestCategory::create(['name' => 'A'], $root);
        NestedSetTestCategory::create(['name' => 'A1'], $a);
        NestedSetTestCategory::create(['name' => 'B'], $root);

        $flat = NestedSetTestCategory::query()->defaultOrder()->get()->toFlatTree();

        $this->assertSame(['Root', 'A', 'A1', 'B'], $flat->pluck('name')->all());
    }

    // -----------------------------------------------------------------
    // Whole-tree integrity after a mixed sequence of moves - added for the
    // smartfield panel SPA's Role drag-and-drop reorder (SF-14021). The
    // tests above each verify one operation in isolation; a real drag
    // session fires several moves back-to-back, so this asserts the
    // lft/rgt/parent_id/depth invariants still hold (no gaps, no overlaps,
    // every node's bounds nested correctly inside its parent's) after a
    // sequence combining reparent + reorder + promote-to-root.
    // -----------------------------------------------------------------

    /**
     * Every node's [lft, rgt] must fall strictly inside its parent's, no two
     * siblings' ranges may overlap, and every integer in [1, 2N] must be used
     * exactly once across the whole tree (N = node count).
     *
     * @return void
     */
    protected function assertNestedSetIsInternallyConsistent() {
        $all = $this->rawRows()->orderBy('lft')->get();
        $byId = [];
        foreach ($all as $row) {
            $byId[$row->nestedset_categories_id] = $row;
        }

        $seen = [];
        foreach ($all as $row) {
            $this->assertLessThan($row->rgt, $row->lft, "node {$row->name}: lft must be < rgt");
            foreach ([$row->lft, $row->rgt] as $bound) {
                $this->assertArrayNotHasKey($bound, $seen, "bound {$bound} used more than once (overlap/gap) - offending node {$row->name}");
                $seen[$bound] = $row->name;
            }

            if ($row->parent_id !== null) {
                $this->assertArrayHasKey($row->parent_id, $byId, "node {$row->name} references a parent_id that doesn't exist");
                $parent = $byId[$row->parent_id];
                $this->assertGreaterThan($parent->lft, $row->lft, "{$row->name}'s lft must be inside parent {$parent->name}'s bounds");
                $this->assertLessThan($parent->rgt, $row->rgt, "{$row->name}'s rgt must be inside parent {$parent->name}'s bounds");
                $this->assertSame((int) $parent->depth + 1, (int) $row->depth, "{$row->name}'s depth must be exactly one more than its parent {$parent->name}'s");
            } else {
                $this->assertSame(0, (int) $row->depth, "root node {$row->name} must have depth 0");
            }
        }

        $count = count($all);
        $expectedBounds = range(1, $count * 2);
        $seenKeys = array_keys($seen);
        sort($seenKeys);
        $this->assertSame($expectedBounds, $seenKeys, 'lft/rgt values across the whole tree must be exactly 1..2N with no gaps');
    }

    public function testTreeStaysConsistentAfterAMixedSequenceOfMoves() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $branchA = NestedSetTestCategory::create(['name' => 'BranchA'], $root);
        $branchB = NestedSetTestCategory::create(['name' => 'BranchB'], $root);
        $a1 = NestedSetTestCategory::create(['name' => 'A1'], $branchA);
        $a2 = NestedSetTestCategory::create(['name' => 'A2'], $branchA);
        $b1 = NestedSetTestCategory::create(['name' => 'B1'], $branchB);
        $this->assertNestedSetIsInternallyConsistent();

        // 1. Reorder siblings within BranchA (A2 before A1).
        $a2->refreshNode();
        $a1->refreshNode();
        $a2->beforeNode($a1)->save();
        $this->assertNestedSetIsInternallyConsistent();

        // 2. Reparent A1 (a leaf) under BranchB, appended as last child.
        $a1->refreshNode();
        $branchB->refreshNode();
        $a1->appendToNode($branchB)->save();
        $this->assertNestedSetIsInternallyConsistent();

        // 3. Reparent BranchA (now containing only A2) to be a child of B1.
        $branchA->refreshNode();
        $b1->refreshNode();
        $branchA->appendToNode($b1)->save();
        $this->assertNestedSetIsInternallyConsistent();

        // 4. Promote B1's subtree (BranchA + A2 inside it) back to root level.
        $b1->refreshNode();
        $b1->saveAsRoot();
        $this->assertNestedSetIsInternallyConsistent();

        // Final structural spot-check on top of the generic integrity sweep.
        $branchA->refreshNode();
        $a2->refreshNode();
        $b1->refreshNode();
        $this->assertSame($b1->getKey(), $branchA->getParentId(), 'BranchA should still be nested under B1 after B1 was promoted to root');
        $this->assertSame($branchA->getKey(), $a2->getParentId(), 'A2 should still be nested under BranchA throughout');
        $this->assertSame(0, $b1->getDepth());
        $this->assertSame(1, $branchA->getDepth());
        $this->assertSame(2, $a2->getDepth());
    }

    public function testUpAndDownByMultiplePositionsSkipTheRightNumberOfSiblings() {
        $root = NestedSetTestCategory::create(['name' => 'Root']);
        $first = NestedSetTestCategory::create(['name' => 'First'], $root);
        $second = NestedSetTestCategory::create(['name' => 'Second'], $root);
        $third = NestedSetTestCategory::create(['name' => 'Third'], $root);
        $fourth = NestedSetTestCategory::create(['name' => 'Fourth'], $root);

        $fourth->up(3);
        $ordered = NestedSetTestCategory::query()->whereDescendantOf($root)->defaultOrder()->get()->pluck('name')->all();
        $this->assertSame(['Fourth', 'First', 'Second', 'Third'], $ordered, 'up(3) should move past 3 siblings to the front');
        $this->assertNestedSetIsInternallyConsistent();

        $fourth->refreshNode();
        $fourth->down(2);
        $ordered = NestedSetTestCategory::query()->whereDescendantOf($root)->defaultOrder()->get()->pluck('name')->all();
        $this->assertSame(['First', 'Second', 'Fourth', 'Third'], $ordered, 'down(2) should move past 2 siblings');
        $this->assertNestedSetIsInternallyConsistent();
    }
}

class NestedSetTestCategory extends CModel {
    use CModel_Nested_Trait;

    /** @var CDatabase_Connection */
    public static $conn;

    protected $table = 'nestedset_categories';

    protected $guarded = [];

    public $timestamps = false;

    // sqlite's PDO driver returns every column as a string; cast the
    // nested-set columns back to int so assertSame()-style comparisons in
    // these tests reflect real attribute types, matching how a real app
    // model would declare these.
    protected $casts = [
        'lft' => 'integer',
        'rgt' => 'integer',
        'depth' => 'integer',
        'parent_id' => 'integer',
    ];

    public static function resolveConnection($connection = null) {
        return static::$conn;
    }
}

class NestedSetTestSoftCategory extends CModel {
    use CModel_Nested_Trait;
    use CModel_SoftDelete_SoftDeleteTrait;

    /** @var CDatabase_Connection */
    public static $conn;

    protected $table = 'nestedset_soft_categories';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'lft' => 'integer',
        'rgt' => 'integer',
        'depth' => 'integer',
        'parent_id' => 'integer',
        'status' => 'integer',
    ];

    public static function resolveConnection($connection = null) {
        return static::$conn;
    }
}
