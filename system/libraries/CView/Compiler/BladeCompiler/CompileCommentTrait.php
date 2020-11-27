<?php

/**
 * Description of CompileCommentTrait
 *
 * @author Hery
 */
trait CView_Compiler_BladeCompiler_CompileCommentTrait {

    /**
     * Compile Blade comments into an empty string.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileComments($value) {
        $pattern = sprintf('/%s--(.*?)--%s/s', $this->contentTags[0], $this->contentTags[1]);

        return preg_replace($pattern, '', $value);
    }

}
