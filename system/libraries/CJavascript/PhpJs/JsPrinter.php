<?php

use PhpParser\Node;
use PhpParser\Error;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Scalar;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Scalar\MagicConst;

class CJavascript_PhpJs_JsPrinter extends CJavascript_PhpJs_JsPrinterAbstract {
    /**
     * @var SourceWriter
     */
    protected $writer;

    /**
     * @var bool
     */
    private $usePrivate;

    /**
     * @var ClosureHelper
     */
    private $closureHelper;

    private $useByRef = null;

    private $useByRedStack = [];

    private static function WTF($message = 'WTF', $node = null) {
        var_dump($node);

        throw new Error($message);
    }

    public function __construct($usePrivate = true) {
        $this->closureHelper = new CJavascript_PhpJs_ClosureHelper();
        $this->writer = new CJavascript_PhpJs_SourceWriter();
        $this->usePrivate = $usePrivate;
    }

    public function pParam(Node\Param $node) {
        $this->notImplemented($node->byRef, "reference param {$node->name} by & ");
        $this->closureHelper->useVar($node->name);
        if ($node->variadic && CJavascript_PhpJs_JsPrinterAbstract::$enableVariadic) {
            $this->print('...');
        }
        $this->print($node->name);
    }

    /**
     * @param Node\Param[] $params
     *
     * @throws Error
     *
     * @return string
     */
    public function pParamDefaultValues(array $params) {
        foreach ($params as $node) {
            if (!$node instanceof Node\Param) {
                throw new Error('this is not instanceof Node\Param but ' . get_class($node));
            }
            if (!$node->default) {
                continue;
            }
            $this->writer
                ->print("if (typeof %{argX} == 'undefined') %{argX}=", $node->name, $node->name);
            $this->p($node->default);
            $this->writer
                ->println(';');
        }
        foreach ($params as $paramPos => $node) {
            if (!$node->type) {
                continue;
            }
            if ($node->variadic) {
                $this->println("for(var __paraPos={$paramPos};__paraPos<arguments.length;__paraPos++){");
                $this->indent();
            }
            $this->print('if (!');
            if (is_string($node->type)) {
                $this->print('is%{Type}(%{argX})', ucfirst($node->type), $node->name);
            } else {
                $classParts = explode('\\', $node->type);
                if (count($classParts) > 1) {
                    $className = 'N.' . join('.', $classParts);
                } else {
                    $className = $node->type;
                }
                if ($node->variadic) {
                    $this->print('%{argX}', 'arguments[__paraPos]');
                } else {
                    $this->print('%{argX}', $node->name);
                }
                $this->print(' instanceof %{Class}', $className);
            }
            $this->println(") throw new Error('bad param type');");

            if ($node->variadic) {
                $this->outdent();
                $this->println('}');
            }
        }
    }

    public function pArg(Node\Arg $node) {
        //TODO: implement this
        $this->notImplemented($node->unpack, 'unpacking argument by ...');
        $this->notImplemented($node->byRef, 'reference by &');
        $this->closureHelper->isDefScope(true);
        $this->p($node->value);
        $this->closureHelper->isDefScope(false);
    }

    public function pConst(Node\Const_ $node) {
        $this->print('%{constName} = ', $node->name);
        $this->p($node->value);
    }

    public function pName(Name $node) {
        if (count($node->parts) == 1 && $node->parts[0] == 'parent') {
            $this->print('parent.prototype');

            return;
        }
        if (count($node->parts) == 1 && $node->parts[0] == 'self') {
            $this->print($this->closureHelper->getSimpleClassName());

            return;
        }
        if (count($node->parts) == 1 && $node->parts[0] == 'FALSE') {
            $this->print('false');

            return;
        }
        if (count($node->parts) == 1 && $node->parts[0] == 'TRUE') {
            $this->print('true');

            return;
        }
        $this->print(implode('.', $node->parts));
    }

    public function pNameFullyQualified(Name\FullyQualified $node) {
        if (count($node->parts) > 1) {
            $this->print('N.');
        }
        $this->print(implode('.', $node->parts));
    }

    public function pNameRelative(Name\Relative $node) {
        //TODO: implement this
        self::WTF('pName_Relative', $node);
        $this->print('namespace\\' . implode('\\', $node->parts));
    }

    public function pScalarMagicConstClass(MagicConst\Class_ $node) {
        //TODO: implement this
        $this->print("'{$this->closureHelper->getClassName()}'");
    }

    public function pScalarMagicConstDir(MagicConst\Dir $node) {
        //TODO: implement this
        $this->notImplemented(true, __METHOD__);

        return '__DIR__';
    }

    public function pScalarMagicConstFile(MagicConst\File $node) {
        //TODO: implement this
        $this->notImplemented(true, __METHOD__);

        return '__FILE__';
    }

    public function pScalarMagicConstFunction(MagicConst\Function_ $node) {
        //TODO: implement this
        $this->print("'{$this->closureHelper->getFunctionName()}'");
    }

    public function pScalarMagicConstLine(MagicConst\Line $node) {
        //TODO: implement this
        $this->notImplemented(true, __METHOD__);

        return '__LINE__';
    }

    public function pScalarMagicConstMethod(MagicConst\Method $node) {
        //TODO: implement this
        $this->print("'{$this->closureHelper->getMethodName()}'");
    }

    public function pScalarMagicConstNamespace(MagicConst\Namespace_ $node) {
        //TODO: implement this
        $this->print("'{$this->closureHelper->getNamespaceName()}'");
    }

    public function pScalarMagicConstTrait(MagicConst\Trait_ $node) {
        //TODO: implement this
        $this->notImplemented(true, __METHOD__);

        return '__TRAIT__';
    }

    public function pScalarString(Scalar\String_ $node) {
        $str = addcslashes($node->value, '"\\');
        $str = str_replace(PHP_EOL, '\n\\' . PHP_EOL, $str);
        $this->print('"' . $str . '"');
    }

    public function pScalarEncapsed(Scalar\Encapsed $node) {
        if ($node->getAttribute('kind') === Scalar\String_::KIND_HEREDOC) {
            $label = $node->getAttribute('docLabel');
            if ($label && !$this->encapsedContainsEndLabel($node->parts, $label)) {
                $this->notImplemented(true, 'encapsed strig with <<<');
                if (count($node->parts) === 1
                    && $node->parts[0] instanceof Scalar\EncapsedStringPart
                    && $node->parts[0]->value === ''
                ) {
                    $str = $this->pNoIndent("<<<{$label}\n{$label}") . $this->docStringEndToken;
                    $this->print($str);

                    return;
                }
                $str = $this->pNoIndent(
                    "<<<{$label}\n" . $this->pEncapsList($node->parts, null) . "\n{$label}"
                ) . $this->docStringEndToken;
                $this->print($str);
            }
        }
        $this->print('"');
        $this->pEncapsList($node->parts, '"');
        $this->print('"');
        $this->println();
    }

    protected function encapsedContainsEndLabel(array $parts, $label) {
        foreach ($parts as $i => $part) {
            $atStart = $i === 0;
            $atEnd = $i === count($parts) - 1;
            if ($part instanceof Scalar\EncapsedStringPart
                && $this->containsEndLabel($part->value, $label, $atStart, $atEnd)
            ) {
                return true;
            }
        }

        return false;
    }

    protected function containsEndLabel($string, $label, $atStart = true, $atEnd = true) {
        $start = $atStart ? '(?:^|[\r\n])' : '[\r\n]';
        $end = $atEnd ? '(?:$|[;\r\n])' : '[;\r\n]';

        return false !== strpos($string, $label)
        && preg_match('/' . $start . $label . $end . '/', $string);
    }

    public function pExprInstanceof(Expr\Instanceof_ $node) {
        $this->p($node->expr);
        $this->print(' instanceof ');
        if ($node->class instanceof Expr\Variable) {
            $this->print('N._GET_(');
        } else {
            if ($node->class instanceof Name && count($node->class->parts) > 1) {
                $this->print('N.');
            }
        }
        $this->p($node->class);
        if ($node->class instanceof Expr\Variable) {
            $this->print(')');
        }
    }

    public function pExprFuncCall(Expr\FuncCall $node) {
        if ($node->name instanceof Expr\Closure) {
            $this->print('(');
        }
        $this->p($node->name);
        if ($node->name instanceof Expr\Closure) {
            $this->print(')');
        }
        $this->print('(');
        $this->pCommaSeparated($node->args);
        $this->print(')');
    }

    public function pExprMethodCall(Expr\MethodCall $node) {
        if ($node->var instanceof Expr\Variable && $node->var->name == 'this' && $this->closureHelper->isClassPrivateMethod($node->name)) {
            $this->print('__private(');
        }
        $this->pVarOrNewExpr($node->var);
        if ($node->var instanceof Expr\Variable && $node->var->name == 'this' && $this->closureHelper->isClassPrivateMethod($node->name)) {
            $this->print(')');
        }
        $this->print('.');
        $this->pObjectProperty($node->name);
        if ($node->var instanceof Expr\Variable && $node->var->name == 'this' && $this->closureHelper->isClassPrivateMethod($node->name)) {
            $this->print('.call(this');
            if (count($node->args) > 0) {
                $this->print(',');
            }
        } else {
            $this->print('(');
        }
        $this->pCommaSeparated($node->args);
        $this->print(')');
    }

    public function pExprStaticCall(Expr\StaticCall $node) {
        //TODO: implement this
        $this->p($node->class);
        $this->print('.');
        if ($node->name instanceof Expr) {
            if ($node->name instanceof Expr\Variable || $node->name instanceof Expr\ArrayDimFetch) {
                $this->p($node->name);
            } else {
                $this->print('{');
                $this->p($node->name);
                $this->print('}');
            }
        } else {
            $this->print($node->name);
        }
        if (count($node->class->parts) == 1 && $node->class->parts[0] == 'parent') {
            $this->print('.call');
        }
        $this->print('(');
        if (count($node->class->parts) == 1 && $node->class->parts[0] == 'parent') {
            $this->print('this');
            if (count($node->args) > 0) {
                $this->print(',');
            }
        }
        $this->pCommaSeparated($node->args);
        $this->print(')');
    }

    public function pExprInclude(Expr\Include_ $node) {
        //TODO: implement this
        $this->notImplemented(true, ' include and require');
        static $map = [
            Expr\Include_::TYPE_INCLUDE => 'include',
            Expr\Include_::TYPE_INCLUDE_ONCE => 'include_once',
            Expr\Include_::TYPE_REQUIRE => 'require',
            Expr\Include_::TYPE_REQUIRE_ONCE => 'require_once',
        ];

        $this->pushDelay();
        $this->p($node->expr);
        $this->popDelayToVar($path);
        $path = substr(substr($path, 1), 0, -1);

        if ($this->ROOT_PATH_TO) {
            $jsPrinter = new self();
            $jsPrinter->closureHelper->fileExtend($this->closureHelper);
            $jsPrinter->jsPrintFileTo($this->ROOT_PATH_FROM . $path, $this->ROOT_PATH_TO . $path . '.js');
        }

        $this->println('eval(%{include}("%{path}.js"))', $map[$node->type], $path);
    }

    /**
     * @param $type
     * @param \PhpParser\Node $leftNode
     * @param string|int      $operator  or delayId
     * @param \PhpParser\Node $rightNode
     *
     * @see PhpParser\Printer\PrinterAbstract::pInfixOp
     *
     * @return void
     */
    protected function pInfixOp($type, Node $leftNode, $operator, Node $rightNode): string {
        list($precedence, $associativity) = $this->precedenceMap[$type];

        $pList = [];
        $listNode = null;
        if ($leftNode instanceof Expr\List_) {
            $listNode = $leftNode;
            $leftNode = new Expr\Variable('__LIST_VALUES__');
            $pList = [];
            foreach ($listNode->items as $var) {
                if ($var == null) {
                    $pList[] = null;

                    continue;
                }
                $this->closureHelper->pushVar($var->value->name);
                $pList[] = $var->value->name;
            }
            $this->closureHelper->pushVar('__LIST_VALUES__');
            $this->printVarDef();
        }

        $this->pPrec($leftNode, $precedence, $associativity, -1);
        if (gettype($operator) == 'integer') {
            $this->writer->writeDelay($operator);
        } else {
            $this->writer->print($operator);
        }
        $this->pPrec($rightNode, $precedence, $associativity, 1);

        if ($listNode instanceof Expr\List_) {
            $this->println(';');
            foreach ($pList as $pos => $varName) {
                if ($varName == null) {
                    continue;
                }
                $this->print('%{varName}=__LIST_VALUES__[%{keyPos}]', $varName, $pos);
                if ($pos < count($pList) - 1) {
                    $this->println(';');
                } else {
                    $this->println();
                }
            }
        }

        return '';
    }

    public function pExprList(Expr\List_ $node, $force = false) {
    }

    public function pExprVariable(Expr\Variable $node) {
        //TODO: implement this
        $this->closureHelper->pushVar($node->name);
        if ($node->name instanceof Expr) {
            $this->notImplemented(true, 'acces by ${name}');
            $this->print($node->name);
        } else {
            $this->print($node->name);
        }
    }

    public function pExprArray(Expr\Array_ $node) {
        $this->closureHelper->pushArrayIndex();
        $this->print('{');
        $this->pCommaSeparated($node->items);
        $this->print('}');
        $this->closureHelper->popArrayIndex();
    }

    public function pExprArrayItem(Expr\ArrayItem $node) {
        $this->notImplemented($node->byRef, ' array value reference &');
        if ($node->key !== null) {
            $this->p($node->key);
        } else {
            $this->print($this->closureHelper->arrayIndex());
        }
        $this->print(':');
        $this->p($node->value);
    }

    public function pExprArrayDimFetch(Expr\ArrayDimFetch $node) {
        //TODO: implement this
        $this->pVarOrNewExpr($node->var);
        $this->print('[');
        if (null !== $node->dim) {
            $this->p($node->dim);
        }
        $this->print(']');
    }

    public function pExprConstFetch(Expr\ConstFetch $node) {
        //TODO: implement this
        $this->p($node->name);
    }

    public function pExprClassConstFetch(Expr\ClassConstFetch $node) {
        //TODO: implement this
        if (count($node->class->parts) == 1 && $node->class->parts[0] == 'self') {
            $this->print($this->closureHelper->getSimpleClassName());
        } else {
            $this->p($node->class);
        }
        $this->print('.' . $node->name);
    }

    public function pExprPropertyFetch(Expr\PropertyFetch $node) {
        //TODO: implement this
        if ($node->var instanceof Expr\Variable && $node->var->name == 'this' && $this->closureHelper->isClassPrivateProperty($node->name)) {
            $this->print('__private(');
        }
        $this->pVarOrNewExpr($node->var);
        if ($node->var instanceof Expr\Variable && $node->var->name == 'this' && $this->closureHelper->isClassPrivateProperty($node->name)) {
            $this->print(')');
        }
        if (!($node->name instanceof Expr)) {
            $this->print('.');
        }
        $this->pObjectProperty($node->name);
    }

    public function pExprStaticPropertyFetch(Expr\StaticPropertyFetch $node) {
        //TODO: implement this
        $this->p($node->class);
        $this->print('.');
        $this->pObjectProperty($node->name);
    }

    public function pExprShellExec(Expr\ShellExec $node) {
        //TODO: implement this
        $this->notImplemented(true, 'shell exec', true);
    }

    protected function printUseByRefDef() {
        if ($this->useByRef !== null) {
            $useByRef = $this->useByRef;
            $this->useByRef = null;
            $this->println($useByRef);
        }
    }

    public function pExprClosure(Expr\Closure $node) {
        //TODO: implement this
        $this->notImplemented($node->byRef, 'closure reference by &');
        if ($node->static) {
            self::WTF();
            $this->print('static');
        }
        $this->closureHelper->pushVarScope();
        $useByRef = [];
        if (!empty($node->uses)) {
            $useByRef2 = [];
            foreach ($node->uses as $use) {
                $this->closureHelper->useVar($use->var);
                if (!$use->byRef) {
                    $useByRef2[] = $use->var . '_=' . $use->var;
                    $useByRef[] = $use->var . '=' . $use->var . '_';
                }
            }
            if (count($useByRef2)) {
                $this->useByRedStack[] = 'var ' . join(',', $useByRef2) . ';';
            }
        }

        $this->print('function(');
        $this->closureHelper->isDefScope(true);

        $this->pCommaSeparated($node->params);
        $this->closureHelper->isDefScope(false);
        $this->print(')');
        $this->println('{')
            ->indent();
        if (count($useByRef)) {
            $this->println('var ' . join(',', $useByRef) . ';');
        }

        $this->pushDelay(true);
        $this->pStmts($node->stmts);
        $this->popDelayToVar($body);

        $this->printVarDef();
        $this->print($body);

        $this->outdent()
            ->println('}');
        if ($this->useByRef !== null) {
            throw new \Exception('method printUseByRefDef must be used!!');
        }
        if (count($useByRef)) {
            $this->useByRef = array_pop($this->useByRedStack);
        }
        $this->closureHelper->popVarScope();
    }

    public function pExprClosureUse(Expr\ClosureUse $node) {
        //TODO: implement this
        throw new \Exception('What you doing here??');
    }

    public function pExprNew(Expr\New_ $node) {
        //TODO: implement this
        $this->print('new ');
        if ($node->class instanceof Expr\Variable) {
            $this->print('(N._GET_(');
        }
        if ($node->class instanceof Stmt\Class_) {
            $node->class->parameters = $node->args;
        }
        $this->p($node->class);
        if ($node->class instanceof Expr\Variable) {
            $this->print('))');
        }
        if (!$node->class instanceof Stmt\Class_) {
            $this->print('(');
            $this->pCommaSeparated($node->args);
            $this->print(')');
        }
    }

    public function pExprClone(Expr\Clone_ $node) {
        //TODO: implement this
        $this->notImplemented(true, 'cloning by clone');
    }

    public function pExprTernary(Expr\Ternary $node) {
        //TODO: implement this
        // a bit of cheating: we treat the ternary as a binary op where the ?...: part is the operator.
        // this is okay because the part between ? and : never needs parentheses.
        $this->pushDelay();
        $this->print('?');
        if ($node->if !== null) {
            $this->p($node->if);
        }
        $this->print(':');
        $this->popDelay($delayId);
        $this->pInfixOp('Expr_Ternary', $node->cond, $delayId, $node->else);
        /*$this->pInfixOp('Expr_Ternary',
            $node->cond, ' ?' . (null !== $node->if ? ' ' . $this->p($node->if) . ' ' : '') . ': ', $node->else
        );*/
    }

    public function pExprExit(Expr\Exit_ $node) {
        $this->print('throw new Exit(');
        if ($node->expr !== null) {
            $this->p($node->expr);
        }
        $this->println(');');
    }

    public function pExprYield(Expr\Yield_ $node) {
        //TODO: implement this
        $this->notImplemented(true, 'using yield', true);
    }

    /**
     * @param null|Node\Name $node
     * @param $pos
     */
    private function printNamespaceVar($node, $pos = 0) {
        if ($node === null) {
            return;
        }
        if ($pos == 0) {
            $this->print('/** @var {{');
        }
        $this->print('%{name}: {', $node->parts[$pos]);
        if (count($node->parts) > $pos + 1) {
            $this->printNamespaceVar($node, $pos + 1);
        }
        $this->print('}');
        if ($pos == 0) {
            $this->println('}} N*/');
        }
    }

    public function pStmtNamespace(Stmt\Namespace_ $node) {
        //TODO: implement this
        if ($node->name !== null) {
            $this->closureHelper->setNamespace(true, $node->name);
            $this->printNamespaceVar($node->name);
            $this->print("N._INIT_('");
            $this->p($node->name);
            $this->println("');")
                ->println('(function(){');

            $this->indent();
            $this->println('for(var __ClassName in this){');
            $this->indent();
            $this->println("if (__ClassName==='class') continue;");
            $this->println('eval("var "+__ClassName+" = this."+__ClassName+";");');
            $this->outdent();
            $this->println('}');
            $this->outdent();

            $this->closureHelper->pushVarScope();
            $this->pStmts($node->stmts);
            $this->closureHelper->popVarScope();
            $this->print('}).call(N.');
            $this->p($node->name);
            $this->println(');');
            $this->closureHelper->setNamespace(false, null);
        } else {
            $this->pStmts($node->stmts);
        }
    }

    public function pStmtUse(Stmt\Use_ $node) {
        //TODO: implement this
        foreach ($node->uses as $node) {
            $this->print('var %{varName} = N.', $node->alias ? $node->alias : $node->name->getLast());
            $this->p($node->name);
            $this->println(';');
        }
    }

    public function pStmtGroupUse(Stmt\GroupUse $node) {
        //TODO: implement this
        $this->notImplemented(true, __METHOD__);
    }

    public function pStmtUseUse(Stmt\UseUse $node) {
        //TODO:: implement this
        $this->notImplemented(true, __METHOD__);
    }

    public function pUseType($type) {
        //TODO: implement this
        $this->notImplemented(true, __METHOD__);
    }

    public function pStmtInterface(Stmt\Interface_ $node) {
        $classWrapper = new Stmt\Class_(
            $node->name,
            [
                'type' => $node->getType(),
                'name' => $node->name,
                'extends' => null,
                'implements' => $node->extends,
                'stmts' => $node->stmts,
            ],
            $node->getAttributes()
        );
        $this->closureHelper->setNextClassIsInterface();

        $this->pStmtClass($classWrapper);
    }

    public function pStmtClass(Stmt\Class_ $node) {
        //TODO: implement this
        if ($node->name != null) {
            $className = $node->name;
        } else {
            $className = '__anonymous__';
        }
        $this->closureHelper->pushClass($className);

        $anonymousClassParameters = [];
        if (isset($node->parameters)) {
            $anonymousClassParameters = $node->parameters;
        }

        $this->pushDelay();

        if ($node->extends || $node->implements) {
            $this->indent()
                ->print('__extends(%{ClassName}, %{parent}', $className, $node->extends ? 'parent' : 'null');
            if ($node->implements) {
                $this->print(',arguments[1]');
            }
            $this->println(');')
                ->outdent();
        }
        $this->popDelayToVar($extends);

        $this->pushDelay();
        $this->closureHelper->pushVarScope();
        $this->pStmts($node->stmts);
        $this->closureHelper->popVarScope();
        $this->popDelay($constructorBody);

        $this->pushDelay()->indent();
        if (is_int($node->flags) && $node->flags & Stmt\Class_::MODIFIER_ABSTRACT) {
            $this->println('%{ClassName}.prototype.__isAbstract__=true;', $className);
        }
        foreach ($this->closureHelper->getClassStaticProperties() as $property) {
            /** @var Stmt\PropertyProperty $property */
            //if ($node->type & Stmt\Class_::MODIFIER_STATIC){ TODO: implement private static property
            $comments = $property->getAttribute('comments', []);
            if ($comments) {
                $this->pComments($comments);
                $property->setAttribute('comments', []);
            }
            $this->print('%{ClassName}.', $node->name);
            $this->p($property);
            $this->println(';');
            //}
        }

        foreach ($this->closureHelper->getClassConstants() as $consts) {
            /** @var Stmt\ClassConst $consts */
            foreach ($consts->consts as $cons) {
                $comments = $cons->getAttribute('comments', []);
                if ($comments) {
                    $this->pComments($comments);
                    $cons->setAttribute('comments', []);
                }
                $this->print('%{ClassName}.', $className);
                $this->pConst($cons);
                $this->println(';');
            }
        }

        foreach ($this->closureHelper->getClassPublicMethods() as $method) {
            $comments = $method->getAttribute('comments', []);
            if ($comments) {
                $this->pComments($comments);
                $method->setAttribute('comments', []);
            }
            /** @var Stmt\ClassMethod $method */
            $this->print('%{ClassName}.%{prototype}', $className, $method->type & Stmt\Class_::MODIFIER_STATIC ? '' : 'prototype.');
            $this->pStmtClassMethod($method, true);
        }

        $this->print($node->name)
            ->print(".class='")
            ->print($this->closureHelper->getClassName())
            ->println("';");
        if ($this->closureHelper->getClassHasMagicMethods()) {
            $this->println('var __handler = {')
                ->indent()
                ->println('construct: function(target, args) {')
                ->indent()
                ->println('var obj = Object.create(%{ClassName}.prototype);', $className)
                ->println('%{ClassName}.apply(obj,args);', $className)
                ->println('return new Proxy(obj,__PROXY_HANDLER);')
                ->outdent()
                ->println('}')
                ->outdent()
                ->println('};')
                ->println('return new Proxy(%{ClassName}, __handler);', $className);
        } else {
            $this->println('return %{ClassName};', $className);
        }
        $this->outdent()
            ->popDelay($methodsAndOthers);

        $this->pushDelay()->indent();
        $this->print('function %{ClassName}(', $className);
        if ($this->closureHelper->classHasConstructor()) {
            $this->pCommaSeparated($this->closureHelper->getClassConstructorParams());
        }
        $this->println('){');
        $this->indent();
        if ($this->closureHelper->classHasConstructor() || $node->extends) {
            $this->println('var __isInheritance=__IS_INHERITANCE__;');
        }
        if ($node->extends) {
            $this->println('window.__IS_INHERITANCE__=true;');
            $this->println('parent.call(this);');
        } else {
            $this->println('window.__IS_INHERITANCE__=false;');
        }
        if ($this->closureHelper->classIsInterface()) {
            $this->println('__INTERFACE_NEW__();');
        }
        $this->writeDelay($constructorBody);

        foreach ($this->closureHelper->getClassPrivateMethods() as $method) {
            $comments = $method->getAttribute('comments', []);
            if ($comments) {
                $this->pComments($comments);
                $method->setAttribute('comments', []);
            }
            /** @var Stmt\ClassMethod $method */
            $this->println('__private(this).%{methodName}=__%{methodName};', $method->name, $method->name);
        }
        if ($this->closureHelper->classHasConstructor()) {
            $this->println('if (__isInheritance==false){');
            $this->indent();
            $this->print('this.__construct(');
            $this->pCommaSeparated($this->closureHelper->getClassConstructorParams());
            $this->println(');');
            $this->outdent();
            $this->println('}');
        } elseif ($node->extends) {
            $this->println('if (__isInheritance==false){');
            $this->indent();
            $this->println('if (parent.prototype.__construct){');
            $this->indentln('parent.prototype.__construct.apply(this,arguments);');
            $this->println('}');
            $this->outdent();
            $this->println('}');
        }
        $this->outdent()
            ->println('}')
            ->outdent()
            ->print($extends)
            ->popDelay($classBody);

        $format = '';
        $params = [];
        if ($node->name != null) {
            $format .= 'var %{Class} = ';
            $params[] = $node->name;
        }
        if ($this->closureHelper->isNamespace() && !isset($node->parameters)) {
            $format .= '%{useNamSPC}';
            $params[] = "this.{$node->name} = ";
        }
        $format .= '(function (%{useParent}';
        $params[] = $node->extends ? 'parent' : '';
        call_user_func_array([$this->writer, 'print_'], array_merge([$format], $params));

        if (count($anonymousClassParameters)) {
            if ($node->implements) {
                $this->print(',__implements');
            }
            $this->print(',');
            $this->pCommaSeparated($anonymousClassParameters);
        }

        $this->println('){');
        if ($this->closureHelper->hasClassPrivateMethodsOrProperties()) {
            $this->indentln('var __private = __PRIVATIZE__();');
        }
        foreach ($this->closureHelper->getClassPrivateMethods() as $method) {
            /** @var Stmt\ClassMethod $method */
            $this->print('var __');
            $this->pStmtClassMethod($method, true);
        }
        $this->writeDelay($classBody);
        $this->writeDelay($methodsAndOthers);
        $this->print('})(');
        if ($node->extends || $node->implements) {
            if ($node->extends) {
                $this->p($node->extends);
            } else {
                $this->print('null');
            }
            if ($node->implements) {
                $this->print(',[');
                $this->pCommaSeparated($node->implements);
                $this->print(']');
            }
        }
        if (count($anonymousClassParameters)) {
            $this->print(',');
            $this->pCommaSeparated($anonymousClassParameters);
        }
        $this->print(')');
        if (!isset($node->parameters)) {
            $this->print(';');
        }
        $this->println();
        $this->closureHelper->popClass();
        //$this->outdent();
    }

    public function pStmtTrait(Stmt\Trait_ $node) {
        //TODO: implement this
        $this->notImplemented(true, 'tait', true);
    }

    public function pStmtTraitUse(Stmt\TraitUse $node) {
        //TODO: implement this
        $this->notImplemented(true, 'use tait', true);
    }

    public function pStmtTraitUseAdaptationPrecedence(Stmt\TraitUseAdaptation\Precedence $node) {
        //TODO: implement this
        $this->notImplemented(true, 'pStmt_TraitUseAdaptation_Precedence', true);
    }

    public function pStmtTraitUseAdaptationAlias(Stmt\TraitUseAdaptation\Alias $node) {
        //TODO: implement this
        $this->notImplemented(true, 'pStmt_TraitUseAdaptation_Alias', true);
    }

    public function pStmtProperty(Stmt\Property $node) {
        //TODO: implement this
        foreach ($node->props as $property) {
            if (!$property->default) {
                $property->default = new \PhpParser\Node\Expr\ConstFetch(new \PhpParser\Node\Name('null', $property->getAttributes()), $property->getAttributes());
            }
        }
        if ($node->type & Stmt\Class_::MODIFIER_STATIC) {
            foreach ($node->props as $prop) {
                $this->closureHelper->addClassStaticProperty($prop);
            }

            return;
        }
        foreach ($node->props as $property) {
            if ($node->type & Stmt\Class_::MODIFIER_PRIVATE && $this->usePrivate) {
                $this->closureHelper->addClassPrivatePropertyName($property->name);
                $this->print('__private(');
            }
            $this->print('this');
            if ($node->type & Stmt\Class_::MODIFIER_PRIVATE && $this->usePrivate) {
                $this->print(')');
            }
            $this->print('.');
            $this->p($property);
            $this->println(';');
        }
    }

    public function pStmtPropertyProperty(Stmt\PropertyProperty $node) {
        //TODO: implement this
        $this->print($node->name);
        if ($node->default !== null) {
            $this->print(' = ');
            $this->p($node->default);
        }
    }

    public function pStmtClassMethod(Stmt\ClassMethod $node, $force = false) {
        //TODO: implement this
        if ($force) {
            if (in_array($node->name, ['__get', '__set', '__call'])) {
                $this->closureHelper->setClassHasMagicMethods();
            }
            if ($node->name == '__construct') {
                $this->closureHelper->setClassConstructorParams($node->params);
            }
            $this->closureHelper->pushVarScope();

            $this->notImplemented($node->byRef, 'method return reference');
            //return //$this->pModifiers($node->type)
            $this->closureHelper->setMethodName($node->name);
            $this->print($node->name);
            $this->print(' = function(');
            $this->pCommaSeparated($node->params);
            $this->println('){');
            if ($node->stmts !== null) {
                $this->pParamDefaultValues($node->params);

                $this->pushDelay(true);
                $this->pStmts($node->stmts);
                $this->popDelayToVar($body);

                $this->printVarDef();
                $this->print($body);
            } else {
                if ($this->closureHelper->classIsInterface()) {
                    $this->println('__INTERFACE_FUNC__();');
                } elseif ($node->isAbstract()) {
                    $this->println('__ABSTRACT_FUNC__();');
                } else {
                    self::WTF('where is body???');
                }
            }
            $this->println('};');
            $this->closureHelper->popVarScope();
        } else {
            if ($node->type & Stmt\Class_::MODIFIER_PRIVATE && $this->usePrivate) {
                $this->closureHelper->addClassPrivateMethodName($node->name);
                $this->closureHelper->addClassPrivateMethod($node);
            } else {
                $this->closureHelper->addClassPublicMethod($node);
            }
        }
    }

    public function pStmtClassConst(Stmt\ClassConst $node) {
        $this->closureHelper->addClassConstants($node);
    }

    public function pStmtFunction(Stmt\Function_ $node) {
        $this->closureHelper->pushVarScope();
        $this->notImplemented($node->byRef, "function return reference by function &{$node->name}(...");
        $this->closureHelper->setFunctionName($node->name);
        if ($this->closureHelper->isNamespace()) {
            $this->println('var %{name} = this.%{name} = function(', $node->name, $node->name);
        } else {
            $this->print('function %{name}(', $node->name);
        }
        $this->pCommaSeparated($node->params);
        $this->println('){')
            ->indent();
        //TODO: where is use keyword???
        $this->pParamDefaultValues($node->params);

        $this->pushDelay(true);
        $this->pStmts($node->stmts);
        $this->popDelayToVar($body);

        $this->printVarDef();
        $this->print($body);
        $this->outdent()
            ->print('}');
        if ($this->closureHelper->isNamespace()) {
            $this->print(';');
        }
        $this->println();
        $this->closureHelper->popVarScope();
    }

    public function pStmtConst(Stmt\Const_ $node) {
        if ($this->closureHelper->isNamespace()) {
            foreach ($node->consts as $const) {
                $this->print('var %{varName} = ', $const->name);
                $this->p($const->value);
                $this->println(';this.%{varName}=%{varName};', $const->name, $const->name);
            }
        } else {
            foreach ($node->consts as $const) {
                $this->print('window.%{varName} = ', $const->name);
                $this->p($const->value);
                $this->println(';');
            }
        }
    }

    public function pStmtDeclare(Stmt\Declare_ $node) {
        $this->notImplemented(true, 'declare()', true);
    }

    public function pStmtDeclareDeclare(Stmt\DeclareDeclare $node) {
        $this->notImplemented(true, 'declare()', true);
    }

    public function pStmtIf(Stmt\If_ $node) {
        $this->print('if (');
        $this->p($node->cond);
        $this->println('){')
            ->indent();
        $this->pStmts($node->stmts);
        $this->outdent()
            ->print('}');
        $this->pImplode($node->elseifs);
        if ($node->else !== null) {
            $this->p($node->else);
        } else {
            $this->println();
        }
    }

    public function pStmtElseIf(Stmt\ElseIf_ $node) {
        $this->print('else if(');
        $this->p($node->cond);
        $this->println('){')
            ->indent();
        $this->pStmts($node->stmts);
        $this->outdent()
            ->println('}');
    }

    public function pStmtElse(Stmt\Else_ $node) {
        $this->println('else{');
        $this->pStmts($node->stmts);
        $this->println('}');
    }

    public function pStmtFor(Stmt\For_ $node) {
        $loopName = $this->closureHelper->pushLoop();

        $this->pushDelay();
        $this->println($loopName . ':');
        $this->print('for(');
        $this->pCommaSeparated($node->init);
        $this->print('; ');
        $this->pCommaSeparated($node->cond);
        $this->print('; ');
        $this->pCommaSeparated($node->loop);
        $this->print(')');
        $this->popDelayToVar($statement);

        $this->pushDelay();
        $this->printVarDef();
        $this->popDelayToVar($vars);

        $this->pushDelay();
        $this->indent();
        $this->pStmts($node->stmts);
        $this->outdent();
        $this->popDelayToVar($loopBody);

        $this->print($vars)
            ->print($statement)
            ->println('{')
            ->print($loopBody)
            ->println('}');
        $this->closureHelper->popLoop();
    }

    public function pStmtForeach(Stmt\Foreach_ $node) {
        $this->notImplemented($node->byRef, 'reference by & in foreach value');

        $this->pushDelay();     //expression
        $this->p($node->expr);
        $this->popDelayToVar($expression);

        if ($node->keyVar) {    //key name
            $this->pushDelay();
            $this->p($node->keyVar);
            $this->popDelayToVar($keyName);
        } else {
            $keyName = '_key_';
            $this->closureHelper->pushVar($keyName);
        }

        $this->pushDelay();
        $this->printVarDef();
        $this->popDelayToVar($vars);

        $this->pushDelay();     //value name
        $this->p($node->valueVar);
        $this->popDelayToVar($varName);

        $this->pushDelay();
        $this->printVarDef();
        $this->popDelayToVar($keyVar);

        $this->pushLoop(true);
        $loopName = $this->closureHelper->pushLoop();
        $this->pStmts($node->stmts);
        $this->popLoopPrintName($loopBody);
        $this->closureHelper->popLoop();

        $this->print($vars)
            ->println($loopName . ':')
            ->println('for (%{key} in %{expr}){', $keyName, $expression)
            ->indent()
            // ->println("if (!%{expr}.hasOwnProperty(%{key})) continue;", $expression, $keyName)
            ->println($keyVar)
            ->println('%{varName} = %{expr}[%{key}];', $varName, $expression, $keyName)
            ->print($loopBody)
            ->outdent()
            ->println('}');
    }

    public function pStmtWhile(Stmt\While_ $node) {
        $this->pushLoop(true);
        $lopName = $this->closureHelper->pushLoop();
        $this->pStmts($node->stmts);
        $this->popLoopPrintName($loopBody);
        $this->closureHelper->popLoop();

        $this->pushDelay();
        $this->p($node->cond);
        $this->popDelayToVar($cond);

        $this->println($lopName . ':')
            ->println('while(%{cond}){', $cond)
            ->indent()
            ->print($loopBody)
            ->outdent()
            ->println('}');
    }

    public function pStmtDo(Stmt\Do_ $node) {
        $this->pushLoop(true);
        $loopName = $this->closureHelper->pushLoop();
        $this->pStmts($node->stmts);
        $this->popLoopPrintName($loopBody);
        $this->closureHelper->popLoop();

        $this->pushDelay(false);
        $this->p($node->cond);
        $this->popDelayToVar($cond);

        $this->println($loopName . ':');
        $this->println('do {')
            ->indent()
            ->print($loopBody)
            ->outdent()
            ->println('}while (%{cond});', $cond);
    }

    public function pStmtSwitch(Stmt\Switch_ $node) {
        $this->pushDelay();
        $this->p($node->cond);
        $this->popDelayToVar($cond);

        $this->pushLoop(true);
        $loopName = $this->closureHelper->pushLoop();
        $this->pStmts($node->cases);
        $this->popLoopPrintName($loopBody);
        $this->closureHelper->popLoop();

        $this->println($loopName . ':');
        $this->println('switch (%{cond}){', $cond)
            ->indent()
            ->print($loopBody)
            ->outdent()
            ->println('}');
    }

    public function pStmtCase(Stmt\Case_ $node) {
        if ($node->cond !== null) {
            $this->print('case ');
            $this->p($node->cond);
        } else {
            $this->print('default');
        }
        $this->println(':')
            ->indent();
        $this->pStmts($node->stmts);
        $this->outdent()
            ->println();
    }

    public function pStmtTryCatch(Stmt\TryCatch $node) {
        //TODO: implement this
        $this->println('try{')
            ->indent();
        $this->pStmts($node->stmts);
        $this->outdent();
        $this->println('}catch(__e__){')
            ->indent();

        $catches = [];
        $catchesVars = [];
        foreach ($node->catches as $catch) {
            $this->pushDelay();
            $this->pStmtCatch($catch, $catchesVars);
            $v = null;
            $this->popDelayToVar($v);
            $catches[] = $v;
        }
        $this->println('var %{vars};', join(', ', array_unique($catchesVars)));
        $this->print(join('else ', $catches));
        $this->println('else{');
        $this->indentln('throw __e__;');
        $this->println('}');
        $this->outdent();
        if ($node->finally !== null) {
            $this->println('}finally{')
                ->indent();
            $this->pStmts($node->finally->stmts);
            $this->outdent();
        }
        $this->println('}');
    }

    public function pStmtCatch(Stmt\Catch_ $node, &$catchesVars) {
        $this->pushDelay(false);
        //TODO: implements multiple types in catch
        $this->p($node->types[0]);
        $this->popDelayToVar($type);
        $this->println('if (__e__ instanceof %{type}){', $type)
            ->indent()
            ->println('%{varName}=__e__;', $node->var);
        $this->pStmts($node->stmts);
        $this->outdent()
            ->println('}');

        $this->closureHelper->useVar($node->var);
        $catchesVars[] = $node->var;
    }

    public function pStmtBreak(Stmt\Break_ $node) {
        $name = '';
        if ($node->num !== null) {
            $name = ' ' . $this->closureHelper->getLoopName($node->num->value);
        }
        $this->println('break %{name};', $name);
    }

    public function pStmtContinue(Stmt\Continue_ $node) {
        $name = '';
        if ($node->num !== null) {
            $name = ' ' . $this->closureHelper->getLoopName($node->num->value);
        }
        $this->println('continue %{name};', $name);
    }

    public function pStmtReturn(Stmt\Return_ $node) {
        $this->print('return ');
        if ($node->expr !== null) {
            $this->p($node->expr);
        }
        $this->println(';');
    }

    public function pStmtThrow(Stmt\Throw_ $node) {
        $this->print('throw ');
        $this->p($node->expr);
        $this->println(';');
    }

    public function pStmtLabel(Stmt\Label $node) {
        //TODO: implement this
        $this->notImplemented(true, 'labels:');
    }

    public function pStmtGoto(Stmt\Goto_ $node) {
        //TODO: implement this
        $this->notImplemented(true, 'goto.', true);
        //TODO: implement it. http://stackoverflow.com/questions/9751207/how-can-i-use-goto-in-javascript/23181432#23181432
    }

    public function pStmtEcho(Stmt\Echo_ $node) {
        $this->print('console.log(');
        $this->pCommaSeparated($node->exprs);
        $this->print(');');
    }

    public function pStmtStatic(Stmt\Static_ $node) {
        //TODO: implement this
        $this->notImplemented(true, ' static variables', true);
    }

    public function pStmtGlobal(Stmt\Global_ $node) {
        //TODO: implement this
        foreach ($node->vars as $var) {
            /** @var Expr\Variable $var */
            $this->closureHelper->useVar($var->name);
        }
    }

    public function pStmtStaticVar(Stmt\StaticVar $node) {
        //TODO: implement this
        $this->notImplemented(true, 'static vars', true);
    }

    public function pStmtUnset(Stmt\Unset_ $node) {
        //TODO: implement this
        $this->print('delete ');
        $this->pCommaSeparated($node->vars);
        $this->println(';');
    }

    public function pStmtInlineHTML(Stmt\InlineHTML $node) {
        //TODO: implement this
        $this->notImplemented(true, 'InlineHTML', true);
        //return JS_SCRIPT_END . $this->pNoIndent("\n" . $node->value) . JS_SCRIPT_BEGIN;
    }

    public function pStmtHaltCompiler(Stmt\HaltCompiler $node) {
        //TODO: implement this
        $this->notImplemented(true, ' __halt_compiler()', true);
    }

    public function pStmtNop(Stmt\Nop $node) {
        // TODO: Implement pStmt_Nop() method.
        $this->notImplemented(true, __METHOD__);
    }

    public function pType($node) {
        // TODO: Implement pType() method.
        $this->notImplemented(true, __METHOD__);
    }

    public function pClassCommon(Stmt\Class_ $node, $afterClassToken) {
        // TODO: Implement pClassCommon() method.
        $this->notImplemented(true, __METHOD__);
    }

    public function pObjectProperty($node) {
        //TODO: implement this
        if ($node instanceof Expr) {
            $this->print('[');
            $this->p($node);
            $this->print(']');
        } else {
            $this->print($node);
        }
    }

    public function pModifiers($modifiers) {
        //TODO: implement this
        /*return ($modifiers & Stmt\Class_::MODIFIER_PUBLIC    ? 'public '    : '')
        . ($modifiers & Stmt\Class_::MODIFIER_PROTECTED ? 'protected ' : '')
        . ($modifiers & Stmt\Class_::MODIFIER_PRIVATE   ? 'private '   : '')
        . ($modifiers & Stmt\Class_::MODIFIER_STATIC    ? 'static '    : '')
        . ($modifiers & Stmt\Class_::MODIFIER_ABSTRACT  ? 'abstract '  : '')
        . ($modifiers & Stmt\Class_::MODIFIER_FINAL     ? 'final '     : '');*/
    }

    public function pEncapsList(array $encapsList, $quote) {
        $str = '';
        foreach ($encapsList as $element) {
            if (is_string($element)) {
                $str = addcslashes($element, '\\' . $quote);
                $str = str_replace(PHP_EOL, '\r\n\\' . PHP_EOL, $str);
                $this->print($str);
                /* $str .= addcslashes($element, "\n\r\t\f\v$" . $quote . "\\"); */
            } else {
                if ($element instanceof Scalar\EncapsedStringPart) {
                    if ($element->value == PHP_EOL) {
                        $this->print("\\r\\n\\\n");
                    } else {
                        $this->print($element->value);
                    }

                    continue;
                }
                $this->print('"+');
                $this->p($element);
                $this->print('+"');
            }
        }
    }

    public function pDereferenceLhs(Node $node) {
        // TODO: Implement pDereferenceLhs() method.
        $this->notImplemented(true, __METHOD__);
    }

    public function pCallLhs(Node $node) {
        // TODO: Implement pCallLhs() method.
        $this->notImplemented(true, __METHOD__);
    }

    public function pVarOrNewExpr(Node $node) {
        //TODO: implement this
        if ($node instanceof Expr\New_) {
            $this->print('(');
            $this->p($node);
            $this->print(')');
        } else {
            $this->p($node);
        }
    }

    private function pushLoop($atStart) {
        $this->pushDelay($atStart);
    }

    private function popLoopPrintName(&$body) {
        $this->popDelayToVar($body);
    }

    /**
     * @return $this
     */
    protected function printVarDef() {
        $vars = $this->closureHelper->getVarsDef();
        if (count($vars)) {
            $this->println('var ' . join(',', $vars) . ';');
        }

        return $this;
    }
}
