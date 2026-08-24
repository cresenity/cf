<?php

class CImage_OptimizerChainFactory {
    /**
     * @return CImage_OptimizerChain
     */
    public static function create() {
        return (new CImage_OptimizerChain())
            ->addOptimizer(new CImage_Optimizer_Jpegoptim([
                '-m85',
                '--strip-all',
                '--all-progressive',
            ]))
            ->addOptimizer(new CImage_Optimizer_Pngquant([
                '--force',
            ]))
            ->addOptimizer(new CImage_Optimizer_Optipng([
                '-i0',
                '-o2',
                '-quiet',
            ]))
            ->addOptimizer(new CImage_Optimizer_Svgo([
                '--disable={cleanupIDs,removeViewBox}',
            ]))
            ->addOptimizer(new CImage_Optimizer_Gifsicle([
                '-b',
                '-O3',
            ]))
            ->addOptimizer(new CImage_Optimizer_Cwebp([
                '-m 6',
                '-pass 10',
                '-mt',
                '-q 80',
            ]));
    }

    /**
     * Build a chain from the `resource.image_optimizers` config, falling back
     * to the default chain when it is empty.
     *
     * @param null|array $optimizers class name => arguments
     *
     * @return CImage_OptimizerChain
     */
    public static function createFromConfig($optimizers = null) {
        if ($optimizers === null) {
            $optimizers = CF::config('resource.image_optimizers');
        }
        if (!is_array($optimizers) || count($optimizers) == 0) {
            return static::create();
        }

        $chain = new CImage_OptimizerChain();
        foreach ($optimizers as $className => $arguments) {
            $chain->addOptimizer(new $className((array) $arguments));
        }

        return $chain;
    }
}
