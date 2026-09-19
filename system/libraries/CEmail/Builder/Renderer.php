<?php

class CEmail_Builder_Renderer {
    protected $content;

    public function __construct($content, $options) {
        $this->content = $content;
    }

    public function get($key, $defaultValue = null) {
        return CEmail::Builder()->globalData()->get($key, $defaultValue);
    }

    /**
     * @return string
     */
    public function render() {
        $lang = $this->get('lang');
        $langAttribute = '';
        if (strlen((string) $lang) > 0) {
            $langAttribute = 'lang="' . $lang . '" ';
        }

        $backgroundColor = $this->get('backgroundColor');

        $backgroundColorAttribute = '';
        if (strlen((string) $backgroundColor) > 0) {
            $backgroundColorAttribute = ' style="background-color:' . $backgroundColor . ';"';
        }
        $title = $this->get('title', '');
        $breakpoint = $this->get('breakpoint', '480px');
        $componentHeadStyleHtml = carr::reduce($this->get('componentHeadStyle', []), function ($result, $compHeadStyle) use ($breakpoint) {
            return $result . "\n" . $this->resolveHeadStyle($compHeadStyle, $breakpoint);
        }, '');

        $headStyleHtml = carr::reduce($this->get('headStyle', []), function ($result, $headStyle) use ($breakpoint) {
            return $result . "\n" . $this->resolveHeadStyle($headStyle, $breakpoint);
        }, '');

        $styleHtml = implode('', $this->get('style', []));
        $headRaw = $this->get('headRaw');
        $headRawHtml = '';
        if ($headRaw != null) {
            $headRaw = carr::filter($headRaw, function ($item) {
                return $item != null;
            });
            $headRawHtml .= implode("\n", $headRaw);
        }

        return '
    <!doctype html>
    <html ' . $langAttribute . 'xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
      <head>
        <title>
          ' . $title . '
        </title>
        <!--[if !mso]><!-- -->
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <!--<![endif]-->
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style type="text/css">
          #outlook a { padding:0; }
          body { margin:0;padding:0;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%; }
          table, td { border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt; }
          img { border:0;height:auto;line-height:100%; outline:none;text-decoration:none;-ms-interpolation-mode:bicubic; }
          p { display:block;margin:13px 0; }
        </style>
        <!--[if mso]>
        <xml>
        <o:OfficeDocumentSettings>
          <o:AllowPNG/>
          <o:PixelsPerInch>96</o:PixelsPerInch>
        </o:OfficeDocumentSettings>
        </xml>
        <![endif]-->
        <!--[if lte mso 11]>
        <style type="text/css">
          .mj-outlook-group-fix, .c-outlook-group-fix { width:100% !important; }
        </style>
        <![endif]-->
        ' . $this->buildFontTags() . '
        ' . $this->buildMediaQueriesTags() . '
        <style type="text/css">
        ' . $componentHeadStyleHtml . '
        ' . $headStyleHtml . '
        ' . $styleHtml . '
        </style>
        ' . $headRawHtml . '
      </head>
      <body' . $backgroundColorAttribute . '>
        ' . $this->buildPreview() . '
        ' . $this->content . '
      </body>
    </html>
  ';
    }

    /**
     * Head style boleh berupa closure($breakpoint), string CSS, atau array string.
     *
     * @param mixed  $headStyle
     * @param string $breakpoint
     *
     * @return string
     */
    protected function resolveHeadStyle($headStyle, $breakpoint) {
        if ($headStyle instanceof Closure) {
            return (string) $headStyle($breakpoint);
        }
        if (is_array($headStyle)) {
            return implode("\n", array_map(function ($item) use ($breakpoint) {
                return $this->resolveHeadStyle($item, $breakpoint);
            }, $headStyle));
        }

        return (string) $headStyle;
    }

    public function buildPreview() {
        $content = $this->get('preview', '');
        if ($content === '') {
            return '';
        }

        return '
    <div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">
      ' . $content . '
    </div>
  ';
    }

    public function buildFontTags() {
        $content = $this->content;
        $fonts = $this->get('fonts', []);
        $inlineStyle = $this->get('inlineStyle', '');
        $toImport = [];
        foreach ($fonts as $name => $url) {
            $regex = '#"[^"]*font-family:[^"]*' . $name . '[^"]*"#mi';
            $inlineRegex = '#font-family:[^;}]*' . $name . '#mi';
            $inlineCallback = function ($s) use ($inlineRegex) {
                return preg_match($inlineRegex, $s);
            };

            if (preg_match($regex, $this->content) || carr::some($inlineStyle, $inlineCallback)) {
                $toImport[] = $url;
            }
        }

        if (count($toImport) > 0) {
            $toImportLink = implode("\n", carr::map($toImport, function ($url) {
                return '<link href="' . $url . '" rel="stylesheet" type="text/css">';
            }));
            $toImportStyle = implode("\n", carr::map($toImport, function ($url) {
                return '@import url(' . $url . ');';
            }));

            return '
      <!--[if !mso]><!-->
        ' . $toImportLink . '
        <style type="text/css">
          ' . $toImportStyle . '
        </style>
      <!--<![endif]-->' . "\n" . '
    ';
        }

        return '';
    }

    public function buildMediaQueriesTags() {
        $breakpoint = $this->get('breakpoint', '480px');
        $mediaQueries = $this->get('mediaQueries', []);
        $forceOWADesktop = $this->get('forceOWADesktop', false);
        if (count($mediaQueries) == 0) {
            return '';
        }
        $baseMediaQueries = carr::map($mediaQueries, function ($mediaQuery, $className) {
            return '.' . $className . ' ' . $mediaQuery;
        });
        $owaStyle = '';
        if ($forceOWADesktop) {
            $owaQueries = carr::map($baseMediaQueries, function ($mq) {
                return '[owa] ' . $mq;
            });
            $owaStyle = '<style type="text/css">' . "\n" . implode("\n", $owaQueries) . "\n" . '</style>';
        }
        $baseMediaQueriesStyle = implode("\n", $baseMediaQueries);

        return '
    <style type="text/css">
      @media only screen and (min-width:' . $breakpoint . ') {
        ' . $baseMediaQueriesStyle . '
      }
    </style>
    ' . $owaStyle . '
  ';
    }
}
