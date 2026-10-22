<?php

return [
    'excanvas' => [
        'js' => ['libs' . DS . 'excanvas.min.js'],
    ],
    'canvas-to-blob' => [
        'js' => ['libs' . DS . 'canvas-to-blob.min.js'],
    ],
    'load-image' => [
        'js' => ['libs' . DS . 'load-image.min.js'],
    ],
    'tmpl' => [
        'js' => ['libs' . DS . 'tmpl.min.js'],
    ],
    'vue' => [
        'js' => [
            'libs' . DS . 'vue.min.js',
            'libs' . DS . 'vue-loader.js',
        ],
    ],
    'axios' => [
        'js' => [
            'libs' . DS . 'axios.min.js',
        ],
    ],
    'jquery' => [
        'js' => ['libs' . DS . 'jquery-3.5.1.min.js'],
    ],
    'alpine' => [
        'js' => ['libs' . DS . 'alpine.js'],
    ],
    'jquery-2.1.0' => [
        'js' => ['libs/jquery-2.1.0/jquery.min.js'],
    ],
    'bootstrap' => [
        'js' => [
            'libs/bootstrap-4.5/bootstrap.bundle.min.js',
        ],
        'css' => ['libs/bootstrap-4.5/bootstrap.min.css'],
    ],
    'bootstrap-2.3' => [
        'js' => ['libs/bootstrap-2.3/bootstrap.min.js'],
        'css' => ['libs/bootstrap-2.3/bootstrap.css', 'libs/bootstrap-2.3/bootstrap-responsive.css'],
    ],
    'bootstrap-switch' => [
        'js' => ['plugins' . DS . 'bootstrap-switch' . DS . 'bootstrap-switch.js'],
        'css' => ['plugins' . DS . 'bootstrap-switch' . DS . 'bootstrap-switch.css'],
    ],
    'font-awesome' => [
        'css' => ['font-awesome.css'],
    ],
    'font-awesome-4.5.0' => [
        'css' => ['plugins/font-awesome/font-awesome 4.5.0.min.css'],
    ],
    'jquery.ui' => [
        'js' => ['libs' . DS . 'jquery.ui.custom.js'],
        'css' => ['plugins' . DS . 'jquery-ui' . DS . 'smoothness' . DS . 'jquery-ui.css', 'plugins' . DS . 'jquery-ui' . DS . 'smoothness' . DS . 'jquery.ui.theme.css'],
    ],
    'jquery-ui-1.12.1.custom' => [
        'js' => ['libs' . DS . 'jquery-ui-1.12.1.custom' . DS . 'jquery-ui.min.js'],
        'css' => [
            'plugins' . DS . 'jquery-ui' . DS . 'smoothness' . DS . 'jquery-ui-1.12.1.min.css',
            'plugins' . DS . 'jquery-ui' . DS . 'smoothness' . DS . 'jquery-ui-1.12.1.theme.min.css',
            'plugins' . DS . 'jquery-ui' . DS . 'smoothness' . DS . 'jquery-ui.css',
            'plugins' . DS . 'jquery-ui' . DS . 'smoothness' . DS . 'jquery.ui.theme.css'
        ],
    ],
    'jquery.lazyload' => [
        'js' => ['plugins' . DS . 'lazyload' . DS . 'jquery.lazyload.min.js'],
    ],
    'jquery.dialog2' => [
        'js' => ['plugins' . DS . 'dialog2' . DS . 'jquery.dialog2.js', 'plugins' . DS . 'dialog2' . DS . 'jquery.dialog2.helpers.js'],
        'css' => ['plugins' . DS . 'dialog2' . DS . 'jquery.dialog2.css'],
    ],
    'jquery.datatable' => [
        'css' => [
            'plugins/datatable/datatables.css',
            'plugins/datatable/fixedColumns.dataTables.min.css',
            'plugins/datatable/fixedHeader.dataTables.min.css',
        ],
        'js' => [
            'plugins/datatable/datatables.js',
            'plugins/datatable/dataTables.fixedColumns.min.js',
            'plugins/datatable/dataTables.fixedHeader.min.js',
        ],
    ],
    'jquery.datatable.tabletools' => [
        'js' => [
            'plugins' . DS . 'datatable' . DS . 'TableTools.min.js',
        ],
        'requirements' => ['jquery.datatable'],
    ],
    'jquery.datatable.colreorder' => [
        'css' => [
            'plugins/datatable/colReorder.dataTables.min.css',
        ],
        'js' => [
            'plugins/datatable/dataTables.colReorder.min.js',
        ],
        'requirements' => ['jquery.datatable'],
    ],
    'jquery.datatable.responsive' => [
        'css' => [
            'plugins/datatable/responsive.dataTables.min.css',
        ],
        'js' => [
            'plugins/datatable/dataTables.responsive.min.js',
        ],
        'requirements' => ['jquery.datatable'],
    ],
    'jquery.datatable.responsive.bs4' => [
        'css' => [
            'plugins/datatable/responsive.bootstrap4.min.css',
        ],
        'js' => [
            'plugins/datatable/responsive.bootstrap4.min.js',
        ],
        'requirements' => ['jquery.datatable'],
    ],
    'jquery.datatable.colvis' => [
        'js' => [
            'plugins' . DS . 'datatable' . DS . 'ColVis.min.js',
        ],
        'requirements' => ['jquery.datatable'],
    ],
    'jquery.datatable.columnfilter' => [
        'js' => [
            'plugins' . DS . 'datatable' . DS . 'jquery.dataTables.columnFilter.js',
        ],
        'requirements' => ['jquery.datatable'],
    ],
    'chosen' => [
        'js' => ['plugins' . DS . 'chosen' . DS . 'chosen.jquery.min.js'],
        'css' => ['plugins' . DS . 'chosen' . DS . 'chosen.css'],
    ],
    'jquery.nestable' => [
        'js' => ['plugins' . DS . 'nestable' . DS . 'jquery.nestable.js'],
        'css' => ['plugins' . DS . 'nestable' . DS . 'jquery.nestable.css'],
    ],
    'cresenity' => [
        'js' => [
            'cresenity.js',
        ],
        'css' => [
            'cresenity.colors.css',
            'cresenity.main.css',
            'cresenity.responsive.css',
            'cresenity.retail.css',
            'cresenity.widget.css',
            'cresenity.table.css',
            'cresenity.css',
        ],
    ],
    'vkeyboard' => [
        'js' => ['plugins' . DS . 'vkeyboard' . DS . 'bootstrap-vkeyboard.js'],
        'css' => ['plugins' . DS . 'vkeyboard' . DS . 'bootstrap-vkeyboard.css'],
    ],
    'jquery-autocomplete' => [
        'js' => ['plugins' . DS . 'jquery-autocomplete' . DS . 'jquery-autocomplete.js'],
    ],
    'fileupload' => [
        'js' => ['plugins' . DS . 'fileupload' . DS . 'bootstrap-fileupload.min.js'],
    ],
    'peity' => [
        'js' => ['plugins' . DS . 'peity' . DS . 'jquery.peity.min.js'],
    ],
    'colorpicker' => [
        'js' => ['plugins' . DS . 'colorpicker' . DS . 'bootstrap-colorpicker.js'],
        'css' => ['plugins' . DS . 'colorpicker' . DS . 'colorpicker.css'],
    ],
    'wysihtml5' => [
        'js' => [
            'libs' . DS . 'wysihtml5-0.3.0.js',
            'plugins' . DS . 'wysihtml5' . DS . 'bootstrap-wysihtml5.js',
        ],
        'css' => ['plugins' . DS . 'wysihtml5' . DS . 'bootstrap-wysihtml5.css'],
    ],
    'notify' => [
        'js' => ['plugins' . DS . 'notify' . DS . 'bootstrap-notify.js'],
        'css' => [
            'plugins' . DS . 'notify' . DS . 'bootstrap-notify.css',
            'plugins' . DS . 'notify' . DS . 'bootstrap-notify-alert-backgloss.css',
        ],
    ],
    'bootbox' => [
        'js' => ['plugins' . DS . 'bootbox' . DS . 'bootbox.all.min.js'],
    ],
    'bootbox4.4.0' => [
        'js' => ['plugins' . DS . 'bootbox' . DS . 'bootboxbootstrap3.min.js'],
    ],
    'form' => [
        'js' => ['plugins' . DS . 'form' . DS . 'jquery.form.js'],
    ],
    'controls' => [
        'js' => ['plugins' . DS . 'controls' . DS . 'jquery.controls.js'],
    ],
    'event' => [
        'js' => [
            'plugins' . DS . 'event' . DS . 'jquery.event.move.js',
            'plugins' . DS . 'event' . DS . 'jquery.event.swipe.js'
        ],
    ],
    'slimscroll' => [
        'js' => [
            'plugins' . DS . 'slimscroll' . DS . 'jquery.slimscroll.js',
            'plugins' . DS . 'slimscroll' . DS . 'jquery.slimscroll-horizontal.js',
        ],
    ],
    'effects' => [
        'js' => [
            'plugins' . DS . 'effects' . DS . 'jquery.effects.core.js',
            'plugins' . DS . 'effects' . DS . 'jquery.effects.slide.js',
        ],
    ],
    'validation' => [
        'js' => [
            'plugins' . DS . 'validation-engine' . DS . 'jquery.validationEngine-3.0.0.js',
            'plugins' . DS . 'validation-engine' . DS . 'languages' . DS . 'jquery.validationEngine-en.js',
        ],
        'css' => ['plugins' . DS . 'validation-engine' . DS . 'jquery.validationEngine.css'],
    ],
    'validate' => [
        'js' => [
            'plugins' . DS . 'validate' . DS . 'validate.js',
        ],
    ],
    'ckeditor' => [
        'js' => ['plugins' . DS . 'ckeditor' . DS . 'ckeditor.js'],
    ],
    'ckeditor-4' => [
        'js' => ['plugins' . DS . 'ckeditor' . DS . '4.5.9' . DS . 'ckeditor.js'],
    ],
    'isotope' => [
        'js' => ['plugins' . DS . 'isotope' . DS . 'jquery.isotope.min.js'],
    ],
    'easing' => [
        'js' => ['plugins' . DS . 'easing' . DS . 'jquery-easing-1.3.js'],
    ],
    'select2_v4' => [
        'js' => ['plugins' . DS . 'select2' . DS . 'select2_v4.js'],
        'css' => ['plugins' . DS . 'select2' . DS . 'select2_v4.css'],
    ],
    'select2' => [
        'js' => ['plugins' . DS . 'select2' . DS . 'select2.full.js'],
        'css' => ['plugins' . DS . 'select2' . DS . 'select2-4.0.0.min.css'],
    ],
    'select2-4.0' => [
        'js' => ['plugins' . DS . 'select2' . DS . 'select2.full.js'],
        'css' => ['plugins' . DS . 'select2' . DS . 'select2-4.0.0.min.css'],
    ],
    'datepicker' => [
        'js' => ['plugins' . DS . 'datepicker' . DS . '1.9' . DS . 'bootstrap-datepicker.js'],
        'css' => ['plugins' . DS . 'datepicker' . DS . '1.9' . DS . 'bootstrap-datepicker.css'],
    ],
    'bootstrap3-datepicker' => [
        'js' => ['plugins' . DS . 'datepicker' . DS . 'bootstrap3-datepicker.js'],
        'css' => ['plugins' . DS . 'datepicker' . DS . 'datepicker.css'],
    ],
    'timepicker' => [
        'js' => ['plugins' . DS . 'timepicker' . DS . 'bootstrap-timepicker.min.js'],
        'css' => ['plugins' . DS . 'timepicker' . DS . 'bootstrap-timepicker.min.css'],
    ],
    'image-gallery' => [
        'css' => ['plugins' . DS . 'image-gallery' . DS . 'bootstrap-image-gallery.min.css'],
    ],
    'modernizr' => [
        'js' => ['libs' . DS . 'modernizr.custom.js'],
    ],
    'multiselect' => [
        'js' => ['plugins' . DS . 'multiselect' . DS . 'jquery.multi-select.js'],
        'css' => ['plugins' . DS . 'multiselect' . DS . 'multi-select.css'],
    ],
    'terminal' => [
        'css' => [
            'plugins' . DS . 'terminal' . DS . 'jquery.terminal.min.css',
        ],
        'js' => [
            //"plugins" . DS . "terminal" . DS . "jquery.mousewheel-min.js",
            'plugins' . DS . 'terminal' . DS . 'jquery.terminal.min.js',
        ],
    ],
    'elfinder' => [
        'js' => [
            'plugins' . DS . 'elfinder' . DS . 'elfinder.min.js'
        ],
        'css' => [
            'plugins' . DS . 'elfinder' . DS . 'elfinder.min.css',
            'plugins' . DS . 'elfinder' . DS . 'theme-bootstrap-libreicons-svg.css',
        ],
    ],
    'jquery.filemanager' => [
        'js' => ['plugins' . DS . 'jquery' . DS . 'fileManager' . DS . 'jquery.fileManager.js'],
        'css' => ['plugins' . DS . 'jquery' . DS . 'fileManager' . DS . 'jquery.fileManager.css'],
    ],
    'prettify' => [
        'js' => ['plugins' . DS . 'google-code-prettify' . DS . 'prettify.js'],
        'css' => ['plugins' . DS . 'google-code-prettify' . DS . 'prettify.css'],
    ],
    'jstree' => [
        'js' => ['plugins' . DS . 'jstree' . DS . 'jstree.min.js'],
        'css' => ['plugins' . DS . 'jstree' . DS . 'style.min.css'],
    ],
    'dropzone' => [
        'js' => ['plugins' . DS . 'dropzone' . DS . 'dropzone.js'],
        'css' => ['plugins' . DS . 'dropzone' . DS . 'dropzone.css'],
    ],
    'bootstrap-3.3.5' => [
        'css' => [
            'plugins/bootstrap-3.3.5/bootstrap.min.css',
        ],
        'js' => [
            'libs/bootstrap-3.3.5/bootstrap.js',
        ],
    ],
    'bootstrap-dropdown' => [
        'css' => [
            'bootstrap-dropdown.css',
        ],
        'js' => [
            'libs' . DS . 'bootstrap' . DS . 'bootstrap-dropdown.js'
        ],
    ],
    'jquery.datatable-bootstrap3' => [
        'css' => [
            'plugins' . DS . 'datatable' . DS . 'dataTables.bootstrap.min.css',
            'plugins' . DS . 'datatable' . DS . 'responsive.bootstrap.min.css',
        ],
        'js' => [
            'plugins' . DS . 'datatable' . DS . 'jquery.dataTables.js',
            'plugins' . DS . 'datatable' . DS . 'dataTables.bootstrap.js',
            'plugins' . DS . 'datatable' . DS . 'dataTables.responsive.2.0.2.min.js',
            //                "plugins" . DS . "datatable" . DS . "TableTools.min.js",
            //                "plugins" . DS . "datatable" . DS . "ColReorder.min.js",
            //                "plugins" . DS . "datatable" . DS . "ColVis.min.js",
            //                "plugins" . DS . "datatable" . DS . "jquery.dataTables.columnFilter.js",
        ],
    ],
    'bootstrap-slider' => [
        'css' => [
            'bootstrap-slider.css',
        ],
        'js' => [
            'libs/bootstrap/bootstrap-slider.js',
        ],
    ],
    'materialize' => [
        'css' => [
            'materialize/materialize.min.css',
            // "materialize/bootstrap-material-design.min.css",
            'materialize/material-icons.css',
            'materialize/swiper/swiper.min.css',
            'materialize/materialize.clockpicker.css',
            // "materialize/ripples.min.css",
            'materialize/materialize.css',
        ],
        'js' => [
            // "materialize/hammer.min.js",
            'materialize/materialize.min.js',
            // "materialize/material.min.js",
            // "materialize/materialize.amd.js",
            // "materialize/ripples.min.js",
            'materialize/swiper/swiper.jquery.js',
            'materialize/materialize.clockpicker.js',
            'materialize/jscroll/jquery.jscroll.js',
            'require.js',
            'materialize/material_main.js',
            'materialize/dlmenu/jquery.dlmenu.js',
            'materialize/dlmenu/modernizr.custom.js',
        ],
    ],
    'moment' => [
        'js' => [
            'plugins/momentjs/moment.js',
            'plugins/momentjs/moment-with-locales.min.js',
        ],
    ],
    'slick' => [
        'js' => [
            'plugins/slick/slick.min.js',
        ],
        'css' => [
            'plugins/slick/slick.css',
            'plugins/slick/slick-theme.css',
        ],
    ],
    'datepicker_material' => [
        'css' => [
            'plugins/datepicker_material/bootstrap-material-datetimepicker.css',
        ],
        'js' => [
            'plugins/datepicker_material/bootstrap-material-datetimepicker.js',
        ],
    ],
    'fullcalendar' => [
        'css' => [
            'plugins/fullcalendar/fullcalendar.min.css',
            //                "plugins/fullcalendar/fullcalendar.print.css",
        ],
        'js' => [
            'plugins/fullcalendar/fullcalendar.min.js',
        ],
    ],
    'swiper' => [
        'css' => [
            'materialize/swiper/swiper.min.css',
        ],
        'js' => [
            'materialize/swiper/swiper.jquery.js',
        ],
    ],
    'pace' => [
        'js' => [
            'plugins/pace/pace.js',
        ],
    ],
    'animate' => [
        'css' => [
            'plugins/animate/animate.css',
        ],
    ],
    'summernote' => [
        'css' => [
            // "plugins/summernote/summernote.css",
            'plugins/summernote/summernote-bs4.css',
        ],
        'js' => [
            // "plugins/summernote/summernote.min.js",
            'plugins/summernote/summernote-bs4.min.js',
        ],
    ],
    'jquery-3.2.1' => [
        'js' => ['libs/jquery-3.2.1/jquery.min.js'],
    ],
    'bootstrap-4' => [
        'css' => ['libs/bootstrap-4/bootstrap.css'],
        'js' => ['libs/bootstrap-4/popper.js', 'libs/bootstrap-4/bootstrap.js'],
    ],
    'bootstrap-4-material' => [
        'css' => ['libs/bootstrap-4-material/bootstrap-material.css'],
        'js' => ['libs/bootstrap-4/popper.js', 'libs/bootstrap-4/bootstrap.js', 'libs/material/material-ripple.js'],
    ],
    'bootstrap-4-datepicker' => [
        'css' => [
            'libs/bootstrap-4/plugins/datepicker/bootstrap-datepicker.css',
            'libs/bootstrap-4/plugins/daterangepicker/bootstrap-daterangepicker.css'
        ],
        'js' => [
            'libs/bootstrap-4/plugins/datepicker/bootstrap-datepicker.js',
            'libs/bootstrap-4/plugins/daterangepicker/bootstrap-daterangepicker.js'
        ],
        'requirements' => ['bootstrap-4-moment'],
    ],
    'bootstrap-4-material-datepicker' => [
        'css' => [
            'libs/bootstrap-4/plugins/datepicker/bootstrap-datepicker.css',
            'libs/bootstrap-4/plugins/daterangepicker/bootstrap-daterangepicker.css',
            'libs/bootstrap-4-material/plugins/datetimepicker/bootstrap-material-datetimepicker.css',
        ],
        'js' => [
            'libs/bootstrap-4/plugins/datepicker/bootstrap-datepicker.js',
            'libs/bootstrap-4/plugins/daterangepicker/bootstrap-daterangepicker.js',
            'libs/bootstrap-4-material/plugins/datetimepicker/bootstrap-material-datetimepicker.js',
        ],
        'requirements' => ['bootstrap-4-moment'],
    ],
    'bootstrap-4-moment' => [
        'js' => [
            'libs/bootstrap-4/plugins/moment/moment.js',
        ],
    ],
    'bootstrap-5' => [
        'css' => ['libs/bootstrap-5/bootstrap.min.css'],
        'js' => ['libs/bootstrap-5/bootstrap.bundle.js'],
    ],
    /*
     * ICON
     */
    'fontawesome-3' => [
        'css' => ['icon/fontawesome-3.css'],
    ],
    'fontawesome-4.5' => [
        'css' => ['icon/fontawesome-4.5.css'],
    ],
    'fontawesome-5' => [
        'css' => ['icon/fontawesome-5.css'],
    ],
    'fontawesome-5-f' => [
        'css' => ['icon/fontawesome-5-f.min.css'],
    ],
    'ionicons' => [
        'css' => ['icon/ionicons.css'],
        'files' => [
            'css/icon/ionicons'
        ],
    ],
    'linearicons' => [
        'css' => ['icon/linearicons.css'],
        'files' => [
            'css/icon/linearicons'
        ],
    ],
    'remixicon' => [
        'css' => ['icon/remixicon.css'],
        'files' => [
            'css/icon/remixicon'
        ],
    ],
    'materialdesignicons' => [
        'css' => ['icon/materialdesignicons.css'],
        'files' => [
            'css/icon/materialdesignicons'
        ],
    ],
    'bootstrap-icons' => [
        'css' => ['icon/bootstrap-icons.css'],
        'files' => [
            'css/icon/bootstrap-icons'
        ],
    ],
    'themify-icons' => [
        'css' => ['icon/themify-icons.css'],
        'files' => [
            'css/icon/themify-icons'
        ],
    ],
    'flag-icons' => [
        'css' => ['icon/flag-icons.css'],
    ],
    'material-design-iconic-font' => [
        'css' => ['icon/material-design-iconic-font.min.css'],
    ],
    'piconsthin' => [
        'css' => ['icon/piconsthin.css'],
    ],
    'osicon' => [
        'css' => ['icon/osicon.css'],
    ],
    'open-ionic' => [
        'css' => ['icon/open-ionic.css'],
    ],
    'pe-icon-7-stroke' => [
        'css' => ['icon/pe-icon-7-stroke.css'],
    ],
    'glyphicons' => [
        'css' => ['icon/glyphicons.css'],
    ],
    'jquery.event.drag' => [
        'js' => ['plugins/jquery.event.drag/jquery.event.drag-2.2.js'],
    ],
    'prism' => [
        'js' => [
            'plugins/prism/prism.js',
            'plugins/prism/components/prism-markup-templating.js',
        ],
        'css' => [
            'plugins/prism/prism.css'
        ],
    ],
    'prism-php' => [
        'js' => [
            'plugins/prism/components/prism-php.js',
        ],
        'css' => [
        ],
    ],
    'block-ui' => [
        'css' => ['spinkit.css'],
        'js' => ['plugins/block-ui/block-ui.js'],
    ],
    'layout-helpers' => [
        'js' => ['plugins/layout-helpers/layout-helpers.js'],
    ],
    'perfect-scrollbar' => [
        'css' => ['plugins/perfect-scrollbar/perfect-scrollbar.css'],
        'js' => ['plugins/perfect-scrollbar/perfect-scrollbar.js'],
    ],
    'sidenav' => [
        'js' => ['plugins/sidenav/sidenav.js'],
    ],
    'tabler-icons' => [
        'css' => ['icon/tabler-icons.css'],
        'files' => [
            'css/icon/tabler-icons',
        ],
    ],
    'theme-material' => [
        'css' => [
            'theme/theme-material/colors-material.css',
            'theme/theme-material/app-material.css',
            'theme/theme-material/theme-material.css',
        ],
    ],
    'auto-numeric' => [
        'js' => ['plugins/autonumeric/autonumeric.js'],
    ],
    'fullcalendar-3' => [
        'css' => [
            'plugins/fullcalendar-3/fullcalendar.min.css',
        ],
        'js' => [
            'plugins/fullcalendar-3/fullcalendar.min.js',
        ],
        'requirements' => ['moment'],
    ],
    'bootstrap-material-datetimepicker' => [
        'css' => [
            'plugins/bootstrap-material-datetimepicker/bootstrap-material-datetimepicker.css',
        ],
        'js' => [
            'plugins/bootstrap-material-datetimepicker/bootstrap-material-datetimepicker.js',
        ],
    ],
    'flot' => [
        'css' => [
            'plugins/flotjs/flot.css',
        ],
        'js' => [
            'plugins/flotjs/flot.js',
        ],
    ],
    'sparkline' => [
        'js' => [
            'plugins/sparklines/sparkline.js'
        ],
    ],
    'c3' => [
        'css' => [
            'plugins/c3/c3.css',
        ],
        'js' => [
            'plugins/d3/d3.js',
            'plugins/c3/c3.js',
        ],
    ],
    'chartjs' => [
        'js' => [
            'plugins/chartjs/chartjs.js',
        ],
    ],
    'chartjs-4.4.0' => [
        'js' => [
            'plugins/chartjs/4.4.0/chart.umd.js',
        ],
    ],

    //peta vektor. Berkas peta memuat data negaranya sendiri dan mendaftarkan
    //diri sebagai 'world_mill' begitu $.fn.vectorMap ada, jadi urutannya wajib:
    //pustaka dulu, peta menyusul
    'jvectormap' => [
        'css' => [
            'plugins/jvectormap/jquery-jvectormap.css',
        ],
        'js' => [
            'plugins/jvectormap/jquery-jvectormap.js',
            'plugins/jvectormap/world-mill.js',
        ],
    ],

    'chartist' => [
        'css' => [
            'plugins/chartist/chartist.css',
        ],
        'js' => [
            'plugins/chartist/chartist.js',
        ],
    ],
    'morris' => [
        'css' => [
            'plugins/morris/morris.css',
        ],
        'js' => [
            'plugins/morris/morris.js',
        ],
    ],
    'raphael' => [
        'js' => [
            'plugins/raphael/raphael.js',
        ],
    ],
    'vis' => [
        'js' => [
            'plugins/vis/vis.min.js',
        ],
        'css' => [
            'plugins/vis/vis.min.css',
        ],
    ],
    'toastr' => [
        'css' => ['plugins/toastr/toastr.css'],
        'js' => ['plugins/toastr/toastr.js']
    ],
    'minicolors' => [
        'js' => [
            'plugins/minicolors/minicolors.js',
        ],
        'css' => [
            'plugins/minicolors/minicolors.css',
        ],
    ],
    'dragula' => [
        'js' => [
            'plugins/dragula/dragula.js',
        ],
        'css' => [
            'plugins/dragula/dragula.css',
        ],
    ],
    'clipboard' => [
        'js' => [
            'plugins/clipboard/clipboard.js',
        ]
    ],
    'quill' => [
        'js' => [
            'plugins/quill/quill.js',
        ],
        'css' => [
            'plugins/quill/quill.core.css',
        ],
    ],
    'tippy' => [
        'js' => [
            'plugins/tippy/tippy.js',
        ],
        'css' => [
            'plugins/tippy/tippy.css',
        ],
    ],
    'waypoints' => [
        'js' => [
            'plugins/waypoints/jquery.waypoints.min.js',
        ],
    ],
    'locationpicker' => [
        'js' => [
            'plugins/locationpicker/locationpicker.jquery.js',
        ],
    ],
    'pdfjs' => [
        'js' => [
            'plugins/pdfjs/pdf.js',
            'plugins/pdfjs/viewer.js',
        ],
        'css' => [
            'plugins/pdfjs/viewer.css',
        ],
    ],
    'bootstrap-daterangepicker' => [
        'js' => [
            'plugins/bootstrap-daterangepicker/bootstrap-daterangepicker.js',
        ],
        'css' => [
            'plugins/bootstrap-daterangepicker/bootstrap-daterangepicker.css',
        ],
    ],
    'datefns' => [
        'js' => [
            'plugins/datefns/datefns.min.js',
        ],
    ],
    'cropper' => [
        'css' => [
            'plugins/cropper/cropper.css',
        ],
        'js' => [
            'plugins/cropper/cropper.js',
        ],
    ],
    'jquery.extendext' => [
        'js' => [
            'plugins/jquery-extendext/jquery-extendext.js',
        ]
    ],
    'jquery-query-builder' => [
        'css' => [
            'plugins/jquery-query-builder/query-builder.default.css',
        ],
        'js' => [
            'plugins/jquery-query-builder/query-builder.js',
            // 'plugins/jquery-query-builder/plugin/query-builder-bs4-tooltip-error.js',
        ],
        'requirements' => [
            'jquery.extendext',
            'bootstrap-icons',
        ],
    ],

    'redoc' => [
        'js' => [
            'plugins/redoc/redoc-mobx-before.js',
            'plugins/redoc/redoc.standalone.min.js',
            'plugins/redoc/redoc-mobx-after.js',
        ],
    ],
    'hljs' => [
        'js' => [
            'plugins/hljs/highlight.min.js',
        ],
        'css' => [
            // 'hljs/highlight.min.css',
            'plugins/hljs/hljs-dark.css',
            'plugins/hljs/hljs-light.css',
            'plugins/hljs/hljs.css',
        ],
    ],
    'ion-rangeslider' => [
        'js' => [
            'plugins/ion-rangeslider/ion.rangeSlider.min.js',
        ],
        'css' => [
            'plugins/ion-rangeslider/ion.rangeSlider.min.css',
        ],
    ],
    'wow' => [
        'js' => [
            'plugins/wow/wow.js',
        ],

    ],
    'swagger-ui' => [
        'js' => [
            'plugins/swagger-ui/swagger-ui-bundle.js',
            'plugins/swagger-ui/swagger-ui-standalone-preset.js',
        ],
        'css' => [
            'plugins/swagger-ui/swagger-ui.css',
        ],

    ],
    'aos' => [
        'js' => [
            'plugins/aos/aos.js',
        ],
        'css' => [
            'plugins/aos/aos.css',
        ],

    ],
    'datetimepicker' => [
        'js' => [
            'plugins/datetimepicker/datetimepicker.min.js',
        ],
        'css' => [
            'plugins/datetimepicker/datetimepicker.min.css',
        ],

    ],
    'mime-icons' => [
        'css' => [
            'plugins/mime-icons/mime-icons.min.css',
        ],

    ],
    'blockly' => [
        'js' => [
            'blockly' . DS . 'blockly_compressed.js',
            'blockly' . DS . 'blocks_compressed.js',
            'blockly' . DS . 'php_compressed.js',
            'blockly' . DS . 'msg' . DS . 'js' . DS . 'en.js',
            'element' . DS . 'blockly' . DS . 'blockly.js',
        ],
    ],

];
