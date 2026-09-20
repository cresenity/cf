<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Debugger helper class.
 */
//@codingStandardsIgnoreStart
class cdbg {
    /**
     * A collapse icon, using in the dump_var function to allow collapsing
     * an array or object.
     *
     * @since   1.0.000
     * @static
     *
     * @var string
     */
    public static $icon_collapse = 'iVBORw0KGgoAAAANSUhEUgAAAAkAAAAJCAMAAADXT/YiAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA2RpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYwIDYxLjEzNDc3NywgMjAxMC8wMi8xMi0xNzozMjowMCAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iIHhtbG5zOnN0UmVmPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VSZWYjIiB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iIHhtcE1NOk9yaWdpbmFsRG9jdW1lbnRJRD0ieG1wLmRpZDo3MjlFRjQ2NkM5QzJFMTExOTA0MzkwRkI0M0ZCODY4RCIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDpFNzFDNDQyNEMyQzkxMUUxOTU4MEM4M0UxRDA0MUVGNSIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDpFNzFDNDQyM0MyQzkxMUUxOTU4MEM4M0UxRDA0MUVGNSIgeG1wOkNyZWF0b3JUb29sPSJBZG9iZSBQaG90b3Nob3AgQ1M1IFdpbmRvd3MiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDo3NDlFRjQ2NkM5QzJFMTExOTA0MzkwRkI0M0ZCODY4RCIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDo3MjlFRjQ2NkM5QzJFMTExOTA0MzkwRkI0M0ZCODY4RCIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/PuF4AWkAAAA2UExURU9t2DBStczM/1h16DNmzHiW7iNFrypMvrnD52yJ4ezs7Onp6ejo6P///+Tk5GSG7D9h5SRGq0Q2K74AAAA/SURBVHjaLMhZDsAgDANRY3ZISnP/y1ZWeV+jAeuRSky6cKL4ryDdSggP8UC7r6GvR1YHxjazPQDmVzI/AQYAnFQDdVSJ80EAAAAASUVORK5CYII=';

    /**
     * A collapse icon, using in the dump_var function to allow collapsing
     * an array or object.
     *
     * @since   1.0.000
     * @static
     *
     * @var string
     */
    public static $icon_expand = 'iVBORw0KGgoAAAANSUhEUgAAAAkAAAAJCAMAAADXT/YiAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA2RpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYwIDYxLjEzNDc3NywgMjAxMC8wMi8xMi0xNzozMjowMCAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iIHhtbG5zOnN0UmVmPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VSZWYjIiB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iIHhtcE1NOk9yaWdpbmFsRG9jdW1lbnRJRD0ieG1wLmRpZDo3MTlFRjQ2NkM5QzJFMTExOTA0MzkwRkI0M0ZCODY4RCIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDpFQzZERTJDNEMyQzkxMUUxODRCQzgyRUNDMzZEQkZFQiIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDpFQzZERTJDM0MyQzkxMUUxODRCQzgyRUNDMzZEQkZFQiIgeG1wOkNyZWF0b3JUb29sPSJBZG9iZSBQaG90b3Nob3AgQ1M1IFdpbmRvd3MiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDo3MzlFRjQ2NkM5QzJFMTExOTA0MzkwRkI0M0ZCODY4RCIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDo3MTlFRjQ2NkM5QzJFMTExOTA0MzkwRkI0M0ZCODY4RCIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/PkmDvWIAAABIUExURU9t2MzM/3iW7ubm59/f5urq85mZzOvr6////9ra38zMzObm5rfB8FZz5myJ4SNFrypMvjBStTNmzOvr+mSG7OXl8T9h5SRGq/OfqCEAAABKSURBVHjaFMlbEoAwCEPRULXF2jdW9r9T4czcyUdA4XWB0IgdNSybxU9amMzHzDlPKKu7Fd1e6+wY195jW0ARYZECxPq5Gn8BBgCr0gQmxpjKAwAAAABJRU5ErkJggg==';

    //@codingStandardsIgnoreEnd
    protected static $deprecated_has_run = false;

    /**
     * Better var dump.
     *
     * @param string $var
     * @param bool   $return
     *
     * @deprecated 1.2
     *
     * @return void
     */
    //@codingStandardsIgnoreStart
    public static function var_dump($var, $return = false) {
        CF::deprecated(__METHOD__, 'cdbg::varDump()', '1.2', null, true);
        return cdbg::varDump($var, $return);
    }

    //@codingStandardsIgnoreEnd

    /**
     * Better var_dump.
     *
     * @param string $var
     * @param bool   $return
     *
     * @return void
     */
    public static function varDump($var, $return = false) {
        $html = '<pre style="margin-bottom: 18px;'
                . 'background: #f7f7f9;'
                . 'border: 1px solid #e1e1e8;'
                . 'padding: 8px;'
                . 'border-radius: 4px;'
                . '-moz-border-radius: 4px;'
                . '-webkit-border radius: 4px;'
                . 'display: block;'
                . 'font-size: 12.05px;'
                . 'white-space: pre-wrap;'
                . 'word-wrap: break-word;'
                . 'color: #333;'
                . 'font-family: Menlo,Monaco,Consolas,\'Courier New\',monospace;">';
        $html .= self::varDumpPlain($var);
        $html .= '</pre>';

        //        try {
        //            throw new Exception('dump');
        //        } catch (Exception $ex) {
        //            echo $ex->getTraceAsString();
        //        }

        if (!$return) {
            echo $html;
        } else {
            return $html;
        }
    }

    public static function varDumpTrace($message = 'dump', $return = false) {
        try {
            throw new Exception($message);
        } catch (Exception $ex) {
            cdbg::varDump($ex->getTraceAsString(), $return);
        }
    }

    public static function dumpLastQuery($db = null) {
        cdbg::varDump(c::db($db)->lastQuery());
    }

    /**
     * Dump the passed variables and end the script.
     *
     * @param  mixed
     *
     * @return void
     */
    public static function d() {
        $args = func_get_args();
        foreach ($args as $x) {
            CDebug::dumper()->dump($x);
        }
    }

    /**
     * Dump the passed variables and end the script.
     *
     * @param  mixed
     *
     * @return void
     */
    public static function dd() {
        $args = func_get_args();

        foreach ($args as $x) {
            CDebug::dumper()->dump($x);
        }

        die(1);
    }

    public static function varDumpPlain($var) {
        $html = '';

        if (is_bool($var)) {
            $html .= '<span style="color:#588bff;">bool</span><span style="color:#999;">(</span><strong>' . (($var) ? 'true' : 'false') . '</strong><span style="color:#999;">)</span>';
        } elseif (is_int($var)) {
            $html .= '<span style="color:#588bff;">int</span><span style="color:#999;">(</span><strong>' . $var . '</strong><span style="color:#999;">)</span>';
        } elseif (is_float($var)) {
            $html .= '<span style="color:#588bff;">float</span><span style="color:#999;">(</span><strong>' . $var . '</strong><span style="color:#999;">)</span>';
        } elseif (is_string($var)) {
            $html .= '<span style="color:#588bff;">string</span><span style="color:#999;">(</span>' . strlen($var) . '<span style="color:#999;">)</span> <strong>"' . htmlentities($var) . '"</strong>';
        } elseif (is_null($var)) {
            $html .= '<strong>NULL</strong>';
        } elseif (is_resource($var)) {
            $html .= '<span style="color:#588bff;">resource</span>("' . get_resource_type($var) . '") <strong>"' . $var . '"</strong>';
        } elseif (is_array($var)) {
            $uuid = 'include-php-' . uniqid();

            $html .= '<span style="color:#588bff;">array</span>(' . count($var) . ')';

            if (!empty($var)) {
                $html .= ' <img id="' . $uuid . '" data-expand="data:image/png;base64,' . self::$icon_expand . '" style="position:relative;left:-5px;top:-1px;cursor:pointer;" src="data:image/png;base64,' . self::$icon_collapse . '" /><br /><span id="' . $uuid . '-collapsable">[<br />';

                $indent = 4;
                $longest_key = 0;

                foreach ($var as $key => $value) {
                    if (is_string($key)) {
                        $longest_key = max($longest_key, strlen($key) + 2);
                    } else {
                        $longest_key = max($longest_key, strlen($key));
                    }
                }

                foreach ($var as $key => $value) {
                    if (is_numeric($key)) {
                        $html .= str_repeat(' ', $indent) . str_pad($key, $longest_key, ' ');
                    } else {
                        $html .= str_repeat(' ', $indent) . str_pad('"' . htmlentities($key) . '"', $longest_key, ' ');
                    }

                    $html .= ' => ';

                    $value = explode('<br />', self::varDumpPlain($value));

                    foreach ($value as $line => $val) {
                        if ($line != 0) {
                            $value[$line] = str_repeat(' ', $indent * 2) . $val;
                        }
                    }

                    $html .= implode('<br />', $value) . '<br />';
                }

                $html .= ']</span>';

                $html .= preg_replace('/ +/', ' ', '<script type="text/javascript">(function() {
				var img = document.getElementById("' . $uuid . '");
				img.onclick = function() {
					if ( document.getElementById("' . $uuid . '-collapsable").style.display == "none" ) {
						document.getElementById("' . $uuid . '-collapsable").style.display = "inline";
						img.src = img.getAttribute("data-collapse");
						var previousSibling = document.getElementById("' . $uuid . '-collapsable").previousSibling;

						while ( previousSibling != null && ( previousSibling.nodeType != 1 || previousSibling.tagName.toLowerCase() != "br" ) ) {
							previousSibling = previousSibling.previousSibling;
						}

						if ( previousSibling != null && previousSibling.tagName.toLowerCase() == "br" ) {
							previousSibling.style.display = "inline";
						}
					} else {
						document.getElementById("' . $uuid . '-collapsable").style.display = "none";
						img.setAttribute( "data-collapse", img.getAttribute("src") );
						img.src = img.getAttribute("data-expand");
						var previousSibling = document.getElementById("' . $uuid . '-collapsable").previousSibling;

						while ( previousSibling != null && ( previousSibling.nodeType != 1 || previousSibling.tagName.toLowerCase() != "br" ) ) {
							previousSibling = previousSibling.previousSibling;
						}

						if ( previousSibling != null && previousSibling.tagName.toLowerCase() == "br" ) {
							previousSibling.style.display = "none";
						}
					}
				};
				})();
				</script>');
            }
        } elseif (is_object($var)) {
            if (is_callable($var)) {
                $html .= '<span style="color:#588bff;">Closure</span><span style="color:#999;"></span>';
            } else {
                $uuid = 'include-php-' . uniqid();

                $html .= '<span style="color:#588bff;">object</span>(' . get_class($var) . ') <img id="' . $uuid . '" data-expand="data:image/png;base64,' . self::$icon_expand . '" style="position:relative;left:-5px;top:-1px;cursor:pointer;" src="data:image/png;base64,' . self::$icon_collapse . '" /><br /><span id="' . $uuid . '-collapsable">[<br />';

                $original = $var;
                $var = (array) $var;

                $indent = 4;
                $longest_key = 0;

                foreach ($var as $key => $value) {
                    if (substr($key, 0, 2) == "\0*") {
                        unset($var[$key]);
                        $key = 'protected:' . substr($key, 2);
                        $var[$key] = $value;
                    } elseif (substr($key, 0, 1) == "\0") {
                        unset($var[$key]);
                        $key = 'private:' . substr($key, 1, strpos(substr($key, 1), "\0")) . ':' . substr($key, strpos(substr($key, 1), "\0") + 1);
                        $var[$key] = $value;
                    }

                    if (is_string($key)) {
                        $longest_key = max($longest_key, strlen($key) + 2);
                    } else {
                        $longest_key = max($longest_key, strlen($key));
                    }
                }

                foreach ($var as $key => $value) {
                    if (is_numeric($key)) {
                        $html .= str_repeat(' ', $indent) . str_pad($key, $longest_key, ' ');
                    } else {
                        $html .= str_repeat(' ', $indent) . str_pad('"' . htmlentities($key) . '"', $longest_key, ' ');
                    }

                    $html .= ' => ';

                    $value = explode('<br />', self::varDumpPlain($value));

                    foreach ($value as $line => $val) {
                        if ($line != 0) {
                            $value[$line] = str_repeat(' ', $indent * 2) . $val;
                        }
                    }

                    $html .= implode('<br />', $value) . '<br />';
                }

                $html .= ']</span>';

                $html .= preg_replace('/ +/', ' ', '<script type="text/javascript">(function() {
			var img = document.getElementById("' . $uuid . '");
			img.onclick = function() {
				if ( document.getElementById("' . $uuid . '-collapsable").style.display == "none" ) {
					document.getElementById("' . $uuid . '-collapsable").style.display = "inline";
					img.src = img.getAttribute("data-collapse");
					var previousSibling = document.getElementById("' . $uuid . '-collapsable").previousSibling;

					while ( previousSibling != null && ( previousSibling.nodeType != 1 || previousSibling.tagName.toLowerCase() != "br" ) ) {
						previousSibling = previousSibling.previousSibling;
					}

					if ( previousSibling != null && previousSibling.tagName.toLowerCase() == "br" ) {
						previousSibling.style.display = "inline";
					}
				} else {
					document.getElementById("' . $uuid . '-collapsable").style.display = "none";
					img.setAttribute( "data-collapse", img.getAttribute("src") );
					img.src = img.getAttribute("data-expand");
					var previousSibling = document.getElementById("' . $uuid . '-collapsable").previousSibling;

					while ( previousSibling != null && ( previousSibling.nodeType != 1 || previousSibling.tagName.toLowerCase() != "br" ) ) {
						previousSibling = previousSibling.previousSibling;
					}

					if ( previousSibling != null && previousSibling.tagName.toLowerCase() == "br" ) {
						previousSibling.style.display = "none";
					}
				}
			};
			})();
			</script>');
            }
        }

        return $html;
    }

    /**
     * Returns an HTML string, highlighting a specific line of a file, with some
     * number of lines padded above and below.
     *
     *     // Highlights the current line of the current file
     *     echo Debug::source(__FILE__, __LINE__);
     *
     * @param string $file        file to open
     * @param int    $line_number line number to highlight
     * @param int    $padding     number of padding lines
     *
     * @return string|false source of file|file is unreadable
     */
    public static function source($file, $line_number, $padding = 5) {
        if (!$file or !is_readable($file)) {
            // Continuing will cause errors
            return false;
        }

        // Open the file and set the line position
        $file = fopen($file, 'r');
        $line = 0;

        // Set the reading range
        $range = ['start' => $line_number - $padding, 'end' => $line_number + $padding];

        // Set the zero-padding amount for line numbers
        $format = '% ' . strlen($range['end']) . 'd';

        $source = '';
        while (($row = fgets($file)) !== false) {
            // Increment the line number
            if (++$line > $range['end']) {
                break;
            }

            if ($line >= $range['start']) {
                // Make the row safe for output
                $row = htmlspecialchars($row, ENT_NOQUOTES, CF::$charset);

                // Trim whitespace and sanitize the row
                $row = '<span class="number">' . sprintf($format, $line) . '</span> ' . $row;

                if ($line === $line_number) {
                    // Apply highlighting to this row
                    $row = '<span class="line highlight">' . $row . '</span>';
                } else {
                    $row = '<span class="line">' . $row . '</span>';
                }

                // Add to the captured source
                $source .= $row;
            }
        }

        // Close the file
        fclose($file);

        return '<pre class="source"><code>' . $source . '</code></pre>';
    }

    /**
     * Returns an HTML string of information about a single variable.
     *
     * Borrows heavily on concepts from the Debug class of [Nette](http://nettephp.com/).
     *
     * @param mixed $value           variable to dump
     * @param int   $length          maximum length of strings
     * @param int   $level_recursion recursion limit
     *
     * @return string
     */
    public static function dump($value, $length = 128, $level_recursion = 10) {
        return self::dumpInternal($value, $length, $level_recursion);
    }

    /**
     * Helper for Debug::dump(), handles recursion in arrays and objects.
     *
     * @param mixed $var    variable to dump
     * @param int   $length maximum length of strings
     * @param int   $limit  recursion limit
     * @param int   $level  current recursion level (internal usage only!)
     *
     * @return string
     */
    protected static function dumpInternal(&$var, $length = 128, $limit = 10, $level = 0) {
        if ($var === null) {
            return '<small>NULL</small>';
        } elseif (is_bool($var)) {
            return '<small>bool</small> ' . ($var ? 'TRUE' : 'FALSE');
        } elseif (is_float($var)) {
            return '<small>float</small> ' . $var;
        } elseif (is_resource($var)) {
            if (($type = get_resource_type($var)) === 'stream' and $meta = stream_get_meta_data($var)) {
                $meta = stream_get_meta_data($var);

                if (isset($meta['uri'])) {
                    $file = $meta['uri'];

                    if (function_exists('stream_is_local')) {
                        // Only exists on PHP >= 5.2.4
                        if (stream_is_local($file)) {
                            $file = cdbg::path($file);
                        }
                    }

                    return '<small>resource</small><span>(' . $type . ')</span> ' . htmlspecialchars($file, ENT_NOQUOTES, CF::$charset);
                }
            } else {
                return '<small>resource</small><span>(' . $type . ')</span>';
            }
        } elseif (is_string($var)) {
            // Clean invalid multibyte characters. iconv is only invoked
            // if there are non ASCII characters in the string, so this
            // isn't too much of a hit.
            $var = CUTF8::clean($var, CF::$charset);

            if (CUTF8::strlen($var) > $length) {
                // Encode the truncated string
                $str = htmlspecialchars(CUTF8::substr($var, 0, $length), ENT_NOQUOTES, CF::$charset) . '&nbsp;&hellip;';
            } else {
                // Encode the string
                $str = htmlspecialchars($var, ENT_NOQUOTES, CF::$charset);
            }

            return '<small>string</small><span>(' . strlen($var) . ')</span> "' . $str . '"';
        } elseif (is_array($var)) {
            $output = [];

            // Indentation for this variable
            $space = str_repeat($s = '    ', $level);

            static $marker;

            if ($marker === null) {
                // Make a unique marker - force it to be alphanumeric so that it is always treated as a string array key
                $marker = uniqid("\x00") . 'x';
            }

            if (empty($var)) {
                // Do nothing
            } elseif (isset($var[$marker])) {
                $output[] = "(\n{$space}{$s}*RECURSION*\n{$space})";
            } elseif ($level < $limit) {
                $output[] = '<span>(';

                $var[$marker] = true;
                foreach ($var as $key => &$val) {
                    if ($key === $marker) {
                        continue;
                    }
                    if (!is_int($key)) {
                        $key = '"' . htmlspecialchars($key, ENT_NOQUOTES, CF::$charset) . '"';
                    }

                    $output[] = "{$space}{$s}{$key} => " . cdbg::dumpInternal($val, $length, $limit, $level + 1);
                }
                unset($var[$marker]);

                $output[] = "{$space})</span>";
            } else {
                // Depth too great
                $output[] = "(\n{$space}{$s}...\n{$space})";
            }

            return '<small>array</small><span>(' . count($var) . ')</span> ' . implode("\n", $output);
        } elseif (is_object($var)) {
            // Copy the object as an array
            $array = (array) $var;

            $output = [];

            // Indentation for this variable
            $space = str_repeat($s = '    ', $level);

            $hash = spl_object_hash($var);

            // Objects that are being dumped
            static $objects = [];

            if (empty($var)) {
                // Do nothing
            } elseif (isset($objects[$hash])) {
                $output[] = "{\n{$space}{$s}*RECURSION*\n{$space}}";
            } elseif ($level < $limit) {
                $output[] = '<code>{';

                $objects[$hash] = true;
                foreach ($array as $key => &$val) {
                    if ($key[0] === "\x00") {
                        // Determine if the access is protected or protected
                        $access = '<small>' . (($key[1] === '*') ? 'protected' : 'private') . '</small>';

                        // Remove the access level from the variable name
                        $key = substr($key, strrpos($key, "\x00") + 1);
                    } else {
                        $access = '<small>public</small>';
                    }

                    $output[] = "{$space}{$s}{$access} {$key} => " . cdbg::dumpInternal($val, $length, $limit, $level + 1);
                }
                unset($objects[$hash]);

                $output[] = "{$space}}</code>";
            } else {
                // Depth too great
                $output[] = "{\n{$space}{$s}...\n{$space}}";
            }

            return '<small>object</small> <span>' . get_class($var) . '(' . count($array) . ')</span> ' . implode("\n", $output);
        } else {
            return '<small>' . gettype($var) . '</small> ' . htmlspecialchars(print_r($var, true), ENT_NOQUOTES, CF::$charset);
        }
    }

    /**
     * Returns an array of HTML strings that represent each step in the backtrace.
     *
     *     // Displays the entire current backtrace
     *     echo implode('<br/>', Debug::trace());
     *
     * @param array $trace
     *
     * @return string
     */
    public static function trace(?array $trace = null) {
        if ($trace === null) {
            // Start a new trace
            $trace = debug_backtrace();
        }

        // Non-standard function calls
        $statements = ['include', 'include_once', 'require', 'require_once'];

        $output = [];
        foreach ($trace as $step) {
            if (!isset($step['function'])) {
                // Invalid trace step
                continue;
            }

            if (isset($step['file']) and isset($step['line'])) {
                // Include the source of this step
                $source = self::source($step['file'], $step['line']);
            }

            if (isset($step['file'])) {
                $file = $step['file'];

                if (isset($step['line'])) {
                    $line = $step['line'];
                }
            }

            // function()
            $function = $step['function'];

            if (in_array($step['function'], $statements)) {
                if (empty($step['args'])) {
                    // No arguments
                    $args = [];
                } else {
                    // Sanitize the file path
                    $args = [$step['args'][0]];
                }
            } elseif (isset($step['args'])) {
                if (!function_exists($step['function']) or strpos($step['function'], '{closure}') !== false) {
                    // Introspection on closures or language constructs in a stack trace is impossible
                    $params = null;
                } else {
                    if (isset($step['class'])) {
                        if (method_exists($step['class'], $step['function'])) {
                            $reflection = new ReflectionMethod($step['class'], $step['function']);
                        } else {
                            $reflection = new ReflectionMethod($step['class'], '__call');
                        }
                    } else {
                        $reflection = new ReflectionFunction($step['function']);
                    }

                    // Get the function parameters
                    $params = $reflection->getParameters();
                }

                $args = [];

                foreach ($step['args'] as $i => $arg) {
                    if (isset($params[$i])) {
                        // Assign the argument by the parameter name
                        $args[$params[$i]->name] = $arg;
                    } else {
                        // Assign the argument by number
                        $args[$i] = $arg;
                    }
                }
            }

            if (isset($step['class'])) {
                // Class->method() or Class::method()
                $function = $step['class'] . $step['type'] . $step['function'];
            }

            $output[] = [
                'function' => $function,
                'args' => isset($args) ? $args : null,
                'file' => isset($file) ? $file : null,
                'line' => isset($line) ? $line : null,
                'source' => isset($source) ? $source : null,
            ];

            unset($function, $args, $file, $line, $source);
        }

        return $output;
    }

    /**
     * Removes application, system, modpath, or docroot from a filename,
     * replacing them with the plain text equivalents. Useful for debugging
     * when you want to display a shorter path.
     *
     *     // Displays SYSPATH/classes/kohana.php
     *     echo Debug::path(Kohana::find_file('classes', 'kohana'));
     *
     * @param string $file path to debug
     *
     * @return string
     */
    public static function path($file) {
        if (strpos($file, APPPATH) === 0) {
            $file = 'APPPATH' . DIRECTORY_SEPARATOR . substr($file, strlen(APPPATH));
        } elseif (strpos($file, SYSPATH) === 0) {
            $file = 'SYSPATH' . DIRECTORY_SEPARATOR . substr($file, strlen(SYSPATH));
        } elseif (strpos($file, MODPATH) === 0) {
            $file = 'MODPATH' . DIRECTORY_SEPARATOR . substr($file, strlen(MODPATH));
        } elseif (strpos($file, DOCROOT) === 0) {
            $file = 'DOCROOT' . DIRECTORY_SEPARATOR . substr($file, strlen(DOCROOT));
        }

        return $file;
    }

    public static function deprecated($message = '', $email = '') {
        return CDebug::collector()->collectDeprecated($message);
    }

    /**
     * @return array
     */
    public static function callerInfoArray() {
        $c = '';
        $file = '';
        $func = '';
        $class = '';
        // Older php version don't have 'DEBUG_BACKTRACE_IGNORE_ARGS', so manually remove the args from the backtrace
        if (!defined('DEBUG_BACKTRACE_IGNORE_ARGS')) {
            $trace = array_map(function ($item) {
                unset($item['args']);

                return $item;
            }, debug_backtrace(false));
        } else {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }

        if (isset($trace[2])) {
            $file = carr::get($trace, '1.file');
            $line = carr::get($trace, '1.line');
            $func = carr::get($trace, '2.function');
            if ((substr($func, 0, 7) == 'include') || (substr($func, 0, 7) == 'require')) {
                $func = '';
            }
        } elseif (isset($trace[1])) {
            $file = carr::get($trace, '1.file');
            $line = carr::get($trace, '1.line');
            $func = '';
        }
        if (isset($trace[3]['class'])) {
            $class = carr::get($trace, '3.class');
            $func = carr::get($trace, '3.function');
            $file = carr::get($trace, '2.file');
            $line = carr::get($trace, '2.line');
        } elseif (isset($trace[2]['class'])) {
            $class = carr::get($trace, '2.class');
            $func = carr::get($trace, '2.function');
            $file = carr::get($trace, '1.file');
            $line = carr::get($trace, '1.line');
        }
        $r = [];
        $r['file'] = $file;
        $r['line'] = $line;
        $r['class'] = $class;
        $r['function'] = $func;

        return $r;
    }

    /**
     * @return string
     */
    public static function callerInfo() {
        $c = '';
        $file = '';
        $func = '';
        $class = '';
        // Older php version don't have 'DEBUG_BACKTRACE_IGNORE_ARGS', so manually remove the args from the backtrace
        if (!defined('DEBUG_BACKTRACE_IGNORE_ARGS')) {
            $trace = array_map(function ($item) {
                unset($item['args']);

                return $item;
            }, debug_backtrace(false));
        } else {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }

        if (isset($trace[2])) {
            $file = carr::get($trace, '1.file');
            $line = carr::get($trace, '1.line');
            $func = carr::get($trace, '2.function');
            if ((substr($func, 0, 7) == 'include') || (substr($func, 0, 7) == 'require')) {
                $func = '';
            }
        } elseif (isset($trace[1])) {
            $file = carr::get($trace, '1.file');
            $line = carr::get($trace, '1.line');
            $func = '';
        }
        if (isset($trace[3]['class'])) {
            $class = carr::get($trace, '3.class');
            $func = carr::get($trace, '3.function');
            $file = carr::get($trace, '2.file');
            $line = carr::get($trace, '2.line');
        } elseif (isset($trace[2]['class'])) {
            $class = carr::get($trace, '2.class');
            $func = carr::get($trace, '2.function');
            $file = carr::get($trace, '1.file');
            $line = carr::get($trace, '1.line');
        }

        $c = $file . ':' . $line . ' ';
        $c .= ($class != '') ? ' ' . $class . '->' : '';
        $c .= ($func != '') ? $func . '(): ' : '';

        return $c;
    }

    public static function getTraceString() {
        $ex = new Exception('test');
        $trace = $ex->getTraceAsString();

        return $trace;
    }

    public static function traceDump($return = false) {
        return static::varDump(self::getTraceString(), $return);
    }

    public static function queryDump($db = null, $return = false) {
        if ($db == null) {
            $db = c::db();
        }

        return cdbg::varDump($db->lastQuery(), $return);
    }
}
