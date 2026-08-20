<?php

defined('SYSPATH') or die('No direct access allowed.');

class CManager_Asset_Compiler {
    use CTrait_HasOptions;

    /**
     * @var string[]
     */
    protected $files;

    /**
     * @var string
     */
    protected $outFile;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var int
     */
    protected $maxImportSize;

    /**
     * The last modified time of the newest Asset in the Assets array.
     *
     * @var int
     */
    protected $lastModTimeNewestAsset = 0;

    /**
     * The last modified time of the compiled Asset.
     *
     * @var int
     */
    protected $lastModTimeCompiledAsset = 0;

    /**
     * @var string
     */
    protected $separator = "\n";

    /**
     * @param string[] $files
     * @param array    $options
     */
    public function __construct(array $files, $options = []) {
        $files = carr::map($files, function ($script) {
            return preg_replace('/\?.*/', '', $script);
        });

        $this->files = $files;
        $this->options = $options;
        $this->type = carr::get($options, 'type');

        $this->outFile = carr::get($options, 'outFile');

        if ($this->type == null) {
            $this->determineType();
        }
        if ($this->outFile == null) {
            $this->determineOutFile();
        }
        $this->determineLastModified();
    }

    /**
     * @return void
     */
    protected function determineType() {
        $firstFile = carr::first($this->files);
        $extension = pathinfo($firstFile, PATHINFO_EXTENSION);
        $this->type = strtolower($extension);
    }

    /**
     * @return void
     */
    protected function determineOutFile() {
        $firstFile = carr::first($this->files);
        $ymd = date('Ymd', filemtime($firstFile));
        $this->outFile = DOCROOT . 'compiled/asset/' . $this->type . '/' . $ymd . '/' . md5(implode(':', $this->files)) . '.' . $this->type;
    }

    /**
     * @return void
     */
    protected function determineLastModified() {
        //Set the instance variable to store the last modified time of the newest file
        $this->lastModTimeNewestAsset = 0;
        foreach ($this->files as $file) {
            if (!file_exists($file)) {
                throw new Exception('Error to compile asset, ' . $file . ' not exist');
            }
            $mTime = filemtime($file);
            $this->lastModTimeNewestAsset = $mTime > $this->lastModTimeNewestAsset ? $mTime : $this->lastModTimeNewestAsset;
        }

        $this->lastModTimeCompiledAsset = 0;
        if (file_exists($this->outFile)) {
            $this->lastModTimeCompiledAsset = filemtime($this->outFile);
        }
    }

    /**
     * @return string
     */
    protected function outputPath() {
        return 'compiled';
    }

    /**
     * @return bool
     */
    public function needToRecompile() {
        return $this->lastModTimeCompiledAsset < $this->lastModTimeNewestAsset;
    }

    /**
     * @return string
     */
    public function compile() {
        if ($this->needToRecompile()) {
            $dirname = dirname($this->outFile);
            if (!is_dir($dirname)) {
                CFile::makeDirectory($dirname, 0755, true);
            }

            //ditulis ke berkas sementara lalu dipindahkan sekali jalan; sebelumnya berkas
            //tujuan dikosongkan dulu dan diisi bertahap, sehingga permintaan yang datang
            //bersamaan dapat menerima bundel kosong atau separuh jadi
            $tempFile = $dirname . DS . uniqid(basename($this->outFile) . '.', true) . '.tmp';
            foreach ($this->files as $file) {
                $compiledOutput = file_get_contents($file);
                // strip BOM, if any
                if (substr($compiledOutput, 0, 3) == "\xef\xbb\xbf") {
                    $compiledOutput = substr($compiledOutput, 3);
                }
                if ($this->type == 'css') {
                    $converter = $this->createConverter($file, $this->outFile);
                    $compiledOutput = $this->move($converter, $compiledOutput);

                    if (CF::config('assets.css.minify')) {
                        $minifier = $this->createCssMinifier();
                        $compiledOutput = $minifier->execute($compiledOutput);
                    }
                }

                if ($this->type == 'js') {
                    if (CF::config('assets.js.minify')) {
                        $minifier = $this->createJsMinifier();
                        $compiledOutput = $minifier->execute($compiledOutput);
                    }
                }

                file_put_contents($tempFile, $this->separator . $compiledOutput, FILE_APPEND);
            }

            if (!@rename($tempFile, $this->outFile)) {
                @unlink($tempFile);
            }
            clearstatcache(true, $this->outFile);
        }

        return $this->outFile . '?v=' . $this->version();
    }

    /**
     * Versi bundel. Identitas rilis dipakai lebih dulu supaya seluruh server di belakang
     * load balancer menyebut versi yang sama untuk isi yang sama.
     *
     * @return string
     */
    protected function version() {
        $release = CManager_Asset_Helper::getReleaseVersion();
        if ($release !== null) {
            return $release;
        }

        return file_exists($this->outFile) ? (string) filemtime($this->outFile) : '0';
    }

    /**
     * Moving a css file should update all relative urls.
     * Relative references (e.g. ../images/image.gif) in a certain css file,
     * will have to be updated when a file is being saved at another location
     * (e.g. ../../images/image.gif, if the new CSS file is 1 folder deeper).
     *
     * @param CManager_Asset_Compiler_PathConverter $converter Relative path converter
     * @param string                                $content   The CSS content to update relative urls for
     *
     * @return string
     */
    protected function move(CManager_Asset_Compiler_PathConverter $converter, $content) {
        /*
         * Relative path references will usually be enclosed by url(). @import
         * is an exception, where url() is not necessary around the path (but is
         * allowed).
         * This *could* be 1 regular expression, where both regular expressions
         * in this array are on different sides of a |. But we're using named
         * patterns in both regexes, the same name on both regexes. This is only
         * possible with a (?J) modifier, but that only works after a fairly
         * recent PCRE version. That's why I'm doing 2 separate regular
         * expressions & combining the matches after executing of both.
         */
        $relativeRegexes = [
            // url(xxx)
            '/
            # open url()
            url\(
                \s*
                # open path enclosure
                (?P<quotes>["\'])?
                    # fetch path
                    (?P<path>.+?)
                # close path enclosure
                (?(quotes)(?P=quotes))
                \s*
            # close url()
            \)
            /ix',
            // @import "xxx"
            '/
            # import statement
            @import
            # whitespace
            \s+
                # we don\'t have to check for @import url(), because the
                # condition above will already catch these
                # open path enclosure
                (?P<quotes>["\'])
                    # fetch path
                    (?P<path>.+?)
                # close path enclosure
                (?P=quotes)
            /ix',
        ];

        // find all relative urls in css
        $matches = [];
        foreach ($relativeRegexes as $relativeRegex) {
            if (preg_match_all($relativeRegex, $content, $regexMatches, PREG_SET_ORDER)) {
                $matches = array_merge($matches, $regexMatches);
            }
        }

        $search = [];
        $replace = [];

        // loop all urls
        foreach ($matches as $match) {
            // determine if it's a url() or an @import match
            $type = (strpos($match[0], '@import') === 0 ? 'import' : 'url');

            $url = $match['path'];
            if ($this->canImportByPath($url)) {
                // attempting to interpret GET-params makes no sense, so let's discard them for awhile
                $params = strrchr($url, '?');
                $url = $params ? substr($url, 0, -strlen($params)) : $url;

                // fix relative url
                $url = $converter->convert($url);

                // now that the path has been converted, re-apply GET-params
                $url .= $params;
            }

            /*
             * Urls with control characters above 0x7e should be quoted.
             * According to Mozilla's parser, whitespace is only allowed at the
             * end of unquoted urls.
             * Urls with `)` (as could happen with data: uris) should also be
             * quoted to avoid being confused for the url() closing parentheses.
             * And urls with a # have also been reported to cause issues.
             * Urls with quotes inside should also remain escaped.
             *
             * @see https://developer.mozilla.org/nl/docs/Web/CSS/url#The_url()_functional_notation
             * @see https://hg.mozilla.org/mozilla-central/rev/14abca4e7378
             * @see https://github.com/matthiasmullie/minify/issues/193
             */
            $url = trim($url);
            if (preg_match('/[\s\)\'"#\x{7f}-\x{9f}]/u', $url)) {
                $url = $match['quotes'] . $url . $match['quotes'];
            }

            // build replacement
            $search[] = $match[0];
            if ($type === 'url') {
                $replace[] = 'url(' . $url . ')';
            } elseif ($type === 'import') {
                $replace[] = '@import "' . $url . '"';
            }
        }

        // replace urls
        return str_replace($search, $replace, $content);
    }

    /**
     * Return a converter to update relative paths to be relative to the new
     * destination.
     *
     * @param string $source
     * @param string $target
     *
     * @return CManager_Asset_Compiler_PathConverter
     */
    public function createConverter($source, $target) {
        return new CManager_Asset_Compiler_PathConverter($source, $target);
    }

    /**
     * Check if file a file can be imported, going by the path.
     *
     * @param string $path
     *
     * @return bool
     */
    protected function canImportByPath($path) {
        return preg_match('/^(data:|https?:|\\/)/', $path) === 0;
    }

    /**
     * @return \CManager_Asset_Compiler_Minify_MinifyCss
     */
    protected function createCssMinifier() {
        return new CManager_Asset_Compiler_Minify_MinifyCss();
    }

    /**
     * @return \CManager_Asset_Compiler_Minify_MinifyJs
     */
    protected function createJsMinifier() {
        return new CManager_Asset_Compiler_Minify_MinifyJs();
    }
}
