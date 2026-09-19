<?php

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Scalar;
use PhpParser\ParserFactory;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\PrettyPrinterAbstract;
use PhpParser\Node\Scalar\MagicConst;

abstract class CJavascript_PhpJs_JsPrinterAbstract extends PrettyPrinterAbstract {
    public static $enableVariadic = false;

    public static $throwErrors = true;

    /**
     * @var SourceWriter
     */
    protected $writer;

    protected $ROOT_PATH_FROM = null;

    protected $ROOT_PATH_TO = null;

    protected $ROOT_PATH_TO_EXT = null;

    protected $isOnlyJsFile = false;

    protected $errors = [];

    /**
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }

    protected function notImplemented($expression, $message, $throw = false) {
        if ($expression) {
            $msg = 'not implemented ' . $message;
            $this->errors[] = $msg;
            if ($throw) {
                if (self::$throwErrors == true) {
                    throw new \RuntimeException($msg);
                } else {
                }
            }
        }
    }

    /**
     * Pretty prints a file of statements (includes the opening <?php tag if it is required).
     *
     * @param string $filePath
     * @param bool   $isOnlyJsFile
     *
     * @return string Pretty printed statements
     */
    public function jsPrintFile($filePath, $isOnlyJsFile = false) {
        $this->ROOT_PATH_FROM = dirname($filePath) . DIRECTORY_SEPARATOR;
        $this->isOnlyJsFile = $isOnlyJsFile;

        $code = file_get_contents($filePath);

        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $stmts = $parser->parse($code);

        $code = $this->jsPrint($stmts);

        if (!$isOnlyJsFile) {
            $code = CJavascript_PhpJs::JS_SCRIPT_BEGIN . $code . CJavascript_PhpJs::JS_SCRIPT_END;
        }
        $code = str_replace(CJavascript_PhpJs::JS_SCRIPT_BEGIN . CJavascript_PhpJs::JS_SCRIPT_END, '', $code);

        return $code;
    }

    public function jsPrintFileTo($filePath, $dstFilePath) {
        $this->ROOT_PATH_TO = dirname($dstFilePath) . DIRECTORY_SEPARATOR;
        $this->ROOT_PATH_TO_EXT = pathinfo($dstFilePath, PATHINFO_EXTENSION);
        $isOnlyJsFile = $this->ROOT_PATH_TO_EXT == 'js';
        $code = $this->jsPrintFile($filePath, $isOnlyJsFile);
        if (!file_exists(dirname($dstFilePath))) {
            mkdir(dirname($dstFilePath), 0777, true);
        }

        return file_put_contents($dstFilePath, $code);
    }

    /**
     * Pretty prints a node.
     *
     * @param Node $node Node to be pretty printed
     *
     * @return void
     */
    protected function p(Node $node, $parentFormatPreserved = false): string {
        $this->{'p' . $this->sanitizeType($node->getType())}($node);

        return '';
    }

    /**
     * Pretty prints an array of statements.
     *
     * @param Node[] $stmts Array of statements
     *
     * @return string Pretty printed statements
     */
    public function jsPrint(array $stmts) {
        $this->pStmts($stmts, false);

        return $this->writer->getResetCode();
    }

    /**
     * @param array $nodes
     * @param bool  $indent
     *
     * @see PhpParser\Printer\PrinterAbstract::pStmts
     *
     * @return string
     */
    protected function pStmts(array $nodes, $indent = true): string {
        foreach ($nodes as $node) {
            $comments = $node->getAttribute('comments', []);
            if ($comments && !($node instanceof Stmt\ClassMethod || $node instanceof Stmt\ClassConst)) {
                $this->pComments($comments);
            }

            $this->writer->pushDelay();
            $this->p($node);
            $this->writer->popDelayToVar($stmts);

            $this->printVarDef();
            $this->printUseByRefDef();
            $this->writer->print($stmts);

            $this->writer->println($node instanceof Node\Expr ? ';' : '');
        }

        return '';
    }

    abstract protected function printUseByRefDef();

    abstract protected function printVarDef();

    /**
     * @param \PhpParser\Comment[] $comments
     *
     * @return void|string
     */
    protected function pComments(array $comments): string {
        foreach ($comments as $comment) {
            $comment = $comment->getReformattedText();
            $comment = preg_replace('/(@(param|var) )([\w\|\\\\]+)( \$\w*)?/', '$1{$3}$4', $comment);
            $comment = preg_replace('/(@(param|var) )({[\w\|\\\\]*} )?\$(\w*)/', '$1$3$4', $comment);
            $comment = str_replace(['@var', '{\\', '\\'], ['@type', '{N.', '.'], $comment);
            $this->writer->println($comment);
        }

        return '';
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

        $this->pPrec($leftNode, $precedence, $associativity, -1);
        if (gettype($operator) == 'integer') {
            $this->writer->writeDelay($operator);
        } else {
            $this->writer->print($operator);
        }
        $this->pPrec($rightNode, $precedence, $associativity, 1);

        return '';
    }

    /**
     * @param $type
     * @param $operatorString
     * @param \PhpParser\Node $node
     *
     * @see PhpParser\Printer\PrinterAbstract::pPrefixOp
     *
     * @return void
     */
    protected function pPrefixOp($type, $operatorString, Node $node): string {
        list($precedence, $associativity) = $this->precedenceMap[$type];
        $this->writer->print($operatorString);
        $this->pPrec($node, $precedence, $associativity, 1);

        return '';
    }

    /**
     * @param $type
     * @param \PhpParser\Node $node
     * @param $operatorString
     *
     * @see PhpParser\Printer\PrinterAbstract::pPostfixOp
     *
     * @return void
     */
    protected function pPostfixOp($type, Node $node, $operatorString): string {
        list($precedence, $associativity) = $this->precedenceMap[$type];
        $this->pPrec($node, $precedence, $associativity, -1);
        $this->writer->print($operatorString);

        return '';
    }

    /**
     * @param \PhpParser\Node $node
     * @param int             $parentPrecedence
     * @param int             $parentAssociativity
     * @param int             $childPosition
     *
     * @see PhpParser\Printer\PrinterAbstract::pPrec
     *
     * @return void
     */
    protected function pPrec(Node $node, $parentPrecedence, $parentAssociativity, $childPosition): string {
        $type = $node->getType();
        if (isset($this->precedenceMap[$type])) {
            $childPrecedence = $this->precedenceMap[$type][0];
            if ($childPrecedence > $parentPrecedence
                || ($parentPrecedence == $childPrecedence && $parentAssociativity != $childPosition)
            ) {
                $this->writer->print('(');
                $this->{'p' . $type}($node);
                $this->writer->print(')');

                return '';
            }
        }

        $this->{'p' . $this->sanitizeType($type)}($node);

        return '';
    }

    /**
     * @param array  $nodes
     * @param string $glue
     *
     * @return void
     */
    protected function pImplode(array $nodes, $glue = ''): string {
        $l = count($nodes);
        for ($i = 0; $i < $l; $i++) {
            $node = $nodes[$i];
            $this->p($node);
            if ($i < $l - 1) {
                $this->writer->print($glue);
            }
        }

        return '';
    }

    public function pScalarLNumber(Scalar\LNumber $node) {
        $this->print((string) $node->value);
    }

    public function pScalarDNumber(Scalar\DNumber $node) {
        $stringValue = (string) $node->value;
        if ($stringValue == 'INF') {
            $stringValue = 'Infinity';
        }
        // ensure that number is really printed as float
        $stringValue = ctype_digit($stringValue) ? $stringValue . '.0' : $stringValue;
        $this->print($stringValue);
    }

    public function pExprAssign(Expr\Assign $node) {
        $this->pInfixOp('Expr_Assign', $node->var, ' = ', $node->expr);
    }

    public function pExprAssignRef(Expr\AssignRef $node) {
        $this->pInfixOp('Expr_AssignRef', $node->var, ' =& ', $node->expr);
    }

    public function pExprAssignOpPlus(AssignOp\Plus $node) {
        $this->pInfixOp('Expr_AssignOp_Plus', $node->var, ' += ', $node->expr);
    }

    public function pExprAssignOpMinus(AssignOp\Minus $node) {
        $this->pInfixOp('Expr_AssignOp_Minus', $node->var, ' -= ', $node->expr);
    }

    public function pExprAssignOpMul(AssignOp\Mul $node) {
        $this->pInfixOp('Expr_AssignOp_Mul', $node->var, ' *= ', $node->expr);
    }

    public function pExprAssignOpDiv(AssignOp\Div $node) {
        $this->pInfixOp('Expr_AssignOp_Div', $node->var, ' /= ', $node->expr);
    }

    public function pExprAssignOpConcat(AssignOp\Concat $node) {
        $this->pInfixOp('Expr_AssignOp_Concat', $node->var, ' += ', $node->expr);
    }

    public function pExprAssignOpMod(AssignOp\Mod $node) {
        $this->pInfixOp('Expr_AssignOp_Mod', $node->var, ' %= ', $node->expr);
    }

    public function pExprAssignOpBitwiseAnd(AssignOp\BitwiseAnd $node) {
        $this->pInfixOp('Expr_AssignOp_BitwiseAnd', $node->var, ' &= ', $node->expr);
    }

    public function pExprAssignOpBitwiseOr(AssignOp\BitwiseOr $node) {
        $this->pInfixOp('Expr_AssignOp_BitwiseOr', $node->var, ' |= ', $node->expr);
    }

    public function pExprAssignOpBitwiseXor(AssignOp\BitwiseXor $node) {
        $this->pInfixOp('Expr_AssignOp_BitwiseXor', $node->var, ' ^= ', $node->expr);
    }

    public function pExprAssignOpShiftLeft(AssignOp\ShiftLeft $node) {
        $this->pInfixOp('Expr_AssignOp_ShiftLeft', $node->var, ' <<= ', $node->expr);
    }

    public function pExprAssignOpShiftRight(AssignOp\ShiftRight $node) {
        $this->pInfixOp('Expr_AssignOp_ShiftRight', $node->var, ' >>= ', $node->expr);
    }

    public function pExprAssignOpPow(AssignOp\Pow $node) {
        //TODO: implement this
        $this->pInfixOp('Expr_AssignOp_Pow', $node->var, ' **= ', $node->expr);
    }

    public function pExprBinaryOpPlus(BinaryOp\Plus $node) {
        $this->pInfixOp('Expr_BinaryOp_Plus', $node->left, ' + ', $node->right);
    }

    public function pExprBinaryOpMinus(BinaryOp\Minus $node) {
        $this->pInfixOp('Expr_BinaryOp_Minus', $node->left, ' - ', $node->right);
    }

    public function pExprBinaryOpMul(BinaryOp\Mul $node) {
        $this->pInfixOp('Expr_BinaryOp_Mul', $node->left, ' * ', $node->right);
    }

    public function pExprBinaryOpDiv(BinaryOp\Div $node) {
        $this->pInfixOp('Expr_BinaryOp_Div', $node->left, ' / ', $node->right);
    }

    public function pExprBinaryOpConcat(BinaryOp\Concat $node) {
        if ($this->isConcatString($node)) {
            $this->p($node->left);
            $this->writer->print('+');
            $this->p($node->right);
        } else {
            $this->pInfixOp('Expr_BinaryOp_Concat', $node->left, ' . ', $node->right);
        }
    }

    private function isConcatString(BinaryOp\Concat $node) {
        if ($node->left instanceof BinaryOp\Concat) {
            if ($this->isConcatString($node->left)) {
                return true;
            }
        }
        if ($node->right instanceof BinaryOp\Concat) {
            if ($this->isConcatString($node->right)) {
                return true;
            }
        }
        if ($node->left instanceof Scalar\String_ || $node->right instanceof Scalar\String_) {
            return true;
        }

        return false;
    }

    public function pExprBinaryOpMod(BinaryOp\Mod $node) {
        $this->pInfixOp('Expr_BinaryOp_Mod', $node->left, ' % ', $node->right);
    }

    public function pExprBinaryOpBooleanAnd(BinaryOp\BooleanAnd $node) {
        $this->pInfixOp('Expr_BinaryOp_BooleanAnd', $node->left, ' && ', $node->right);
    }

    public function pExprBinaryOpBooleanOr(BinaryOp\BooleanOr $node) {
        $this->pInfixOp('Expr_BinaryOp_BooleanOr', $node->left, ' || ', $node->right);
    }

    public function pExprBinaryOpBitwiseAnd(BinaryOp\BitwiseAnd $node) {
        $this->pInfixOp('Expr_BinaryOp_BitwiseAnd', $node->left, ' & ', $node->right);
    }

    public function pExprBinaryOpBitwiseOr(BinaryOp\BitwiseOr $node) {
        $this->pInfixOp('Expr_BinaryOp_BitwiseOr', $node->left, ' | ', $node->right);
    }

    public function pExprBinaryOpBitwiseXor(BinaryOp\BitwiseXor $node) {
        $this->pInfixOp('Expr_BinaryOp_BitwiseXor', $node->left, ' ^ ', $node->right);
    }

    public function pExprBinaryOpShiftLeft(BinaryOp\ShiftLeft $node) {
        $this->pInfixOp('Expr_BinaryOp_ShiftLeft', $node->left, ' << ', $node->right);
    }

    public function pExprBinaryOpShiftRight(BinaryOp\ShiftRight $node) {
        $this->pInfixOp('Expr_BinaryOp_ShiftRight', $node->left, ' >> ', $node->right);
    }

    public function pExprBinaryOpPow(BinaryOp\Pow $node) {
        //TODO: implement this
        $this->pInfixOp('Expr_BinaryOp_Pow', $node->left, ' ** ', $node->right);
    }

    public function pExprBinaryOpLogicalAnd(BinaryOp\LogicalAnd $node) {
        $this->pInfixOp('Expr_BinaryOp_LogicalAnd', $node->left, ' && ', $node->right);
    }

    public function pExprBinaryOpLogicalOr(BinaryOp\LogicalOr $node) {
        $this->pInfixOp('Expr_BinaryOp_LogicalOr', $node->left, ' || ', $node->right);
    }

    public function pExprBinaryOpLogicalXor(BinaryOp\LogicalXor $node) {
        //TODO: implement this
        $this->pInfixOp('Expr_BinaryOp_LogicalXor', $node->left, ' ^ ', $node->right);
    }

    public function pExprBinaryOpEqual(BinaryOp\Equal $node) {
        $this->pInfixOp('Expr_BinaryOp_Equal', $node->left, ' == ', $node->right);
    }

    public function pExprBinaryOpNotEqual(BinaryOp\NotEqual $node) {
        $this->pInfixOp('Expr_BinaryOp_NotEqual', $node->left, ' != ', $node->right);
    }

    public function pExprBinaryOpIdentical(BinaryOp\Identical $node) {
        $this->pInfixOp('Expr_BinaryOp_Identical', $node->left, ' === ', $node->right);
    }

    public function pExprBinaryOpNotIdentical(BinaryOp\NotIdentical $node) {
        $this->pInfixOp('Expr_BinaryOp_NotIdentical', $node->left, ' !== ', $node->right);
    }

    public function pExprBinaryOpSpaceship(BinaryOp\Spaceship $node) {
        //TODO: Implement pExpr_BinaryOp_Spaceship() method.
        $this->notImplemented(true, __METHOD__);
    }

    public function pExprBinaryOpGreater(BinaryOp\Greater $node) {
        $this->pInfixOp('Expr_BinaryOp_Greater', $node->left, ' > ', $node->right);
    }

    public function pExprBinaryOpGreaterOrEqual(BinaryOp\GreaterOrEqual $node) {
        $this->pInfixOp('Expr_BinaryOp_GreaterOrEqual', $node->left, ' >= ', $node->right);
    }

    public function pExprBinaryOpSmaller(BinaryOp\Smaller $node) {
        $this->pInfixOp('Expr_BinaryOp_Smaller', $node->left, ' < ', $node->right);
    }

    public function pExprBinaryOpSmallerOrEqual(BinaryOp\SmallerOrEqual $node) {
        $this->pInfixOp('Expr_BinaryOp_SmallerOrEqual', $node->left, ' <= ', $node->right);
    }

    public function pExprBinaryOpCoalesce(BinaryOp\Coalesce $node) {
        // TODO: Implement pExpr_BinaryOp_Coalesce() method.
        $this->notImplemented(true, __METHOD__);
    }

    public function pExprBooleanNot(Expr\BooleanNot $node) {
        $this->pPrefixOp('Expr_BooleanNot', '!', $node->expr);
    }

    public function pExprBitwiseNot(Expr\BitwiseNot $node) {
        //TODO: implement this
        $this->pPrefixOp('Expr_BitwiseNot', '~', $node->expr);
    }

    public function pExprUnaryMinus(Expr\UnaryMinus $node) {
        $this->pPrefixOp('Expr_UnaryMinus', '-', $node->expr);
    }

    public function pExprUnaryPlus(Expr\UnaryPlus $node) {
        $this->pPrefixOp('Expr_UnaryPlus', '+', $node->expr);
    }

    public function pExprPreInc(Expr\PreInc $node) {
        $this->pPrefixOp('Expr_PreInc', '++', $node->var);
    }

    public function pExprPreDec(Expr\PreDec $node) {
        $this->pPrefixOp('Expr_PreDec', '--', $node->var);
    }

    public function pExprPostInc(Expr\PostInc $node) {
        $this->pPostfixOp('Expr_PostInc', $node->var, '++');
    }

    public function pExprPostDec(Expr\PostDec $node) {
        $this->pPostfixOp('Expr_PostDec', $node->var, '--');
    }

    public function pExprErrorSuppress(Expr\ErrorSuppress $node) {
        //TODO: implement this
        $this->notImplemented(true, 'ErrorSuppress by @', true);
        $this->pPrefixOp('Expr_ErrorSuppress', '@', $node->expr);
    }

    public function pExprYieldFrom(Expr\YieldFrom $node) {
        // TODO: Implement pExpr_YieldFrom() method.
        $this->notImplemented(true, __METHOD__);
    }

    public function pExprPrint(Expr\Print_ $node) {
        // TODO: Implement pExpr_Print() method.
        $this->print('console.log(');
        $this->p($node->expr);
        $this->print(')');
    }

    public function pExprCastInt(Cast\Int_ $node) {
        $this->print('parseInt(');
        $this->p($node->expr);
        $this->print(')');
    }

    public function pExprCastDouble(Cast\Double $node) {
        $this->print('parseFloat(');
        $this->p($node->expr);
        $this->print(')');
    }

    public function pExprCastString(Cast\String_ $node) {
        $this->print('(');
        $this->p($node->expr);
        $this->print(').toString()');
    }

    public function pExprCastArray(Cast\Array_ $node) {
        //TODO: implement this
        $this->notImplemented(true, ' conversion to (array)', true);
        $this->pPrefixOp('Expr_Cast_Array', '(array) ', $node->expr);
    }

    public function pExprCastObject(Cast\Object_ $node) {
        //TODO: implement this
        $this->notImplemented(true, ' conversion to (object)', true);
        $this->pPrefixOp('Expr_Cast_Object', '(object) ', $node->expr);
    }

    public function pExprCastBool(Cast\Bool_ $node) {
        return 'Boolean(' . $this->p($node->expr) . ')';
    }

    public function pExprCastUnset(Cast\Unset_ $node) {
        //TODO: implement this
        $this->notImplemented(true, __METHOD__);
        $this->pPrefixOp('Expr_Cast_Unset', 'delete ', $node->expr);
    }

    public function pExprEmpty(Expr\Empty_ $node) {
        //TODO: implement this
        $this->print('empty(');
        $this->p($node->expr);
        $this->print(')');
    }

    public function pExprIsset(Expr\Isset_ $node) {
        //TODO: implement this
        $this->print('isset(');
        $this->pCommaSeparated($node->vars);
        $this->print(')');
    }

    public function pExprEval(Expr\Eval_ $node) {
        //TODO: implement this
        $this->print('eval(');
        $this->p($node->expr);
        $this->print(')');
    }

    /**
     * @param null $atStart
     *
     * @return JsPrinter
     */
    public function pushDelay($atStart = null) {
        call_user_func_array([$this->writer, __FUNCTION__], func_get_args());

        return $this;
    }

    /**
     * @param null $id
     *
     * @return JsPrinter
     */
    public function popDelay(&$id = null) {
        $this->writer->popDelay($id);

        return $this;
    }

    /**
     * @param $var
     *
     * @return $this
     */
    public function popDelayToVar(&$var) {
        $this->writer->popDelayToVar($var);

        return $this;
    }

    /**
     * @param $id
     *
     * @return JsPrinter
     */
    public function writeDelay($id) {
        call_user_func_array([$this->writer, __FUNCTION__], func_get_args());

        return $this;
    }

    /**
     * @return JsPrinter
     */
    public function writeLastDelay() {
        call_user_func_array([$this->writer, __FUNCTION__], func_get_args());

        return $this;
    }

    /**
     * @param $string
     * @param ... $objects
     *
     * @return JsPrinter
     */
    public function println($string = '', $objects = null) {
        call_user_func_array([$this->writer, __FUNCTION__], func_get_args());

        return $this;
    }

    /**
     * @param $string
     * @param ... $objects
     *
     * @return JsPrinter
     */
    public function print($string, $objects = null) {
        call_user_func_array([$this->writer, __FUNCTION__], func_get_args());

        return $this;
    }

    /**
     * @return JsPrinter
     */
    public function indent() {
        call_user_func_array([$this->writer, __FUNCTION__], func_get_args());

        return $this;
    }

    /**
     * @return JsPrinter
     */
    public function outdent() {
        call_user_func_array([$this->writer, __FUNCTION__], func_get_args());

        return $this;
    }

    /**
     * @param $string
     * @param ... $objects
     *
     * @return JsPrinter
     */
    public function indentln($string, $objects = null) {
        call_user_func_array([$this->writer, __FUNCTION__], func_get_args());

        return $this;
    }

    public function sanitizeType($type) {
        return str_replace('_', '', $type);
    }
}
