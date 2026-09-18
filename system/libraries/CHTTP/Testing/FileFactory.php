<?php

/**
 * Description of FileFactory.
 */
class CHTTP_Testing_FileFactory {
    /**
     * Create a new fake file.
     *
     * @param string      $name
     * @param string|int  $kilobytes
     * @param null|string $mimeType
     *
     * @return CHTTP_Testing_File
     */
    public function create($name, $kilobytes = 0, $mimeType = null) {
        if (is_string($kilobytes)) {
            return $this->createWithContent($name, $kilobytes);
        }

        return c::tap(new CHTTP_Testing_File($name, tmpfile()), function ($file) use ($kilobytes, $mimeType) {
            $file->sizeToReport = $kilobytes * 1024;
            $file->mimeTypeToReport = $mimeType;
        });
    }

    /**
     * Create a new fake file with content.
     *
     * @param string $name
     * @param string $content
     *
     * @return CHTTP_Testing_File
     */
    public function createWithContent($name, $content) {
        $tmpfile = tmpfile();

        fwrite($tmpfile, $content);

        return c::tap(new CHTTP_Testing_File($name, $tmpfile), function ($file) use ($tmpfile) {
            $file->sizeToReport = fstat($tmpfile)['size'];
        });
    }

    /**
     * Create a new fake image.
     *
     * @param string $name
     * @param int    $width
     * @param int    $height
     *
     * @return CHTTP_Testing_File
     */
    public function image($name, $width = 10, $height = 10) {
        return new CHTTP_Testing_File(
            $name,
            $this->generateImage(
                $width,
                $height,
                cstr::endsWith(cstr::lower($name), ['.jpg', '.jpeg']) ? 'jpeg' : 'png'
            )
        );
    }

    /**
     * Generate a dummy image of the given width and height.
     *
     * @param int    $width
     * @param int    $height
     * @param string $type
     *
     * @return resource
     */
    protected function generateImage($width, $height, $type) {
        return c::tap(tmpfile(), function ($temp) use ($width, $height, $type) {
            ob_start();

            $image = imagecreatetruecolor($width, $height);

            switch ($type) {
                case 'jpeg':
                    imagejpeg($image);

                    break;
                case 'png':
                    imagepng($image);

                    break;
            }

            fwrite($temp, ob_get_clean());
        });
    }
}
