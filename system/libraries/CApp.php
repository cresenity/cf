<?php

use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Renderable;

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @mixin CElement
 *
 * @see CRenderable
 * @see CElement
 *
 * @method CElement_Component_Action             addAction($id=null)
 * @method CElement_Component_Alert              addAlert($id=null)
 * @method CElement_Component_FileManager        addFileManager($id=null)
 * @method CElement_Component_Form               addForm($id=null)
 * @method CElement_Component_DataTable          addTable($id=null)
 * @method CElement_Component_ListGroup          addListGroup($id=null)
 * @method CElement_Component_Form_Field         addField($id=null)
 * @method CElement_Element_Div                  addDiv($id=null)
 * @method CElement_Element_Iframe               addIframe($id=null)
 * @method CElement_Element_A                    addA($id=null)
 * @method CElement_Element_H1                   addH1($id=null)
 * @method CElement_Element_H2                   addH2($id=null)
 * @method CElement_Element_H3                   addH3($id=null)
 * @method CElement_Element_H4                   addH4($id=null)
 * @method CElement_Element_H5                   addH5($id=null)
 * @method CElement_Element_H6                   addH6($id=null)
 * @method CElement_Element_H6                   addP($id=null)
 * @method CElement_Element_Span                 addSpan($id=null)
 * @method CElement_Element_Pre                  addPre($id=null)
 * @method CElement_List_ActionList              addActionList($id=null)
 * @method CElement_List_TabList                 addTabList($id=null)
 * @method CElement_FormInput_Select             addSelectControl($id=null)
 * @method CElement_FormInput_SelectSearch       addSelectSearchControl($id=null)
 * @method CElement_Template                     addTemplate($id=null)
 * @method CElement_View                         addView($view = null, $data = null, $id = null)
 * @method CElement_Component_Widget             addWidget($id=null)
 * @method CElement_Component_Gallery            addGallery($id=null)
 * @method CElement_Element_Img                  addImg($id=null)
 * @method CElement_Component_Image              addImage($id=null)
 * @method CElement_Component_Chart              addChart($id=null)
 * @method CElement_Component_Metric_ValueMetric addValueMetric($id=null)
 * @method CApp                                  addBr()
 * @method CApp                                  addHr()
 * @method CApp                                  add(mixed $renderable)
 * @method $this                                 addJs($js)
 */
class CApp implements CInterface_Responsable, Renderable, Jsonable {
    use CTrait_Compat_App,
        CTrait_Macroable,
        CTrait_RequestInfoTrait,
        CApp_Concern_OrgTrait,
        CApp_Concern_NavigationTrait,
        CApp_Concern_ViewElementTrait,
        CApp_Concern_ManageStackTrait,
        CApp_Concern_BreadcrumbTrait,
        CApp_Concern_VariablesTrait,
        CApp_Concern_ViewTrait,
        CApp_Concern_RendererTrait,
        CApp_Concern_AuthTrait,
        CApp_Concern_BootstrapTrait,
        CApp_Concern_TitleTrait;

    /**
     * @var null|CApp[]
     */
    public static $instance = null;

    /**
     * @var mixed
     */
    protected $renderer;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var null|string
     */
    protected $id = null;

    /**
     * @var array<string, CApp_PWA>
     */
    protected $pwa = [];

    /**
     * @var CApp_Element
     */
    protected $element;

    /**
     * @var null|Closure
     */
    protected $baseResolver = null;

    /**
     * @var string
     */
    protected $content = '';

    /**
     * @var string
     */
    protected $js = '';

    /**
     * @var string
     */
    private $custom_js = '';

    /**
     * @var string
     */
    private $custom_header = '';

    /**
     * @var string
     */
    private $custom_footer = '';

    /**
     * @var array
     */
    private $custom_data = [];

    /**
     * @var bool
     */
    private $signup = false;

    /**
     * @var bool
     */
    private $activation = false;

    /**
     * @var bool
     */
    private $resend = false;

    /**
     * @var string
     */
    private $additional_head = '';

    /**
     * @var array
     */
    private $ajaxData = [];

    /**
     * @var bool
     */
    private $renderMessage = true;

    /**
     * @var bool
     */
    private $keepMessage = false;

    /**
     * @var bool
     */
    private $useRequireJs = false;

    /**
     * @var null|bool
     */
    private static $haveScrollToTop = null;

    /**
     * @var bool
     */
    private $coreModuleIsRegistered = false;

    /**
     * @var null|CApp_Visitor
     */
    private $visitor;

    /**
     * Whether the app output has already been rendered. Set by CApp_Concern_RendererTrait.
     *
     * @var bool
     */
    protected $rendered = false;

    /**
     * @param null|string $domain
     */
    public function __construct($domain = null) {
        $this->element = new CApp_Element();

        //we load another configuration for this app
        //org configuration
        if (strlen((string) CF::orgCode()) > 0) {
            $orgBootFile = DOCROOT . 'application' . DS . $this->code() . DS . CF::orgCode() . DS . CF::orgCode() . EXT;
            if (file_exists($orgBootFile)) {
                include $orgBootFile;
            }
        }

        $appBootFile = DOCROOT . 'application' . DS . $this->code() . DS . $this->code() . EXT;

        if (file_exists($appBootFile)) {
            include $appBootFile;
        }

        $this->id = 'capp';
        //check login

        if (CF::config('app.update_last_request')) {
            $user = $this->user();

            if ($user != null) {
                if (!is_array($user) && is_object($user) && $user->user_id) {
                    //update last request
                    $db = c::db();
                    $db->table('users')->where('user_id', '=', $user->user_id)->update(['last_request' => date('Y-m-d H:i:s')]);
                }
            }
        }
        $haveUserLogin = CF::config('app.have_user_login');
        if ($haveUserLogin === false) {
            $this->authEnabled = false;
        }

        $this->baseResolver = function () {
            return CF::config('app.classes.base', CApp_Base::class);
        };
    }

    /**
     * @return void
     */
    public function __destruct() {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * @return CApp_Contract_BaseInterface
     */
    public function base() {
        return new CBase_ForwarderStaticClass(c::value($this->baseResolver));
    }

    /**
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters) {
        if (method_exists($this->element, $method)) {
            return call_user_func_array([$this->element, $method], $parameters);
        }
        if ($this->element->hasMacro($method)) {
            return call_user_func_array([$this->element, $method], $parameters);
        }

        throw new Exception('undefined method on CApp: ' . $method);
    }

    /**
     * @return CApp_SEO
     */
    public static function seo() {
        return CApp_SEO::instance();
    }

    /**
     * @param string $domain
     * @param mixed  $options
     *
     * @return CApp_Remote
     */
    public static function remote($domain = null, $options = []) {
        return CApp_Remote::instance($domain, $options);
    }

    /**
     * @param string $modelName
     *
     * @deprecated 1.3
     *
     * @return CApp_Model
     */
    public static function model($modelName) {
        $modelClass = 'CApp_Model_' . $modelName;

        return new $modelClass();
    }

    /**
     * @param string $domain
     *
     * @return CApp_Navigation
     */
    public static function nav($domain = null) {
        return CNavigation::manager();
    }

    /**
     * @param string $domain
     *
     * @return CApp_Navigation
     */
    public static function navigation($domain = null) {
        return CNavigation::manager();
    }

    /**
     * @return bool
     */
    public static function isAjax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) and is_string($_SERVER['HTTP_X_REQUESTED_WITH']) and strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * @return CApp_Temp
     */
    public static function temp() {
        return new CApp_Temp();
    }

    /**
     * @return CApp_Data
     */
    public static function data() {
        return new CApp_Data();
    }

    /**
     * @param string      $message
     * @param array       $params
     * @param null|string $lang
     *
     * @return null|string
     */
    public static function getTranslation($message, $params = [], $lang = null) {
        return c::__($message, $params, $lang);
    }

    /**
     * @param null|mixed $domain
     * @param null|mixed $dbName
     *
     * @deprecated 1.6 use c::db
     *
     * @return CDatabase_Connection
     */
    public static function db($domain = null, $dbName = null) {
        return CDatabase::manager()->connection($dbName);
    }

    /**
     * @param array|string $key
     * @param mixed        $value
     *
     * @return CApp
     */
    public function setAjaxData($key, $value = null) {
        if (is_array($key)) {
            $this->ajaxData = array_merge($this->ajaxData, $key);
        } else {
            $this->ajaxData[$key] = $value;
        }

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return void
     */
    public function setRenderMessage($bool) {
        $this->renderMessage = $bool;
    }

    /**
     * @param bool $bool
     *
     * @return void
     */
    public function setKeepMessage($bool) {
        $this->keepMessage = $bool;
    }

    /**
     * Get Translator instance.
     *
     * @return CTranslation_Translator
     */
    public function translator() {
        return CTranslation::translator();
    }

    /**
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     *
     * @return array
     */
    public function validate(array $data, array $rules, array $messages = [], array $customAttributes = []) {
        $validation = CValidation::factory();

        return $validation->validate($data, $rules, $messages, $customAttributes);
    }

    /**
     * @return null|int|string
     */
    public function appId() {
        return CF::appId();
    }

    /**
     * @return CManager
     */
    public function manager() {
        return CManager::instance();
    }

    /**
     * @return string
     */
    public function name() {
        return strlen(CF::appCode()) > 0 ? CF::appCode() : CF::appCode();
    }

    /**
     * @return string
     */
    public function code() {
        return CF::appCode();
    }

    /**
     * @param string $type
     * @param string $message
     *
     * @return void
     */
    public function message($type, $message) {
        return CApp_Message::add($type, $message);
    }

    /**
     * @return mixed
     */
    public function controller() {
        return CHTTP::kernel()->controller();
    }

    /**
     * @param string      $path
     * @param null|string $domain
     *
     * @return mixed
     */
    public static function config($path, $domain = null) {
        return CApp_Config::get($path, $domain);
    }

    /**
     * @param bool $install
     *
     * @return CApp
     */
    public static function factory($install = false) {
        //return new CApp($install);
        return self::instance($install);
    }

    /**
     * @param null|string $domain
     *
     * @return CApp
     */
    public static function instance($domain = null) {
        if ($domain == null) {
            $domain = CF::domain();
        }
        if (self::$instance == null) {
            self::$instance = [];
        }
        if (!isset(self::$instance[$domain])) {
            self::$instance[$domain] = new CApp($domain);
        }

        return self::$instance[$domain];
    }

    /**
     * Buang instance per-domain yang sudah dibuat.
     *
     * `instance()` di atas adalah singleton proses (bukan per-request) —
     * di produksi itu aman karena tiap request PHP-FPM adalah proses baru,
     * tapi test harness memproses banyak simulasi request dalam satu proses
     * yang sama (lihat CTesting_Concern_MakesHttpRequests::call(), yang
     * sudah reset CObserver dengan alasan yang sama). Tanpa reset ini,
     * widget/breadcrumb/title dari satu simulasi request nempel ke request
     * berikutnya kalau domain-nya sama. Nama beda dari method instance
     * `reset()` di bawah (yang cuma bersihin satu instance) — ini buang
     * semua instance per-domain sekaligus.
     *
     * @return void
     */
    public static function resetInstances() {
        self::$instance = null;
    }

    /**
     * @param bool $bool
     *
     * @return CApp
     */
    public function signup($bool = true) {
        $this->signup = $bool;

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return CApp
     */
    public function resend($bool = true) {
        $this->resend = $bool;

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return CApp
     */
    public function activation($bool = true) {
        $this->activation = $bool;

        return $this;
    }

    /**
     * @param string $js
     *
     * @return CApp
     */
    public function addCustomJs($js) {
        $this->custom_js .= $js;

        return $this;
    }

    /**
     * @param bool $force
     *
     * @return void
     */
    public function registerCoreModules($force = false) {
        if ($force || !$this->coreModuleIsRegistered) {
            $manager = CManager::instance();
            $theme = CManager::theme()->getCurrentTheme();
            $themeFile = CF::getFile('themes', $theme);
            if (file_exists($themeFile)) {
                $themeData = include $themeFile;
                $moduleArray = carr::get($themeData, 'client_modules');
                $cssArray = carr::get($themeData, 'css');
                $jsArray = carr::get($themeData, 'js');

                if ($moduleArray != null) {
                    foreach ($moduleArray as $module) {
                        $manager->registerThemeModule($module);
                    }
                }

                if ($cssArray != null) {
                    foreach ($cssArray as $css) {
                        $manager->asset()->theme()->registerCssFile($css);
                    }
                }
                if ($jsArray != null) {
                    foreach ($jsArray as $js) {
                        $manager->asset()->theme()->registerJsFiles($js);
                    }
                }
            }

            $manager->registerModule('block-ui');
        }
    }

    /**
     * @return CApp
     */
    public function reset() {
        $this->rendered = false;
        $this->element->clear();

        return $this;
    }

    /**
     * Alias of setCustomData.
     *
     * @param string $key
     * @param string $value
     *
     * @return CApp
     */
    public function addCustomData($key, $value) {
        return $this->setCustomData($key, $value);
    }

    /**
     * @param array|string $key
     * @param mixed        $value
     *
     * @return CApp
     */
    public function setCustomData($key, $value = null) {
        if (is_array($key)) {
            $this->custom_data = $key;

            return $this;
        }
        if (!is_array($this->custom_data)) {
            $this->custom_data = [];
        }
        $this->custom_data[$key] = $value;

        return $this;
    }

    /**
     * @param null|string $key
     * @param mixed       $default
     *
     * @return mixed
     */
    public function getCustomData($key = null, $default = null) {
        if ($key === null) {
            return $this->custom_data;
        }

        return carr::get($this->custom_data, $key, $default);
    }

    /**
     * @param null|int|string $roleId
     * @param null|int|string $orgId
     * @param null|string     $type
     *
     * @return array
     */
    public function getRoleChildList($roleId = null, $orgId = null, $type = null) {
        if (strlen($roleId) == 0) {
            $roleId = c::optional($this->role())->role_id;
        }
        if (strlen($orgId) == 0) {
            $orgId = CApp_Base::orgId();
        }
        $roleModel = c::container()->make($this->auth()->getRoleModelClass());
        /** @var CApp_Model_Roles $roleModel */
        $nodes = $roleModel->getDescendantsTree($roleId, $orgId, $type);
        $childList = [];

        $traverse = function ($childs) use (&$traverse, &$childList) {
            foreach ($childs as $child) {
                $depth = carr::get($child, 'depth');
                $childList[$child['role_id']] = cutils::indent($depth, '&nbsp;&nbsp;') . $child['name'];
                $traverse($child->getChildren()->orderBy('lft', 'asc')->get());
            }
        };

        $traverse($nodes);

        return $childList;
    }

    /**
     * @param string $buffer
     *
     * @return string
     */
    protected function minifyJavascript($buffer) {
        $minifier = new CManager_Asset_Compiler_Minify_MinifyJs();

        return $minifier->execute($buffer);
    }

    /**
     * @return array
     */
    public function toArray() {
        $data = [];
        $data['title'] = $this->title;
        $message = '';
        $messageOrig = '';
        if (!$this->keepMessage) {
            $messageOrig = CApp_Message::flashAll();
            if ($this->renderMessage) {
                $message = $messageOrig;
            }
        }

        $asset = CManager::asset();
        $html = $this->element->html();
        $js = $this->element->js();

        if (CF::config('app.javascript.minify')) {
            $js = $this->minifyJavascript($js);
        }

        //$js = $asset->renderJsRequire($js, 'cresenity.cf.requireJs');

        $cappScript = $this->yieldPushContent('capp-script');
        //strip cappScript from <script>
        //parse the output of view
        // preg_match_all('#<script>(.*?)</script>#ims', $cappScript, $matches);

        // foreach ($matches[1] as $value) {
        //     $js = $value . $js;
        // }

        //$js .= $cappScript;
        $assetData = [];
        $assetData['js'] = $asset->getAllJsFileUrl();
        $assetData['css'] = $asset->getAllCssFileUrl();
        $data['assets'] = $assetData;
        $data['html'] = $message . $html . $cappScript;
        $data['js'] = base64_encode($js);
        if (CF::config('app.debug')) {
            $data['jsRaw'] = $js;
        }

        //$data['css_require'] = $asset->getAllCssFileUrl();
        $data['message'] = $messageOrig;
        $data['ajaxData'] = $this->ajaxData;
        $data['html'] = mb_convert_encoding($data['html'], 'UTF-8', 'UTF-8');

        return $data;
    }

    /**
     * Get the collection of items as JSON.
     *
     * @param int $options
     *
     * @return string
     */
    public function toJson($options = 0) {
        $data = $this->toArray();

        return json_encode($data, $options);
    }

    /**
     * Alias of toJson.
     *
     * @param int $options
     *
     * @return string
     */
    public function json($options = 0) {
        return $this->toJson($options);
    }

    /**
     * @param Exception   $exception
     * @param null|string $email
     *
     * @return void
     */
    public static function sendExceptionEmail(Exception $exception, $email = null) {
        $ignoredExceptions = [
            CDaemon_Exception_AlreadyRunningException::class,
            CDaemon_Exception_AlreadyStoppedException::class
        ];

        if (!($exception instanceof CHTTP_Exception_NotFoundHttpException)) {
            $ignored = false;
            foreach ($ignoredExceptions as $ignoredException) {
                if (is_subclass_of($exception, $ignoredException) || get_class($exception) === $ignoredException) {
                    $ignored = true;

                    break;
                }
            }

            if (!$ignored) {
                $html = CApp_ErrorHandler::sendExceptionEmail($exception, $email);
            }
        }
    }

    /**
     * @param CHTTP_Request $request
     *
     * @return CHTTP_Response
     */
    public function toResponse($request) {
        if (c::request()->ajax()) {
            /** @var CApp $this */
            if (CDebug::bar()->isEnabled()) {
                CDebug::bar()->populateAssets();
            }
            CFEvent::run('CApp.beforeRender');
            $this->registerCoreModules();

            return c::response()->json($this->toArray());
        }

        return CHTTP::createResponse($this->render());
    }

    /**
     * @deprecated 1.8, use c::manager()->theme()->setTheme() instead
     *
     * @param string $theme
     *
     * @return void
     */
    public static function setTheme($theme) {
        return CManager::theme()->setTheme($theme);
    }

    /**
     * @param bool $bool
     *
     * @return void
     */
    public static function setHaveScrollToTop($bool = true) {
        static::$haveScrollToTop = $bool;
    }

    /**
     * @return bool
     */
    public static function haveScrollToTop() {
        if (static::$haveScrollToTop === null) {
            static::$haveScrollToTop = CF::config('cresjs', 'scroll_to_top', false);
        }

        return static::$haveScrollToTop;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return CApp
     */
    public function setData($key, $value) {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Get CApp Formatter Instance.
     *
     * @return CApp_Formatter
     */
    public static function formatter() {
        return CApp_Formatter::instance();
    }

    /**
     * @param string $group
     *
     * @return void
     */
    public function enablePWA($group) {
        $this->pwa($group)->enable();
    }

    /**
     * @param string $group
     *
     * @return CApp_PWA
     */
    public function pwa($group) {
        if ($this->pwa == null) {
            $this->pwa = [];
        }
        if (!isset($this->pwa[$group])) {
            $this->pwa[$group] = new CApp_PWA($group);
        }

        return $this->pwa[$group];
    }

    /**
     * @return CApp_Notification
     */
    public function notification() {
        return CApp_Notification::instance();
    }

    /**
     * @return CApp_Visitor
     */
    public function visitor() {
        if ($this->visitor == null) {
            $this->visitor = new CApp_Visitor(CF::config('visitor'));
        }

        return $this->visitor;
    }
}
