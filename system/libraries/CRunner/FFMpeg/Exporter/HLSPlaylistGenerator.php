<?php

class CRunner_FFMpeg_Exporter_HLSPlaylistGenerator implements CRunner_FFMpeg_Exporter_PlaylistGeneratorInterface {
    const PLAYLIST_START = '#EXTM3U';
    const PLAYLIST_END = '#EXT-X-ENDLIST';

    /**
     * Extracts the framerate from the given media and formats it in a
     * suitable format.
     *
     * @param CRunner_FFMpeg $media
     *
     * @return mixed
     */
    private function getFrameRate(CRunner_FFMpeg $media) {
        $mediaStream = $media->getVideoStream();

        $frameRate = trim(cstr::before(c::optional($mediaStream)->get('avg_frame_rate'), '/1'));

        if (!$frameRate || cstr::endsWith($frameRate, '/0')) {
            return null;
        }

        return $frameRate ? number_format($frameRate, 3, '.', '') : null;
    }

    /**
     * Return the line from the master playlist that references the given segment playlist.
     *
     * @param CRunner_FFMpeg_Media $playlistMedia
     * @param string               $key
     *
     * @return string
     */
    private function getStreamInfoLine(CRunner_FFMpeg_Media $segmentPlaylistMedia, $key) {
        $segmentPlaylist = $segmentPlaylistMedia->getDisk()->get(
            $segmentPlaylistMedia->getDirectory() . CRunner_FFMpeg_Exporter_HLSExporter::generateTemporarySegmentPlaylistFilename($key, $segmentPlaylistMedia)
        );

        $lines = DynamicHLSPlaylist::parseLines($segmentPlaylist)->filter();

        return $lines->get($lines->search($segmentPlaylistMedia->getFilename()) - 1);
    }

    /**
     * Loops through all segment playlists and generates a main playlist. It finds
     * the relative paths to the segment playlists and adds the framerate when
     * to each playlist.
     *
     * @param array                                         $segmentPlaylists
     * @param CRunner_FFMpeg_Driver_PHPFFMpeg $driver
     *
     * @return string
     */
    public function get(array $segmentPlaylists, CRunner_FFMpeg_Driver_PHPFFMpeg $driver): string {
        return Collection::make($segmentPlaylists)->map(function (Media $segmentPlaylist, $key) use ($driver) {
            $streamInfoLine = $this->getStreamInfoLine($segmentPlaylist, $key);

            $media = (new MediaOpener($segmentPlaylist->getDisk(), $driver))
                ->openWithInputOptions($segmentPlaylist->getPath(), ['-allowed_extensions', 'ALL']);

            if ($frameRate = $this->getFrameRate($media)) {
                $streamInfoLine .= ",FRAME-RATE={$frameRate}";
            }

            return [$streamInfoLine, $segmentPlaylist->getFilename()];
        })->collapse()
            ->prepend(static::PLAYLIST_START)
            ->push(static::PLAYLIST_END)
            ->implode(PHP_EOL);
    }
}
