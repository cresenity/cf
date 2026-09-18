<?php

defined('SYSPATH') or die('No direct access allowed.');
use Opis\Closure\SerializableClosure as OpisSerializableClosure;

class CFunction {
    /**
     * @var string|callable
     */
    public $func = '';

    /**
     * @var array
     */
    public $args = [];

    public $requires = [];

    public $type = 'defined'; //defined,class,

    private function __construct($func) {
        if ($func instanceof Closure) {
            $func = new CFunction_SerializableClosure($func);
        }
        // a closure that travelled through an ajax method file arrives as its serialize() string
        if (is_string($func) && strncmp($func, 'O:', 2) === 0) {
            $unserialized = @unserialize($func);
            if ($unserialized instanceof CFunction_SerializableClosure || $unserialized instanceof OpisSerializableClosure) {
                $func = $unserialized;
            }
        }
        $this->func = $func;
    }

    public static function factory($func) {
        return new CFunction($func);
    }

    public function setFunction($func) {
        $this->func = $func;

        return $this;
    }

    public function getFunction() {
        return $this->func;
    }

    public function setArgs(array $args) {
        $this->args = $args;

        return $this;
    }

    public function addArg($arg) {
        $this->args[] = $arg;

        return $this;
    }

    public function addRequire($p) {
        $this->requires[] = $p;

        return $this;
    }

    public function setRequire($p) {
        if ($p == null) {
            $p = [];
        }
        if (is_string($p)) {
            $p = [$p];
        }

        $this->requires = $p;

        return $this;
    }

    public function execute($args = []) {
        if (!is_array($args)) {
            $args = [$args];
        }
        foreach ($this->requires as $r) {
            if (strlen($r) > 0 && file_exists($r)) {
                require_once $r;
            }
        }
        $args = array_merge($args, $this->args);

        $error = 0;
        if ($error == 0) {
            if ($this->func instanceof OpisSerializableClosure) {
                return $this->func->__invoke(...$args);
            }
            if ($this->func instanceof CFunction_SerializableClosure) {
                return $this->func->__invoke(...$args);
            }
        }
        if ($error == 0) {
            if (is_array($this->func)) {
                if (is_callable($this->func)) {
                    return call_user_func_array($this->func, $args);
                } else {
                    $error++;
                }
            }
        }

        if ($error == 0) {
            if ($this->func instanceof Closure) {
                return call_user_func_array($this->func, $args);
            }
        }
        if ($error == 0) {
            if (is_callable($this->func)) {
                return call_user_func_array($this->func, $args);
            }
        }
        if ($error == 0) {
            //not array let check if it is a function name
            if (function_exists($this->func)) {
                return call_user_func_array($this->func, $args);
            }
        }
        if ($error == 0) {
            //try to get from transform
            $transformer = CManager_Transform::instance();

            if ($transformer->isTransformable($this->func)) {
                $item = carr::first($args);
                $parameters = array_slice($args, 1);

                return $transformer->call($this->func, $item, $parameters);
            }

            //not the function name, let check it if it is function from ctransform class
            if (is_callable(['ctransform', $this->func])) {
                return call_user_func_array(['ctransform', $this->func], $args);
            }
            //not the function name, let check it if it is function from ctransform class
        }
        if ($error == 0) {
            //not the function name, let check it if it is function from CHelper_Transform class
            $transform = CHelper::transform();
            if (is_callable([$transform, $this->func])) {
                return call_user_func_array([$transform, $this->func], $args);
            }
        }
        if ($error == 0) {
            //it is not method from ctransform class, try the other class if it is found ::
            if (strpos($this->func, '::') !== false) {
                return call_user_func_array(explode('::', $this->func), $args);
            }
        }
        //last return this name of function
        if ($error == 0) {
            return $this->func;
        }

        return 'ERROR ON CFUNCTION';
    }

    public static function serializeClosure(Closure $func) {
        return new CFunction_SerializableClosure($func);
    }

    public static function isSerializeClosure($func) {
        return $func instanceof CFunction_SerializableClosure;
    }

    public static function getClosureCode(Closure $c) {
        $str = 'function (';
        $r = new ReflectionFunction($c);
        $params = [];
        foreach ($r->getParameters() as $p) {
            $s = '';
            if ($p->isArray()) {
                $s .= 'array ';
            } elseif ($p->getClass()) {
                $s .= $p->getClass()->name . ' ';
            }
            if ($p->isPassedByReference()) {
                $s .= '&';
            }
            $s .= '$' . $p->name;
            if ($p->isOptional()) {
                $s .= ' = ' . var_export($p->getDefaultValue(), true);
            }
            $params[] = $s;
        }
        $str .= implode(', ', $params);
        $str .= '){' . PHP_EOL;
        $lines = file($r->getFileName());
        for ($l = $r->getStartLine(); $l < $r->getEndLine(); $l++) {
            $str .= $lines[$l];
        }

        return $str;
    }
}
