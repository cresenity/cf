<?php

defined('SYSPATH') or die('No direct access allowed.');

CPolyfill::php74();
CPolyfill::php80();
CPolyfill::php81();
CPolyfill::php82();
CPolyfill::php83();
CPolyfill::php84();
CPolyfill::php85();
CFConfig::bootstrap();

CPagination_Paginator::useBootstrap();
CBootstrap::instance()->addBootstrapper([
    CApp_Bootstrapper_DependencyChecker::class,
]);

$domain = CF::domain();

CException::init();
CModel::setEventDispatcher(CEvent::dispatcher());
if (CF::config('collector.exception')) {
    //Bertipe Throwable, bukan Exception: pemilihan callback memakai
    //`is_a($e, <tipe parameter pertama>)`, sedangkan Error di PHP 7+ bukan
    //turunan Exception - keduanya hanya berbagi Throwable. Dengan tipe lama,
    //justru galat yang paling perlu terlihat (TypeError dan kerabatnya) tidak
    //pernah terkumpul.
    CException::exceptionHandler()->reportable(function (Throwable $e) {
        CDebug::collector()->collectException($e);
    });
}

if (CF::config('app.mail_error')) {
    CException::exceptionHandler()->reportable(function (Exception $e) {
        CApp::sendExceptionEmail($e);
    });
}

CFBenchmark::start('capp:bootstrap');
CApp::registerBlade();
CManager::registerBlade();

CApp::registerControl();
CFBenchmark::stop('capp:bootstrap');
if (!CF::isCli()) {
    if (CHTTP::request()->cookie('capp-profiler')) {
        CProfiler::enable();
    }
    if (CHTTP::request()->cookie('capp-debugbar')) {
        CDebug::bar()->enable();
    }
}

CApp_Auth_Features::setFeatures(CF::config('app.auth.features'));

if (CF::isTesting()) {
    CEvent::dispatcher()->listen(CLogger_Event_MessageLogged::class, function (CLogger_Event_MessageLogged $event) {
        if (isset($event->context['exception'])) {
            CTesting::loggedExceptionCollection()->push($event->context['exception']);
        }
    });
}

//CView::blade()->component('dynamic-component', CView_Component_DynamicComponent::class);
CView::blade()->component('icon', \CView_Component_IconComponent::class);
c::manager()->icon()->registerIconDirectory('orchid', DOCROOT . 'media/img/icons/orchid/');
if (CF::config('daemon.supervisor.enabled', false)) {
    CDaemon::bootSupervisor();
}
CBootstrap::instance()->boot();
CF::terminating(function () {
    CView_ComponentAbstract::flushCache();
});
