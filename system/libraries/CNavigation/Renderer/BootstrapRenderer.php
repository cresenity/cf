<?php

defined('SYSPATH') or die('No direct access allowed.');

use CApp_Navigation_Helper as Helper;

class CNavigation_Renderer_BootstrapRenderer extends CNavigation_RendererAbstract {
    /**
     * @param null|array $navs
     * @param int        $level
     * @param int        $child
     *
     * @return bool|string
     */
    public function render($navs = null, $level = 0, &$child = 0) {
        if ($navs == null) {
            $navs = $this->navs;
        }
        if ($navs == null) {
            return false;
        }
        $html = '';
        $child_count = 0;
        if (!is_array($navs)) {
            $navs = [];
        }
        foreach ($navs as $d) {
            $child = 0;
            $pass = 0;
            $active_class = '';
            $controller = carr::get($d, 'controller');
            $method = carr::get($d, 'method');
            $label = carr::get($d, 'label');
            $icon = carr::get($d, 'icon');

            $child_html = '';

            $resolvedSubnav = CNavigation_Data::resolveSubnav($d);
            if (count($resolvedSubnav) > 0) {
                $child_html .= $this->render($resolvedSubnav, $level + 1, $child);
            }
            $url = carr::get($d, 'uri');
            if ($url == null) {
                $url = Helper::url($d);
            }

            if (!isset($url) || $url == null) {
                $url = '';
            }

            if (strlen($child_html) > 0 || strlen($url) > 0) {
                if (!Helper::accessAvailable($d, CF::appId(), CF::domain())) {
                    continue;
                }
                if (isset($d['controller']) && $d['controller'] != '') {
                    if (CF::config('app.have_user_access')) {
                        if (!Helper::haveAccess($d)) {
                            continue;
                        }
                    }
                }

                $child_count++;

                $border = carr::get($d, 'border');

                $find_nav = Helper::nav($d);

                if ($find_nav !== false) {
                    $active_class = ' active';
                }

                $li_class = 'sidenav-item ';
                if ($child > 0) {
                    $li_class .= ' with-right-arrow';
                    if ($level == 0) {
                        $li_class .= ' dropdown';
                    } else {
                        $li_class .= ' dropdown-submenu ';
                    }
                }

                $addition_style = '';
                if ($border == 'top') {
                    $addition_style = ' style="border-top:1px solid #bbb"';
                }
                if ($border == 'bottom') {
                    $addition_style = ' style="border-bottom:1px solid #bbb"';
                }

                $html .= '<li class="' . $li_class . $active_class . '" ' . $addition_style . '>';

                $iconClass = carr::get($d, 'icon');
                if (strlen($iconClass) > 0 && strpos($iconClass, 'fa-') === false && strpos($iconClass, 'ion-') === false) {
                    $iconClass = 'icon-' . $iconClass;
                }
                $icon_html = '';
                if (strlen($iconClass) > 0) {
                    $icon_html = '<i class="' . $iconClass . '"></i>';
                }
                if ($url == '') {
                    $caret = '';
                    if ($level == 0) {
                        $caret = '<b class="caret">';
                    }

                    $elem = '<a class="' . $active_class . ' dropdown-toggle sidenav-link sidenav-toggle" href="javascript:;" data-toggle="dropdown">' . $icon_html . '<span>' . c::__($label) . '</span>' . $caret . '</b>';
                    if ($child > 0) {
                        //$elem .= '<span class="label">'.$child.'</span>';
                    }
                    $elem .= "</a>\r\n";
                } else {
                    $url = '/' . trim($url, '/');
                    $target = '';
                    $notif = '';
                    if (isset($d['target']) && strlen($d['target']) > 0) {
                        $target = ' target="' . $d['target'] . '"';
                    }
                    if (isset($d['notif_count'])) {
                        $callable = $d['notif_count'];

                        if (is_callable($callable)) {
                            $notif = call_user_func($callable);
                        }
                    }

                    $strNotif = '';
                    if ($notif != null && $notif > 0) {
                        $strNotif = ' <span class="label label-info nav-notif nav-notif-count">' . $notif . '</span>';
                    }
                    $elem = '<a class="' . $active_class . ' sidenav-link" href="' . $url . '"' . $target . '>' . $icon_html . '<span>' . c::__($label) . '</span>' . $strNotif . "</a>\r\n";
                }
                $html .= $elem;
                $html .= $child_html;
                $html .= '</li>';
            }
        }
        if (strlen($html) > 0) {
            if ($level == 0) {
                $html = "  <ul class=\"mainnav \">\r\n" . $html . "  </ul>\r\n";
            } else {
                $html = "  <ul class=\"dropdown-menu\">\r\n" . $html . "  </ul>\r\n";
            }
        }
        if ($child_count == 0) {
            $html = '';
        }
        $child = $child_count;

        return $html;
    }
}
