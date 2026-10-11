<?php
/*
 * This file is part of sebastian/type.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SebastianBergmann\Type;

use function gettype;
use const PHP_VERSION;
use function get_class;
use function strtolower;
use function version_compare;

abstract class Type {
    public static function fromValue($value, $allowsNull) {
        if ($value === false) {
            return new FalseType();
        }

        $typeName = gettype($value);

        if ($typeName === 'object') {
            return new ObjectType(TypeName::fromQualifiedName(get_class($value)), $allowsNull);
        }

        $type = self::fromName($typeName, $allowsNull);

        if ($type instanceof SimpleType) {
            $type = new SimpleType($typeName, $allowsNull, $value);
        }

        return $type;
    }

    public static function fromName($typeName, $allowsNull) {
        if (version_compare(PHP_VERSION, '8.1.0-dev', '>=') && strtolower($typeName) === 'never') {
            return new NeverType();
        }

        $result = null;

        switch (strtolower($typeName)) {
            case 'callable':
                $result = new CallableType($allowsNull);

                break;
            case 'false':
                $result = new FalseType();

                break;
            case 'true':
                $result = new TrueType();

                break;
            case 'iterable':
                $result = new IterableType($allowsNull);

                break;

            case 'null':
                $result = new NullType();

                break;
            case 'object':
                $result = new GenericObjectType($allowsNull);

                break;
            case 'unknown type':
                $result = new UnknownType();

                break;
            case 'void':
                $result = new VoidType();

                break;
            case 'array':
            case 'bool':
            case 'boolean':
            case 'double':
            case 'float':
            case 'int':
            case 'integer':
            case 'real':
            case 'resource':
            case 'resource (closed)':
            case 'string':
                $result = new SimpleType($typeName, $allowsNull);

                break;
            default:
                $result = new ObjectType(TypeName::fromQualifiedName($typeName), $allowsNull);

                break;

        }

        return $result;
    }

    public function asString() {
        return ($this->allowsNull() ? '?' : '') . $this->name();
    }

    public function isCallable() {
        return false;
    }

    public function isFalse() {
        return false;
    }

    public function isTrue() {
        return false;
    }

    public function isGenericObject() {
        return false;
    }

    public function isIntersection() {
        return false;
    }

    public function isIterable() {
        return false;
    }

    public function isMixed() {
        return false;
    }

    public function isNever() {
        return false;
    }

    public function isNull() {
        return false;
    }

    public function isObject() {
        return false;
    }

    public function isSimple() {
        return false;
    }

    public function isStatic() {
        return false;
    }

    public function isUnion() {
        return false;
    }

    public function isUnknown() {
        return false;
    }

    public function isVoid() {
        return false;
    }

    abstract public function isAssignable(self $other);

    abstract public function name();

    abstract public function allowsNull();
}
