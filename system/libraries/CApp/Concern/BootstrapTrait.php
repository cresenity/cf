<?php

defined('SYSPATH') or die('No direct access allowed.');

trait CApp_Concern_BootstrapTrait {
    /**
     * @var bool
     */
    protected static $registerControlBooted = false;

    /**
     * @var bool
     */
    protected static $registerBladeBooted = false;

    /**
     * @return void
     */
    public static function registerBlade() {
        if (!static::$registerBladeBooted) {
            CView::blade()->directive('CApp', [CApp_Blade_Directive::class, 'directive']);
            CView::blade()->directive('CAppStyles', [CApp_Blade_Directive::class, 'styles']);
            CView::blade()->directive('CAppScripts', [CApp_Blade_Directive::class, 'scripts']);
            CView::blade()->directive('CAppPageTitle', [CApp_Blade_Directive::class, 'pageTitle']);
            CView::blade()->directive('CAppTitle', [CApp_Blade_Directive::class, 'title']);
            CView::blade()->directive('CAppNav', [CApp_Blade_Directive::class, 'nav']);
            CView::blade()->directive('CAppSeo', [CApp_Blade_Directive::class, 'seo']);
            CView::blade()->directive('CAppContent', [CApp_Blade_Directive::class, 'content']);
            CView::blade()->directive('CAppPushScript', [CApp_Blade_Directive::class, 'pushScript']);
            CView::blade()->directive('CAppEndPushScript', [CApp_Blade_Directive::class, 'endPushScript']);
            CView::blade()->directive('CAppPrependScript', [CApp_Blade_Directive::class, 'prependScript']);
            CView::blade()->directive('CAppEndPrependScript', [CApp_Blade_Directive::class, 'endPrependScript']);
            CView::blade()->directive('CAppElement', [CApp_Blade_Directive::class, 'element']);
            CView::blade()->directive('CAppMessage', [CApp_Blade_Directive::class, 'message']);
            CView::blade()->directive('CAppPWA', [CApp_Blade_Directive::class, 'pwa']);
            CView::blade()->directive('CAppReact', [CApp_Blade_Directive::class, 'react']);
            CView::blade()->directive('CAppStartReact', [CApp_Blade_Directive::class, 'startReact']);
            CView::blade()->directive('CAppEndReact', [CApp_Blade_Directive::class, 'endReact']);
            CView::blade()->directive('CAppPreloader', [CApp_Blade_Directive::class, 'preloader']);
            static::$registerBladeBooted = true;
        }
    }

    /**
     * @return void
     */
    public static function registerControl() {
        if (!static::$registerControlBooted) {
            $manager = CManager::instance();
            $manager->registerControls([
                'text' => CElement_FormInput_Text::class,
                'textarea' => CElement_FormInput_Textarea::class,
                'number' => CElement_FormInput_Number::class,
                'email' => CElement_FormInput_Email::class,
                'datepicker' => CElement_FormInput_Date::class,
                'date' => CElement_FormInput_Date::class,
                'material-datetime' => CElement_FormInput_DateTime_MaterialDateTime::class,
                'daterange-picker' => CElement_FormInput_DateRange::class,
                'daterange-dropdown' => CElement_FormInput_DateRange_Dropdown::class,
                'daterange-button' => CElement_FormInput_DateRange_DropdownButton::class,
                'currency' => CElement_FormInput_Currency::class,
                'auto-numeric' => CElement_FormInput_AutoNumeric::class,
                'time' => CElement_FormInput_Time::class,
                'timepicker' => CElement_FormInput_Time::class,
                'clock' => CElement_FormInput_Clock::class,
                'clockpicker' => CElement_FormInput_Clock::class,
                'image' => CElement_FormInput_Image::class,
                'image-ajax' => CElement_FormInput_ImageAjax::class,
                'multi-image-ajax' => CElement_FormInput_MultipleImageAjax::class,
                'file' => CElement_FormInput_File::class,
                'file-ajax' => CElement_FormInput_FileAjax::class,
                'multi-file-ajax' => CElement_FormInput_MultipleFileAjax::class,
                'password' => CElement_FormInput_Password::class,
                'select' => CElement_FormInput_Select::class,
                'minicolor' => CElement_FormInput_MiniColor::class,
                'map-picker' => CElement_FormInput_MapPicker::class,
                'hidden' => CElement_FormInput_Hidden::class,
                'select-tag' => CElement_FormInput_SelectTag::class,
                'selectsearch' => CElement_FormInput_SelectSearch::class,
                'checkbox' => CElement_FormInput_Checkbox::class,
                'checkbox-list' => CElement_FormInput_CheckboxList::class,
                'switcher' => CElement_FormInput_Checkbox_Switcher::class,
                'summernote' => CElement_FormInput_Textarea_Summernote::class,
                'radio' => CElement_FormInput_Radio::class,
                'label' => CElement_FormInput_Label::class,
                'quill' => CElement_FormInput_Textarea_Quill::class,
                'ckeditor' => CElement_FormInput_Textarea_CKEditor::class,
                'slider' => CElement_FormInput_Slider::class,
                'fileupload' => CElement_FormInput_MultipleImageAjax::class,
            ]);

            static::$registerControlBooted = true;
        }
    }
}
