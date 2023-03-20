<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan <hery@itton.co.id>
 * @license Ittron Global Teknologi
 *
 * @since Nov 29, 2020
 */
trait CApp_Concern_BootstrapTrait {
    protected static $registerComponentBooted = false;

    protected static $registerControlBooted = false;

    protected static $registerBladeBooted = false;

    public static function registerComponent() {
        if (!static::$registerComponentBooted) {
            CComponent_RenameMe_SupportEvents::init();
            CComponent_RenameMe_SupportLocales::init();
            CComponent_RenameMe_SupportChildren::init();
            CComponent_RenameMe_SupportRedirects::init();
            CComponent_RenameMe_SupportValidation::init();
            CComponent_RenameMe_SupportFileUploads::init();
            CComponent_RenameMe_OptimizeRenderedDom::init();
            CComponent_RenameMe_SupportFileDownloads::init();
            CComponent_RenameMe_SupportActionReturns::init();
            CComponent_RenameMe_SupportBrowserHistory::init();

            CComponent_RenameMe_SupportComponentTraits::init();
            CView::blade()->precompiler(function ($string) {
                return (new CComponent_ComponentTagCompiler())->compile($string);
            });

            CView::blade()->directive('CAppComponent', [CComponent_BladeDirective::class, 'component']);
            CView::blade()->directive('this', [CComponent_BladeDirective::class, 'this']);
            CView::blade()->directive('entangle', [CComponent_BladeDirective::class, 'entangle']);

            CView::engineResolver()->register('blade', function () {
                return new CComponent_ComponentCompilerEngine();
            });
            CComponent_LifecycleManager::registerHydrationMiddleware([
                /* This is the core middleware stack of Livewire. It's important */
                /* to understand that the request goes through each class by the */
                /* order it is listed in this array, and is reversed on response */

                /* ↓    Incoming Request                  Outgoing Response    ↑ */
                /* ↓                                                           ↑ */
                /* ↓    Secure Stuff                                           ↑ */
                /* ↓ */ CComponent_HydrationMiddleware_SecureHydrationWithChecksum::class, /* --------------- ↑ */
                /* ↓ */ CComponent_HydrationMiddleware_NormalizeServerMemoSansDataForJavaScript::class, /* -- ↑ */
                /* ↓ */ CComponent_HydrationMiddleware_HashDataPropertiesForDirtyDetection::class, /* ------- ↑ */
                /* ↓                                                           ↑ */
                /* ↓    Hydrate Stuff                                          ↑ */
                /* ↓ */ CComponent_HydrationMiddleware_HydratePublicProperties::class, /* ------------------- ↑ */
                /* ↓ */ CComponent_HydrationMiddleware_CallPropertyHydrationHooks::class, /* ---------------- ↑ */
                /* ↓ */ CComponent_HydrationMiddleware_CallHydrationHooks::class, /* ------------------------ ↑ */
                /* ↓                                                           ↑ */
                /* ↓    Update Stuff                                           ↑ */
                /* ↓ */ CComponent_HydrationMiddleware_PerformDataBindingUpdates::class, /* ----------------- ↑ */
                /* ↓ */ CComponent_HydrationMiddleware_PerformActionCalls::class, /* ------------------------ ↑ */
                /* ↓ */ CComponent_HydrationMiddleware_PerformEventEmissions::class, /* --------------------- ↑ */
                /* ↓                                                           ↑ */
                /* ↓    Output Stuff                                           ↑ */
                /* ↓ */ CComponent_HydrationMiddleware_RenderView::class, /* -------------------------------- ↑ */
                /* ↓ */ CComponent_HydrationMiddleware_NormalizeComponentPropertiesForJavaScript::class, /* - ↑ */
            ]);

            CComponent_LifecycleManager::registerInitialDehydrationMiddleware([
                /* Initial Response */
                /* ↑ */ [CComponent_HydrationMiddleware_SecureHydrationWithChecksum::class, 'dehydrate'],
                /* ↑ */ [CComponent_HydrationMiddleware_NormalizeServerMemoSansDataForJavaScript::class, 'dehydrate'],
                /* ↑ */ [CComponent_HydrationMiddleware_HydratePublicProperties::class, 'dehydrate'],
                /* ↑ */ [CComponent_HydrationMiddleware_CallPropertyHydrationHooks::class, 'dehydrate'],
                /* ↑ */ [CComponent_HydrationMiddleware_CallHydrationHooks::class, 'initialDehydrate'],
                /* ↑ */ [CComponent_HydrationMiddleware_RenderView::class, 'dehydrate'],
                /* ↑ */ [CComponent_HydrationMiddleware_NormalizeComponentPropertiesForJavaScript::class, 'dehydrate'],
            ]);

            CComponent_LifecycleManager::registerInitialHydrationMiddleware([
                [CComponent_HydrationMiddleware_CallHydrationHooks::class, 'initialHydrate'],
            ]);

            if (method_exists(CView_ComponentAttributeBag::class, 'macro')) {
                CView_ComponentAttributeBag::macro('cf', function ($name) {
                    $entries = carr::head($this->whereStartsWith('cf:' . $name));

                    $directive = carr::head(array_keys($entries));
                    $value = carr::head(array_values($entries));

                    return new CComponent_CresDirective($name, $directive, $value);
                });
            }

            static::$registerComponentBooted = true;
        }
    }

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

    public static function registerControl() {
        if (!static::$registerControlBooted) {
            CFBenchmark::start('CApp.RegisterControl');
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
                'file-ajax' => CElement_FormInput_FileAjax::class,
                'password' => CElement_FormInput_Password::class,
                'select' => CElement_FormInput_Select::class,
                'minicolor' => CElement_FormInput_MiniColor::class,
                'map-picker' => CElement_FormInput_MapPicker::class,
                'hidden' => CElement_FormInput_Hidden::class,
                'select-tag' => CElement_FormInput_SelectTag::class,
                'selectsearch' => CElement_FormInput_SelectSearch::class,
                'checkbox' => CElement_FormInput_Checkbox::class,
                'checkbox-list' => CFormInputCheckboxList::class,
                'switcher' => CElement_FormInput_Checkbox_Switcher::class,
                'summernote' => CElement_FormInput_Textarea_Summernote::class,
                'radio' => CElement_FormInput_Radio::class,
                'label' => CElement_FormInput_Label::class,
                'quill' => CElement_FormInput_Textarea_Quill::class,
                'file' => CElement_FormInput_File::class,
                'ckeditor' => CFormInputCKEditor::class,
                'filedrop' => CFormInputFileDrop::class,
                'slider' => CFormInputSlider::class,
                'tooltip' => CFormInputTooltip::class,
                'fileupload' => CFormInputFileUpload::class,
                'wysiwyg' => CFormInputWysiwyg::class,
            ]);

            CFBenchmark::stop('CApp.RegisterControl');
            static::$registerControlBooted = true;
        }
    }
}
