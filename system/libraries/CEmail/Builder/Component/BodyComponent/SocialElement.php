<?php

use CEmail_Builder_Helper as Helper;

class CEmail_Builder_Component_BodyComponent_SocialElement extends CEmail_Builder_Component_BodyComponent {
    protected static $tagName = 'c-social-element';
    protected static $endingTag = true;

    const IMG_BASE_URL = 'https://www.mailjet.com/images/theme/v1/icons/ico-social/';

    protected $defaultSocialNetworks = [
        'facebook' => [
            'share-url' => 'https://www.facebook.com/sharer/sharer.php?u=[[URL]]',
            'background-color' => '#3b5998',
            'src' => self::IMG_BASE_URL . 'facebook.png',
        ],
        'twitter' => [
            'share-url' => 'https://twitter.com/home?status=[[URL]]',
            'background-color' => '#55acee',
            'src' => self::IMG_BASE_URL . 'twitter.png',
        ],
        'google' => [
            'share-url' => 'https://plus.google.com/share?url=[[URL]]',
            'background-color' => '#dc4e41',
            'src' => self::IMG_BASE_URL . 'google-plus.png',
        ],
        'pinterest' => [
            'share-url' => 'https://pinterest.com/pin/create/button/?url=[[URL]]&media=&description=',
            'background-color' => '#bd081c',
            'src' => self::IMG_BASE_URL . 'pinterest.png',
        ],
        'linkedin' => [
            'share-url' => 'https://www.linkedin.com/shareArticle?mini=true&url=[[URL]]&title=&summary=&source=',
            'background-color' => '#0077b5',
            'src' => self::IMG_BASE_URL . 'linkedin.png',
        ],
        'instagram' => [
            'background-color' => '#3f729b',
            'src' => self::IMG_BASE_URL . 'instagram.png',
        ],
        'web' => [
            'src' => self::IMG_BASE_URL . 'web.png',
            'background-color' => '#4BADE9',
        ],
        'snapchat' => [
            'src' => self::IMG_BASE_URL . 'snapchat.png',
            'background-color' => '#FFFA54',
        ],
        'youtube' => [
            'src' => self::IMG_BASE_URL . 'youtube.png',
            'background-color' => '#EB3323',
        ],
        'tumblr' => [
            'src' => self::IMG_BASE_URL . 'tumblr.png',
            'share-url' => 'https://www.tumblr.com/widgets/share/tool?canonicalUrl=[[URL]]',
            'background-color' => '#344356',
        ],
        'github' => [
            'src' => self::IMG_BASE_URL . 'github.png',
            'background-color' => '#000000',
        ],
        'xing' => [
            'src' => self::IMG_BASE_URL . 'xing.png',
            'share-url' => 'https://www.xing.com/app/user?op=share&url=[[URL]]',
            'background-color' => '#296366',
        ],
        'vimeo' => [
            'src' => self::IMG_BASE_URL . 'vimeo.png',
            'background-color' => '#53B4E7',
        ],
        'medium' => [
            'src' => self::IMG_BASE_URL . 'medium.png',
            'background-color' => '#000000',
        ],
        'soundcloud' => [
            'src' => self::IMG_BASE_URL . 'soundcloud.png',
            'background-color' => '#EF7F31',
        ],
        'dribbble' => [
            'src' => self::IMG_BASE_URL . 'dribbble.png',
            'background-color' => '#D95988',
        ],
    ];
    protected $allowedAttributes = [
        'align' => 'enum(left,center,right)',
        'background-color' => 'color',
        'color' => 'color',
        'border-radius' => 'unit(px)',
        'font-family' => 'string',
        'font-size' => 'unit(px)',
        'font-style' => 'string',
        'font-weight' => 'string',
        'href' => 'string',
        'icon-size' => 'unit(px,%)',
        'icon-height' => 'unit(px,%)',
        'icon-padding' => 'unit(px,%){1,4}',
        'line-height' => 'unit(px,%,)',
        'name' => 'string',
        'padding-bottom' => 'unit(px,%)',
        'padding-left' => 'unit(px,%)',
        'padding-right' => 'unit(px,%)',
        'padding-top' => 'unit(px,%)',
        'padding' => 'unit(px,%){1,4}',
        'text-padding' => 'unit(px,%){1,4}',
        'src' => 'string',
        'alt' => 'string',
        'title' => 'string',
        'target' => 'string',
        'text-decoration' => 'string',
    ];
    protected $defaultAttributes = [
        'align' => 'left',
        'color' => '#000',
        'border-radius' => '3px',
        'font-family' => 'Ubuntu, Helvetica, Arial, sans-serif',
        'font-size' => '13px',
        'line-height' => '1',
        'padding' => '4px',
        'text-padding' => '4px 4px 4px 0',
        'target' => '_blank',
        'text-decoration' => 'none',
    ];

    public function getStyles() {
        $socialAttributes = $this->getSocialAttributes();
        $iconSize = carr::get($socialAttributes, 'icon-size');
        $iconHeight = carr::get($socialAttributes, 'icon-height');
        $backgroundColor = carr::get($socialAttributes, 'background-color');

        return [
            'td' => [
                'padding' => $this->getAttribute('padding'),
            ],
            'table' => [
                'background' => $backgroundColor,
                'border-radius' => $this->getAttribute('border-radius'),
                'width' => $iconSize,
            ],
            'icon' => [
                'padding' => $this->getAttribute('icon-padding'),
                'font-size' => '0',
                'height' => ($iconHeight != null ? $iconHeight : $iconSize),
                'vertical-align' => 'middle',
                'width' => $iconSize,
            ],
            'img' => [
                'border-radius' => $this->getAttribute('border-radius'),
                'display' => 'block',
            ],
            'tdText' => [
                'vertical-align' => 'middle',
                'padding' => $this->getAttribute('text-padding'),
            ],
            'text' => [
                'color' => $this->getAttribute('color'),
                'font-size' => $this->getAttribute('font-size'),
                'font-weight' => $this->getAttribute('font-weight'),
                'font-style' => $this->getAttribute('font-style'),
                'font-family' => $this->getAttribute('font-family'),
                'line-height' => $this->getAttribute('line-height'),
                'text-decoration' => $this->getAttribute('text-decoration'),
            ],
        ];
    }

    public function getSocialAttributes() {
        $name = (string) $this->getAttribute('name');
        $noShare = cstr::endsWith($name, '-noshare');
        $socialNetwork = carr::get($this->defaultSocialNetworks, $noShare ? substr($name, 0, -8) : $name, []);
        $href = $this->getAttribute('href');
        if ($href && !$noShare && isset($socialNetwork['share-url'])) {
            // jaringan berbagi: href pengguna disisipkan ke URL share jaringan
            $href = str_replace('[[URL]]', $href, $socialNetwork['share-url']);
        }

        $attrs = carr::reduce([
            'icon-size',
            'icon-height',
            'src',
            'background-color',
        ], function ($r, $attr) use ($socialNetwork) {
            $r[$attr] = $this->getAttribute($attr) ? $this->getAttribute($attr) : carr::get($socialNetwork, $attr);
            return $r;
        }, []);

        $attrs['href'] = $href;
        return $attrs;
    }

    public function render() {
        $socialAttributes = $this->getSocialAttributes();
        $src = carr::get($socialAttributes, 'src');
        $href = carr::get($socialAttributes, 'href');
        $iconSize = carr::get($socialAttributes, 'icon-size');
        $iconHeight = carr::get($socialAttributes, 'icon-height');

        $hasLink = !!$this->getAttribute('href');

        $tableAttr = [];
        $tableAttr['border'] = '0';
        $tableAttr['cellpadding'] = '0';
        $tableAttr['cellspacing'] = '0';
        $tableAttr['role'] = 'presentation';
        $tableAttr['style'] = 'table';

        $imgAttr = [];
        $imgAttr['alt'] = $this->getAttribute('alt');
        $imgAttr['title'] = $this->getAttribute('title');
        $imgAttr['height'] = $iconHeight ? $iconHeight : $iconSize;
        $imgAttr['src'] = $src;
        $imgAttr['style'] = 'img';
        $imgAttr['width'] = $iconSize;

        $openLink = '';
        $closeLink = '';
        if ($hasLink) {
            $openLink = '<a' . $this->htmlAttributes(['href' => $href, 'rel' => $this->getAttribute('rel'), 'target' => $this->getAttribute('target')]) . '>';
            $closeLink = '</a>';
        }

        $content = $this->getContent();
        $textContent = '';
        if ($content) {
            $openContentLink = '<span' . $this->htmlAttributes(['style' => 'text']) . '>';
            $closeContentLink = '</span>';
            if ($hasLink) {
                $openContentLink = '<a' . $this->htmlAttributes(['href' => $href, 'style' => 'text', 'rel' => $this->getAttribute('rel'), 'target' => $this->getAttribute('target')]) . '>';
                $closeContentLink = '</a>';
            }

            $textContent = '
          <td' . $this->htmlAttributes(['style' => 'tdText']) . '>
            ' . $openContentLink . '
              ' . $content . '
            ' . $closeContentLink . '
          </td>
          ';
        }
        return '
      <tr' . $this->htmlAttributes(['class' => $this->getAttribute('css-class')]) . '>
        <td' . $this->htmlAttributes(['style' => 'td']) . '>
          <table' . $this->htmlAttributes($tableAttr) . '>
            <tr>
              <td' . $this->htmlAttributes(['style' => 'icon']) . '>
                ' . $openLink . '
                    <img' . $this->htmlAttributes($imgAttr) . '/>
                  ' . $closeLink . '
                </td>
              </tr>
          </table>
        </td>
        ' . $textContent . '
      </tr>
    ';
    }
}
