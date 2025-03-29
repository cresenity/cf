<?php

class CBase_Vite implements CInterface_Htmlable {
    use CTrait_Macroable;

    /**
     * The Content Security Policy nonce to apply to all generated tags.
     *
     * @var null|string
     */
    protected $nonce;

    /**
     * The key to check for integrity hashes within the manifest.
     *
     * @var string|false
     */
    protected $integrityKey = 'integrity';

    /**
     * The configured entry points.
     *
     * @var array
     */
    protected $entryPoints = [];

    /**
     * The path to the "hot" file.
     *
     * @var null|string
     */
    protected $hotFile;

    /**
     * The path to the build directory.
     *
     * @var string
     */
    protected $buildDirectory = 'build';

    /**
     * The name of the manifest file.
     *
     * @var string
     */
    protected $manifestFilename = 'manifest.json';

    /**
     * The script tag attributes resolvers.
     *
     * @var array
     */
    protected $scriptTagAttributesResolvers = [];

    /**
     * The style tag attributes resolvers.
     *
     * @var array
     */
    protected $styleTagAttributesResolvers = [];

    /**
     * The preload tag attributes resolvers.
     *
     * @var array
     */
    protected $preloadTagAttributesResolvers = [];

    /**
     * The preloaded assets.
     *
     * @var array
     */
    protected $preloadedAssets = [];

    /**
     * The cached manifest files.
     *
     * @var array
     */
    protected static $manifests = [];

    /**
     * Get the preloaded assets.
     *
     * @return array
     */
    public function preloadedAssets() {
        return $this->preloadedAssets;
    }

    /**
     * Get the Content Security Policy nonce applied to all generated tags.
     *
     * @return null|string
     */
    public function cspNonce() {
        return $this->nonce;
    }

    /**
     * Generate or set a Content Security Policy nonce to apply to all generated tags.
     *
     * @param null|string $nonce
     *
     * @return string
     */
    public function useCspNonce($nonce = null) {
        return $this->nonce = $nonce ?? cstr::random(40);
    }

    /**
     * Use the given key to detect integrity hashes in the manifest.
     *
     * @param string|false $key
     *
     * @return $this
     */
    public function useIntegrityKey($key) {
        $this->integrityKey = $key;

        return $this;
    }

    /**
     * Set the Vite entry points.
     *
     * @param array $entryPoints
     *
     * @return $this
     */
    public function withEntryPoints($entryPoints) {
        $this->entryPoints = $entryPoints;

        return $this;
    }

    /**
     * Set the filename for the manifest file.
     *
     * @param string $filename
     *
     * @return $this
     */
    public function useManifestFilename($filename) {
        $this->manifestFilename = $filename;

        return $this;
    }

    /**
     * Get the Vite "hot" file path.
     *
     * @return string
     */
    public function hotFile() {
        return $this->hotFile ?? CF::publicPath('/hot');
    }

    /**
     * Set the Vite "hot" file path.
     *
     * @param string $path
     *
     * @return $this
     */
    public function useHotFile($path) {
        $this->hotFile = $path;

        return $this;
    }

    /**
     * Set the Vite build directory.
     *
     * @param string $path
     *
     * @return $this
     */
    public function useBuildDirectory($path) {
        $this->buildDirectory = $path;

        return $this;
    }

    /**
     * Use the given callback to resolve attributes for script tags.
     *
     * @param  (callable(string, string, ?array, ?array): array)|array  $attributes
     *
     * @return $this
     */
    public function useScriptTagAttributes($attributes) {
        if (!is_callable($attributes)) {
            $attributes = fn () => $attributes;
        }

        $this->scriptTagAttributesResolvers[] = $attributes;

        return $this;
    }

    /**
     * Use the given callback to resolve attributes for style tags.
     *
     * @param  (callable(string, string, ?array, ?array): array)|array  $attributes
     *
     * @return $this
     */
    public function useStyleTagAttributes($attributes) {
        if (!is_callable($attributes)) {
            $attributes = fn () => $attributes;
        }

        $this->styleTagAttributesResolvers[] = $attributes;

        return $this;
    }

    /**
     * Use the given callback to resolve attributes for preload tags.
     *
     * @param  (callable(string, string, ?array, ?array): (array|false))|array|false  $attributes
     *
     * @return $this
     */
    public function usePreloadTagAttributes($attributes) {
        if (!is_callable($attributes)) {
            $attributes = fn () => $attributes;
        }

        $this->preloadTagAttributesResolvers[] = $attributes;

        return $this;
    }

    /**
     * Generate Vite tags for an entrypoint.
     *
     * @param string|string[] $entrypoints
     * @param null|string     $buildDirectory
     *
     * @throws \Exception
     *
     * @return \CBase_HtmlString
     */
    public function __invoke($entrypoints, $buildDirectory = null) {
        $entrypoints = c::collect($entrypoints);
        $buildDirectory ??= $this->buildDirectory;

        if ($this->isRunningHot()) {
            return new CBase_HtmlString(
                $entrypoints
                    ->prepend('@vite/client')
                    ->map(fn ($entrypoint) => $this->prependDefaultIfNecessary($entrypoint))
                    ->map(fn ($entrypoint) => $this->makeTagForChunk($entrypoint, $this->hotAsset($entrypoint), null, null))
                    ->join('')
            );
        }

        $manifest = $this->manifest($buildDirectory);

        $tags = c::collect();
        $preloads = c::collect();

        foreach ($entrypoints as $entrypoint) {
            $chunk = $this->chunk($manifest, $entrypoint);

            $preloads->push([
                $chunk['src'],
                $this->assetPath("{$buildDirectory}/{$chunk['file']}"),
                $chunk,
                $manifest,
            ]);

            foreach ($chunk['imports'] ?? [] as $import) {
                $preloads->push([
                    $import,
                    $this->assetPath("{$buildDirectory}/{$manifest[$import]['file']}"),
                    $manifest[$import],
                    $manifest,
                ]);

                foreach ($manifest[$import]['css'] ?? [] as $css) {
                    $partialManifest = CCollection::make($manifest)->where('file', $css);

                    $preloads->push([
                        $partialManifest->keys()->first(),
                        $this->assetPath("{$buildDirectory}/{$css}"),
                        $partialManifest->first(),
                        $manifest,
                    ]);

                    $tags->push($this->makeTagForChunk(
                        $partialManifest->keys()->first(),
                        $this->assetPath("{$buildDirectory}/{$css}"),
                        $partialManifest->first(),
                        $manifest
                    ));
                }
            }

            $tags->push($this->makeTagForChunk(
                $entrypoint,
                $this->assetPath("{$buildDirectory}/{$chunk['file']}"),
                $chunk,
                $manifest
            ));

            foreach ($chunk['css'] ?? [] as $css) {
                $partialManifest = CCollection::make($manifest)->where('file', $css);

                $preloads->push([
                    $partialManifest->keys()->first(),
                    $this->assetPath("{$buildDirectory}/{$css}"),
                    $partialManifest->first(),
                    $manifest,
                ]);

                $tags->push($this->makeTagForChunk(
                    $partialManifest->keys()->first(),
                    $this->assetPath("{$buildDirectory}/{$css}"),
                    $partialManifest->first(),
                    $manifest
                ));
            }
        }

        list($stylesheets, $scripts) = $tags->unique()->partition(fn ($tag) => str_starts_with($tag, '<link'));

        $preloads = $preloads->unique()
            ->sortByDesc(fn ($args) => $this->isCssPath($args[1]))
            ->map(fn ($args) => $this->makePreloadTagForChunk(...$args));

        return new CBase_HtmlString($preloads->join('') . $stylesheets->join('') . $scripts->join(''));
    }

    protected function prependDefaultIfNecessary($url) {
        if ($url !== '@vite/client') {
            if (is_dir(CF::appDir() . DS . 'default')) {
                if (!cstr::startsWith($url, 'default')) {
                    $url = 'default/' . $url;
                }
            }
        }

        return $url;
    }

    /**
     * Make tag for the given chunk.
     *
     * @param string     $src
     * @param string     $url
     * @param null|array $chunk
     * @param null|array $manifest
     *
     * @return string
     */
    protected function makeTagForChunk($src, $url, $chunk, $manifest) {
        if ($this->nonce === null
            && $this->integrityKey !== false
            && !array_key_exists($this->integrityKey, $chunk ?? [])
            && $this->scriptTagAttributesResolvers === []
            && $this->styleTagAttributesResolvers === []
        ) {
            return $this->makeTag($url);
        }

        if ($this->isCssPath($url)) {
            return $this->makeStylesheetTagWithAttributes(
                $url,
                $this->resolveStylesheetTagAttributes($src, $url, $chunk, $manifest)
            );
        }

        return $this->makeScriptTagWithAttributes(
            $url,
            $this->resolveScriptTagAttributes($src, $url, $chunk, $manifest)
        );
    }

    /**
     * Make a preload tag for the given chunk.
     *
     * @param string $src
     * @param string $url
     * @param array  $chunk
     * @param array  $manifest
     *
     * @return string
     */
    protected function makePreloadTagForChunk($src, $url, $chunk, $manifest) {
        $attributes = $this->resolvePreloadTagAttributes($src, $url, $chunk, $manifest);

        if ($attributes === false) {
            return '';
        }

        $this->preloadedAssets[$url] = $this->parseAttributes(
            CCollection::make($attributes)->forget('href')->all()
        );

        return '<link ' . implode(' ', $this->parseAttributes($attributes)) . ' />';
    }

    /**
     * Resolve the attributes for the chunks generated script tag.
     *
     * @param string     $src
     * @param string     $url
     * @param null|array $chunk
     * @param null|array $manifest
     *
     * @return array
     */
    protected function resolveScriptTagAttributes($src, $url, $chunk, $manifest) {
        $attributes = $this->integrityKey !== false
            ? ['integrity' => $chunk[$this->integrityKey] ?? false]
            : [];

        foreach ($this->scriptTagAttributesResolvers as $resolver) {
            $attributes = array_merge($attributes, $resolver($src, $url, $chunk, $manifest));
        }

        return $attributes;
    }

    /**
     * Resolve the attributes for the chunks generated stylesheet tag.
     *
     * @param string     $src
     * @param string     $url
     * @param null|array $chunk
     * @param null|array $manifest
     *
     * @return array
     */
    protected function resolveStylesheetTagAttributes($src, $url, $chunk, $manifest) {
        $attributes = $this->integrityKey !== false
            ? ['integrity' => $chunk[$this->integrityKey] ?? false]
            : [];

        foreach ($this->styleTagAttributesResolvers as $resolver) {
            $attributes = array_merge($attributes, $resolver($src, $url, $chunk, $manifest));
        }

        return $attributes;
    }

    /**
     * Resolve the attributes for the chunks generated preload tag.
     *
     * @param string $src
     * @param string $url
     * @param array  $chunk
     * @param array  $manifest
     *
     * @return array|false
     */
    protected function resolvePreloadTagAttributes($src, $url, $chunk, $manifest) {
        $attributes = $this->isCssPath($url) ? [
            'rel' => 'preload',
            'as' => 'style',
            'href' => $url,
            'nonce' => $this->nonce ?? false,
            'crossorigin' => $this->resolveStylesheetTagAttributes($src, $url, $chunk, $manifest)['crossorigin'] ?? false,
        ] : [
            'rel' => 'modulepreload',
            'href' => $url,
            'nonce' => $this->nonce ?? false,
            'crossorigin' => $this->resolveScriptTagAttributes($src, $url, $chunk, $manifest)['crossorigin'] ?? false,
        ];

        $attributes = $this->integrityKey !== false
            ? array_merge($attributes, ['integrity' => $chunk[$this->integrityKey] ?? false])
            : $attributes;

        foreach ($this->preloadTagAttributesResolvers as $resolver) {
            if (false === ($resolvedAttributes = $resolver($src, $url, $chunk, $manifest))) {
                return false;
            }

            $attributes = array_merge($attributes, $resolvedAttributes);
        }

        return $attributes;
    }

    /**
     * Generate an appropriate tag for the given URL in HMR mode.
     *
     * @deprecated will be removed in a future Laravel version
     *
     * @param string $url
     *
     * @return string
     */
    protected function makeTag($url) {
        if ($this->isCssPath($url)) {
            return $this->makeStylesheetTag($url);
        }

        return $this->makeScriptTag($url);
    }

    /**
     * Generate a script tag for the given URL.
     *
     * @deprecated will be removed in a future Laravel version
     *
     * @param string $url
     *
     * @return string
     */
    protected function makeScriptTag($url) {
        return $this->makeScriptTagWithAttributes($url, []);
    }

    /**
     * Generate a stylesheet tag for the given URL in HMR mode.
     *
     * @deprecated will be removed in a future Laravel version
     *
     * @param string $url
     *
     * @return string
     */
    protected function makeStylesheetTag($url) {
        return $this->makeStylesheetTagWithAttributes($url, []);
    }

    /**
     * Generate a script tag with attributes for the given URL.
     *
     * @param string $url
     * @param array  $attributes
     *
     * @return string
     */
    protected function makeScriptTagWithAttributes($url, $attributes) {
        $attributes = $this->parseAttributes(array_merge([
            'type' => 'module',
            'src' => $url,
            'nonce' => $this->nonce ?? false,
        ], $attributes));

        return '<script ' . implode(' ', $attributes) . '></script>';
    }

    /**
     * Generate a link tag with attributes for the given URL.
     *
     * @param string $url
     * @param array  $attributes
     *
     * @return string
     */
    protected function makeStylesheetTagWithAttributes($url, $attributes) {
        $attributes = $this->parseAttributes(array_merge([
            'rel' => 'stylesheet',
            'href' => $url,
            'nonce' => $this->nonce ?? false,
        ], $attributes));

        return '<link ' . implode(' ', $attributes) . ' />';
    }

    /**
     * Determine whether the given path is a CSS file.
     *
     * @param string $path
     *
     * @return bool
     */
    protected function isCssPath($path) {
        return preg_match('/\.(css|less|sass|scss|styl|stylus|pcss|postcss)$/', $path) === 1;
    }

    /**
     * Parse the attributes into key="value" strings.
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function parseAttributes($attributes) {
        return CCollection::make($attributes)
            ->reject(fn ($value, $key) => in_array($value, [false, null], true))
            ->flatMap(fn ($value, $key) => $value === true ? [$key] : [$key => $value])
            ->map(fn ($value, $key) => is_int($key) ? $value : $key . '="' . $value . '"')
            ->values()
            ->all();
    }

    /**
     * Generate React refresh runtime script.
     *
     * @return \CBase_HtmlString|void
     */
    public function reactRefresh() {
        if (!$this->isRunningHot()) {
            return;
        }

        $attributes = $this->parseAttributes([
            'nonce' => $this->cspNonce(),
        ]);

        return new CBase_HtmlString(
            sprintf(
                <<<'HTML'
                <script type="module" %s>
                    import RefreshRuntime from '%s'
                    RefreshRuntime.injectIntoGlobalHook(window)
                    window.$RefreshReg$ = () => {}
                    window.$RefreshSig$ = () => (type) => type
                    window.__vite_plugin_react_preamble_installed__ = true
                </script>
                HTML,
                implode(' ', $attributes),
                $this->hotAsset('@react-refresh')
            )
        );
    }

    /**
     * Get the path to a given asset when running in HMR mode.
     *
     * @param mixed $asset
     *
     * @return string
     */
    protected function hotAsset($asset) {
        return rtrim(file_get_contents($this->hotFile())) . '/' . $asset;
    }

    /**
     * Get the URL for an asset.
     *
     * @param string      $asset
     * @param null|string $buildDirectory
     *
     * @return string
     */
    public function asset($asset, $buildDirectory = null) {
        $buildDirectory ??= $this->buildDirectory;
        if ($this->isRunningHot()) {
            return $this->hotAsset($asset);
        }

        $chunk = $this->chunk($this->manifest($buildDirectory), $asset);

        return $this->assetPath($buildDirectory . '/' . $chunk['file']);
    }

    /**
     * Get the content of a given asset.
     *
     * @param string      $asset
     * @param null|string $buildDirectory
     *
     * @throws \Exception
     *
     * @return string
     */
    public function content($asset, $buildDirectory = null) {
        $buildDirectory ??= $this->buildDirectory;

        $chunk = $this->chunk($this->manifest($buildDirectory), $asset);

        $path = CF::publicPath($buildDirectory . '/' . $chunk['file']);

        if (!is_file($path) || !file_exists($path)) {
            throw new Exception("Unable to locate file from Vite manifest: {$path}.");
        }

        return file_get_contents($path);
    }

    /**
     * Generate an asset path for the application.
     *
     * @param string    $path
     * @param null|bool $secure
     *
     * @return string
     */
    protected function assetPath($path, $secure = null) {
        $path = CF::publicPath($path);
        if (cstr::startsWith($path, CF::publicPath())) {
            $path = str_replace(CF::publicPath() . '/', '', $path);
        }
        $urlGenerator = CRouting::urlGenerator();
        $root = $urlGenerator->formatRoot($urlGenerator->formatScheme($secure));
        $i = 'index.php';

        $root = cstr::contains($root, $i) ? str_replace('/' . $i, '', $root) : $root;

        return $root . '/' . trim($path, '/');

        // return c::media($path, $secure);
    }

    /**
     * Get the the manifest file for the given build directory.
     *
     * @param string $buildDirectory
     *
     * @throws \CBase_Exception_ViteManifestNotFoundException
     *
     * @return array
     */
    protected function manifest($buildDirectory) {
        $path = $this->manifestPath($buildDirectory);

        if (!isset(static::$manifests[$path])) {
            if (!is_file($path)) {
                throw new CBase_Exception_ViteManifestNotFoundException("Vite manifest not found at: $path");
            }

            static::$manifests[$path] = json_decode(file_get_contents($path), true);
        }

        return static::$manifests[$path];
    }

    /**
     * Get the path to the manifest file for the given build directory.
     *
     * @param string $buildDirectory
     *
     * @return string
     */
    protected function manifestPath($buildDirectory) {
        // $relativeIndex = str_replace(DOCROOT, '', CFINDEX);

        // return strpos($relativeIndex, 'application/') !== false;
        // cdbg::dd(CFINDEX, CF::isIndexInApp(), CF::publicPath($buildDirectory . '/' . $this->manifestFilename), $buildDirectory . '/' . $this->manifestFilename);
        return CF::publicPath($buildDirectory . '/' . $this->manifestFilename);
    }

    /**
     * Get a unique hash representing the current manifest, or null if there is no manifest.
     *
     * @param null|string $buildDirectory
     *
     * @return null|string
     */
    public function manifestHash($buildDirectory = null) {
        $buildDirectory ??= $this->buildDirectory;

        if ($this->isRunningHot()) {
            return null;
        }

        if (!is_file($path = $this->manifestPath($buildDirectory))) {
            return null;
        }

        return md5_file($path) ?: null;
    }

    /**
     * Get the chunk for the given entry point / asset.
     *
     * @param array  $manifest
     * @param string $file
     *
     * @throws \Exception
     *
     * @return array
     */
    protected function chunk($manifest, $file) {
        if (!isset($manifest[$file])) {
            $fileWithDefault = 'default/' . $file;
            if (isset($manifest[$fileWithDefault])) {
                return $manifest[$fileWithDefault];
            }

            throw new Exception("Unable to locate file in Vite manifest: {$file}.");
        }

        return $manifest[$file];
    }

    /**
     * Get the nonce attribute for the prefetch script tags.
     *
     * @return \CBase_HtmlString
     */
    protected function nonceAttribute() {
        if ($this->cspNonce() === null) {
            return new CBase_HtmlString('');
        }

        return new CBase_HtmlString(' nonce="' . $this->cspNonce() . '"');
    }

    /**
     * Determine if the HMR server is running.
     *
     * @return bool
     */
    public function isRunningHot() {
        return is_file($this->hotFile());
    }

    /**
     * Get the Vite tag content as a string of HTML.
     *
     * @return string
     */
    public function toHtml() {
        return $this->__invoke($this->entryPoints)->toHtml();
    }
}
