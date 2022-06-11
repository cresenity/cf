<?php

trait CTesting_Concern_ProvidesBrowser {
    /**
     * All of the active browser instances.
     *
     * @var array
     */
    protected static $browsers = [];

    /**
     * The callbacks that should be run on class tear down.
     *
     * @var array
     */
    protected static $afterClassCallbacks = [];

    /**
     * Tear down the Dusk test case class.
     *
     * @afterClass
     *
     * @return void
     */
    public static function tearDownDuskClass() {
        static::closeAll();

        foreach (static::$afterClassCallbacks as $callback) {
            $callback();
        }
    }

    /**
     * Register an "after class" tear down callback.
     *
     * @param \Closure $callback
     *
     * @return void
     */
    public static function afterClass(Closure $callback) {
        static::$afterClassCallbacks[] = $callback;
    }

    /**
     * Create a new browser instance.
     *
     * @param \Closure $callback
     *
     * @throws \Exception
     * @throws \Throwable
     *
     * @return \CTesting_Browser|void
     */
    public function browse(Closure $callback) {
        $browsers = $this->createBrowsersFor($callback);

        try {
            $callback(...$browsers->all());
        } catch (Exception $e) {
            $this->captureFailuresFor($browsers);
            $this->storeSourceLogsFor($browsers);

            throw $e;
        } catch (Throwable $e) {
            $this->captureFailuresFor($browsers);
            $this->storeSourceLogsFor($browsers);

            throw $e;
        } finally {
            $this->storeConsoleLogsFor($browsers);

            static::$browsers = $this->closeAllButPrimary($browsers);
        }
    }

    /**
     * Create the browser instances needed for the given callback.
     *
     * @param \Closure $callback
     *
     * @throws \ReflectionException
     *
     * @return CCollection|array
     */
    protected function createBrowsersFor(Closure $callback) {
        if (count(static::$browsers) === 0) {
            static::$browsers = c::collect([$this->newBrowser($this->createWebDriver())]);
        }

        $additional = $this->browsersNeededFor($callback) - 1;

        for ($i = 0; $i < $additional; $i++) {
            static::$browsers->push($this->newBrowser($this->createWebDriver()));
        }

        return static::$browsers;
    }

    /**
     * Create a new Browser instance.
     *
     * @param \Facebook\WebDriver\Remote\RemoteWebDriver $driver
     *
     * @return \CTesting_Browser
     */
    protected function newBrowser($driver) {
        return new CTesting_Browser($driver);
    }

    /**
     * Get the number of browsers needed for a given callback.
     *
     * @param \Closure $callback
     *
     * @throws \ReflectionException
     *
     * @return int
     */
    protected function browsersNeededFor(Closure $callback) {
        return (new ReflectionFunction($callback))->getNumberOfParameters();
    }

    /**
     * Capture failure screenshots for each browser.
     *
     * @param \CCollection $browsers
     *
     * @return void
     */
    protected function captureFailuresFor($browsers) {
        $browsers->each(function ($browser, $key) {
            if (property_exists($browser, 'fitOnFailure') && $browser->fitOnFailure) {
                $browser->fitContent();
            }

            $name = $this->getCallerName();

            $browser->screenshot('failure-' . $name . '-' . $key);
        });
    }

    /**
     * Store the console output for the given browsers.
     *
     * @param \CCollection $browsers
     *
     * @return void
     */
    protected function storeConsoleLogsFor($browsers) {
        $browsers->each(function ($browser, $key) {
            $name = $this->getCallerName();

            $browser->storeConsoleLog($name . '-' . $key);
        });
    }

    /**
     * Store the source code for the given browsers (if necessary).
     *
     * @param \CCollection $browsers
     *
     * @return void
     */
    protected function storeSourceLogsFor($browsers) {
        $browsers->each(function ($browser, $key) {
            if (property_exists($browser, 'makesSourceAssertion')
                && $browser->makesSourceAssertion
            ) {
                $browser->storeSource($this->getCallerName() . '-' . $key);
            }
        });
    }

    /**
     * Close all of the browsers except the primary (first) one.
     *
     * @param CCollection $browsers
     *
     * @return CCollection
     */
    protected function closeAllButPrimary($browsers) {
        $browsers->slice(1)->each->quit();

        return $browsers->take(1);
    }

    /**
     * Close all of the active browsers.
     *
     * @return void
     */
    public static function closeAll() {
        CCollection::make(static::$browsers)->each->quit();

        static::$browsers = c::collect();
    }

    /**
     * Create the remote web driver instance.
     *
     * @throws \Exception
     *
     * @return \Facebook\WebDriver\Remote\RemoteWebDriver
     */
    protected function createWebDriver() {
        return c::retry(5, function () {
            return $this->driver();
        }, 50);
    }

    /**
     * Get the browser caller name.
     *
     * @return string
     */
    protected function getCallerName() {
        return str_replace('\\', '_', get_class($this)) . '_' . $this->getName(false);
    }

    /**
     * Create the RemoteWebDriver instance.
     *
     * @return \Facebook\WebDriver\Remote\RemoteWebDriver
     */
    abstract protected function driver();
}
