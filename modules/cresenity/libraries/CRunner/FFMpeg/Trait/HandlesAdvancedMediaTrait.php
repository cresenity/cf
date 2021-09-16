<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan <hery@itton.co.id>
 * @license Ittron Global Teknologi
 *
 * @since Aug 26, 2020
 */

use FFMpeg\Format\FormatInterface;
use ProtoneMedia\LaravelFFMpeg\FFMpeg\AdvancedOutputMapping;
use ProtoneMedia\LaravelFFMpeg\Filesystem\Media;

trait CRunner_FFMpeg_Trait_HandlesAdvancedMediaTrait {
    /**
     * @var \Illuminate\Support\Collection
     */
    protected $maps;

    public function addFormatOutputMapping(FormatInterface $format, Media $output, array $outs, $forceDisableAudio = false, $forceDisableVideo = false) {
        $this->maps->push(
            new CRunner_FFMpeg_AdvancedOutputMapping($outs, $format, $output, $forceDisableAudio, $forceDisableVideo)
        );

        return $this;
    }
}
