<?php

use PHPUnit\Framework\TestCase;

/**
 * Pembangunan rantai optimizer dari config.
 *
 * Yang diuji `CImage_OptimizerChainFactory::createFromConfig()` — config
 * `resource.image_optimizers` menentukan isi rantainya, dan config kosong
 * jatuh ke rantai bawaan.
 */
class Image_OptimizerChainFactoryTest extends TestCase {
    public function testBuildsChainFromGivenOptimizers() {
        $chain = CImage_OptimizerChainFactory::createFromConfig([
            CImage_Optimizer_Jpegoptim::class => ['--strip-all'],
            CImage_Optimizer_Pngquant::class => ['--force'],
        ]);

        $optimizers = $chain->getOptimizers();

        $this->assertCount(2, $optimizers);
        $this->assertInstanceOf(CImage_Optimizer_Jpegoptim::class, $optimizers[0]);
        $this->assertInstanceOf(CImage_Optimizer_Pngquant::class, $optimizers[1]);
    }

    public function testFallsBackToDefaultChainWhenConfigIsEmpty() {
        $optimizers = CImage_OptimizerChainFactory::createFromConfig([])->getOptimizers();

        $this->assertGreaterThan(2, count($optimizers));
    }
}
