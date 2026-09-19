<?php

interface CRunner_FFMpeg_Exporter_PlaylistGeneratorInterface {
    public function get(array $playlistMedia, CRunner_FFMpeg_Driver_PHPFFMpeg $driver);
}
