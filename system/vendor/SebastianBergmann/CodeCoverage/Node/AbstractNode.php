<?php
/*
 * This file is part of phpunit/php-code-coverage.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace SebastianBergmann\CodeCoverage\Node;

use const DIRECTORY_SEPARATOR;
use function array_merge;
use function str_replace;
use function substr;
use Countable;
use SebastianBergmann\CodeCoverage\Percentage;
use SebastianBergmann\LinesOfCode\LinesOfCode;

/**
 * @internal This class is not covered by the backward compatibility promise for phpunit/php-code-coverage
 */
abstract class AbstractNode implements Countable
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $pathAsString;

    /**
     * @var array
     */
    private $pathAsArray;

    /**
     * @var AbstractNode
     */
    private $parent;

    /**
     * @var string
     */
    private $id;

    public function __construct($name, ?self $parent = null)
    {
        if (substr($name, -1) === DIRECTORY_SEPARATOR) {
            $name = substr($name, 0, -1);
        }

        $this->name   = $name;
        $this->parent = $parent;
    }

    public function name()
    {
        return $this->name;
    }

    public function id()
    {
        if ($this->id === null) {
            $parent = $this->parent();

            if ($parent === null) {
                $this->id = 'index';
            } else {
                $parentId = $parent->id();

                if ($parentId === 'index') {
                    $this->id = str_replace(':', '_', $this->name);
                } else {
                    $this->id = $parentId . '/' . $this->name;
                }
            }
        }

        return $this->id;
    }

    public function pathAsString()
    {
        if ($this->pathAsString === null) {
            if ($this->parent === null) {
                $this->pathAsString = $this->name;
            } else {
                $this->pathAsString = $this->parent->pathAsString() . DIRECTORY_SEPARATOR . $this->name;
            }
        }

        return $this->pathAsString;
    }

    public function pathAsArray()
    {
        if ($this->pathAsArray === null) {
            if ($this->parent === null) {
                $this->pathAsArray = [];
            } else {
                $this->pathAsArray = $this->parent->pathAsArray();
            }

            $this->pathAsArray[] = $this;
        }

        return $this->pathAsArray;
    }

    public function parent()
    {
        return $this->parent;
    }

    public function percentageOfTestedClasses()
    {
        return Percentage::fromFractionAndTotal(
            $this->numberOfTestedClasses(),
            $this->numberOfClasses()
        );
    }

    public function percentageOfTestedTraits()
    {
        return Percentage::fromFractionAndTotal(
            $this->numberOfTestedTraits(),
            $this->numberOfTraits()
        );
    }

    public function percentageOfTestedClassesAndTraits()
    {
        return Percentage::fromFractionAndTotal(
            $this->numberOfTestedClassesAndTraits(),
            $this->numberOfClassesAndTraits()
        );
    }

    public function percentageOfTestedFunctions()
    {
        return Percentage::fromFractionAndTotal(
            $this->numberOfTestedFunctions(),
            $this->numberOfFunctions()
        );
    }

    public function percentageOfTestedMethods()
    {
        return Percentage::fromFractionAndTotal(
            $this->numberOfTestedMethods(),
            $this->numberOfMethods()
        );
    }

    public function percentageOfTestedFunctionsAndMethods()
    {
        return Percentage::fromFractionAndTotal(
            $this->numberOfTestedFunctionsAndMethods(),
            $this->numberOfFunctionsAndMethods()
        );
    }

    public function percentageOfExecutedLines()
    {
        return Percentage::fromFractionAndTotal(
            $this->numberOfExecutedLines(),
            $this->numberOfExecutableLines()
        );
    }

    public function percentageOfExecutedBranches()
    {
        return Percentage::fromFractionAndTotal(
            $this->numberOfExecutedBranches(),
            $this->numberOfExecutableBranches()
        );
    }

    public function percentageOfExecutedPaths()
    {
        return Percentage::fromFractionAndTotal(
            $this->numberOfExecutedPaths(),
            $this->numberOfExecutablePaths()
        );
    }

    public function numberOfClassesAndTraits()
    {
        return $this->numberOfClasses() + $this->numberOfTraits();
    }

    public function numberOfTestedClassesAndTraits()
    {
        return $this->numberOfTestedClasses() + $this->numberOfTestedTraits();
    }

    public function classesAndTraits()
    {
        return array_merge($this->classes(), $this->traits());
    }

    public function numberOfFunctionsAndMethods()
    {
        return $this->numberOfFunctions() + $this->numberOfMethods();
    }

    public function numberOfTestedFunctionsAndMethods()
    {
        return $this->numberOfTestedFunctions() + $this->numberOfTestedMethods();
    }

    abstract public function classes();

    abstract public function traits();

    abstract public function functions();

    abstract public function linesOfCode();

    abstract public function numberOfExecutableLines();

    abstract public function numberOfExecutedLines();

    abstract public function numberOfExecutableBranches();

    abstract public function numberOfExecutedBranches();

    abstract public function numberOfExecutablePaths();

    abstract public function numberOfExecutedPaths();

    abstract public function numberOfClasses();

    abstract public function numberOfTestedClasses();

    abstract public function numberOfTraits();

    abstract public function numberOfTestedTraits();

    abstract public function numberOfMethods();

    abstract public function numberOfTestedMethods();

    abstract public function numberOfFunctions();

    abstract public function numberOfTestedFunctions();
}
