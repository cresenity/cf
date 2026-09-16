<?php

defined('SYSPATH') or die('No direct access allowed.');

class Controller_Cresenity extends CController {
    public function index() {
        return c::abort(404);
    }

    public function cron() {
        CCron::run();
    }

    /**
     * @return void
     */
    public function dispatch() {
        CQueue::run();
    }

    public function daemon() {
        try {
            CDaemon::cliRunner();
        } catch (CDaemon_Exception_AlreadyRunningException $ex) {
            //do nothing when exception is already running
        }
    }

    public function supervisor() {
        try {
            CDaemon::cliSupervisorRunner();
        } catch (CDaemon_Exception_AlreadyRunningException $ex) {
            //do nothing when exception is already running
        }
    }

    public function worker() {
        try {
            CDaemon::cliWorkerRunner();
        } catch (CDaemon_Exception_AlreadyRunningException $ex) {
            //do nothing when exception is already running
        }
    }

    public function component() {
        return CApp::component()->controllerHandler(func_get_args());
    }

    public function qc($className = null) {
        if ($className == null) {
            CF::show404();

            return;
        }
        CQC::cliRunner($className);
    }

    public function ajax() {
        $args = func_get_args();
        $method = carr::get($args, 0);
        $filename = $method . '.tmp';
        $file = CTemporary::getPath('ajax', $filename);

        $disk = CTemporary::disk();
        if (!$disk->exists($file)) {
            c::abort(404, c::__('failed to get temporary file :filename', ['filename' => $file]));
            //throw new Exception(c::__('failed to get temporary file :filename', ['filename' => $file]));
        }
        $json = $disk->get($file);
        CDebug::variable('cf-ajax', $json);
        $ajaxMethod = CAjax::createMethod($json)->setArgs($args);

        $response = $ajaxMethod->executeEngine();
        if (!($response instanceof Symfony\Component\HttpFoundation\Response)) {
            $response = c::response($response);
        }

        return $response;
    }

    public function captcha() {
        header('Content-type: image/jpeg');

        $width = 50;
        $height = 24;

        $my_image = imagecreatetruecolor($width, $height);

        imagefill($my_image, 0, 0, 0xFFFFFF);

        // add noise
        for ($c = 0; $c < 40; $c++) {
            $x = rand(0, $width - 1);
            $y = rand(0, $height - 1);
            imagesetpixel($my_image, $x, $y, 0x000000);
        }

        $x = rand(1, 10);
        $y = rand(1, 10);

        $rand_string = rand(1000, 9999);
        imagestring($my_image, 5, $x, $y, $rand_string, 0x000000);

        $session = c::session();
        $session->set('capp-captcha', md5($rand_string) . 'a4xn');

        imagejpeg($my_image);
        imagedestroy($my_image);
    }

    public function noimage($width = 200, $height = 150, $bgColor = 'EFEFEF', $txtColor = 'AAAAAA', $text = 'NO IMAGE') {
        if (strlen($text) > 0) {
            $text = urldecode($text);
        }
        //Create the image resource
        $width = (int) $width;
        $height = (int) $height;
        if ($width === 0) {
            $width = 1;
        }
        if ($height === 0) {
            $height = 1;
        }
        $image = imagecreate($width, $height);
        //Making of colors, we are changing HEX to RGB
        $bgColor = imagecolorallocate($image, base_convert(substr($bgColor, 0, 2), 16, 10), base_convert(substr($bgColor, 2, 2), 16, 10), base_convert(substr($bgColor, 4, 2), 16, 10));

        $txtColor = imagecolorallocate($image, base_convert(substr($txtColor, 0, 2), 16, 10), base_convert(substr($txtColor, 2, 2), 16, 10), base_convert(substr($txtColor, 4, 2), 16, 10));

        //Fill the background color
        imagefill($image, 0, 0, $bgColor);

        $fontFile = CManager_FontManager::getArialFontPath();
        $maxWidth = $width * 0.7;   // teks maksimal 80% lebar gambar
        $maxHeight = $height * 0.4; // teks maksimal 40% tinggi gambar
        $fontSize = 10;
        while (true) {
            $box = imagettfbbox($fontSize, 0, $fontFile, $text);
            $textWidth = abs($box[2] - $box[0]);
            $textHeight = abs($box[5] - $box[1]);

            if ($textWidth > $maxWidth || $textHeight > $maxHeight) {
                $fontSize--;

                break;
            }
            $fontSize++;
        }
        // Hitung posisi tengah
        $box = imagettfbbox($fontSize, 0, $fontFile, $text);
        $textWidth = abs($box[2] - $box[0]);
        $textHeight = abs($box[5] - $box[1]);
        $x = ($width - $textWidth) / 2;
        $y = ($height + $textHeight) / 2;
        // Render teks
        imagettftext($image, $fontSize, 0, $x, $y, $txtColor, $fontFile, $text);

        //Tell the browser what kind of file is come in
        $headers = [
            'Content-Type' => 'image/png',
        ];

        return c::response()->stream(function () use ($image) {
            //Output the newly created image in png format
            imagepng($image);
            //Free up resources
            imagedestroy($image);
        }, 200, $headers);
    }

    public function transparent($width = 100, $height = 100) {
        $img = imagecreatetruecolor($width, $height);
        imagesavealpha($img, true);
        $color = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $color);
        //Tell the browser what kind of file is come in
        $headers = [
            'Content-Type' => 'image/png',
        ];

        return c::response()->stream(function () use ($img) {
            //Output the newly created image in png format
            imagepng($img);
            //Free up resources
            imagedestroy($img);
        }, 200, $headers);
    }

    public function avatar($method = 'initials') {
        // if (!function_exists('gd_info')) {
        //     throw new Exception('GD Library extension must be installed/enabled to use avatar endpoint.');
        // }
        if (!function_exists('finfo_buffer')) {
            throw new Exception('PHP fileinfo extension must be installed/enabled to use avatar endpoint.');
        }

        $engineName = 'Initials';
        switch ($method) {
            case 'initials':
                $engineName = 'Initials';

                break;
        }

        $avatarApi = CImage::avatar($engineName)->api();
        /*
        if (!isset($_GET['noheader'])) {
            header('Content-type: image/png');
            header('Pragma: public');
            header('Cache-Control: max-age=172800');
            header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 172800));
        }
        $avatarApi->render();
        */
        //ob_start('ob_gzhandler');
        $headers = [
            'Content-Type' => 'image/png',
            'Pragma' => 'public',
            'Cache-Control' => 'max-age=172800',
            'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time() + 172800),
        ];

        if (isset($_GET['debug_avatar'])) {
            return $avatarApi->render();
        }

        return c::response($avatarApi->render(), 200, $headers);
        // return c::response()->stream(function () use ($avatarApi) {
        //     $avatarApi->render();
        // }, 200, $headers);
    }

    public function connector($engine, $method = null) {
        if ($method == null) {
            return c::abort(404);
        }
        $engineName = 'FileManager';
        switch ($engine) {
            case 'elfinder':
                $engineName = 'ElFinder';

                break;
            case 'fm':
                $engineName = 'FileManager';

                break;

            default:
                die('Error, Connector engine not found');

                break;
        }
        $options = [];
        $options['path'] = DOCROOT . 'temp/files';
        $connector = CManager_File::createConnector($engineName, $options);
        $connector->run($method);
    }

    public function pdf() {
        $app = CApp::instance();

        CManager::theme()->setThemeCallback(function ($theme) {
            return 'null';
        });

        CManager::registerModule('pdfjs');

        $app->setView('cresenity.pdf');

        return $app;
    }

    public function upload($method = 'temp') {
        $filesInput = $_FILES;
        $fileId = '';
        $fileIdPreview = '';
        $result = [];

        foreach ($filesInput as $k => $fileData) {
            //check for array
            $isArray = is_array(carr::get($fileData, 'name'));
            $transposedDataArray = [];
            if (!isset($result[$k])) {
                $result[$k] = [];
            }
            foreach ($fileData as $dataKey => $dataValue) {
                $dataArray = $dataValue;
                if (!$isArray) {
                    $dataArray = [$dataValue];
                }
                $i = 0;
                foreach ($dataArray as $value) {
                    $i++;
                    carr::set($transposedDataArray, $i . '.' . $dataKey, $value);
                }
            }
            foreach ($transposedDataArray as $transposedData) {
                $filename = carr::get($transposedData, 'name');
                $filetype = carr::get($transposedData, 'type');
                $filetmp = carr::get($transposedData, 'tmp_name');
                $filesize = carr::get($transposedData, 'size');
                $fileerror = carr::get($transposedData, 'error');

                $errFileCode = 0;
                $errFileMessage = '';

                switch ($fileerror) {
                    case UPLOAD_ERR_OK:
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $errFileCode++;
                        $errFileMessage = 'No file sent.';
                        // no break
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $errFileMessage = 'Exceeded filesize limit.';
                        // no break
                    default:
                        $errFileMessage = 'Unknown errors.';
                }

                $extension = '.' . pathinfo($filename, PATHINFO_EXTENSION);
                if (strtolower($extension) == 'php') {
                    die('fatal error');
                }
                $fileId = date('Ymd') . cutils::randmd5() . $extension;
                $fullfilename = CTemporary::makePath('upload', $fileId);
                $fullfilenameinf = CTemporary::makePath('upload', $fileId);
                $url = CTemporary::getUrl('upload', $fileId);
                if (!move_uploaded_file($filetmp, $fullfilename)) {
                    $errFileCode++;
                    $errFileMessage = 'Failed to move temporary file to new path';
                }
                $resultData['filename'] = $filename;
                $resultData['size'] = $filesize;
                $resultData['fileId'] = $fileId;
                $resultData['status'] = $errFileCode == 0;
                $resultData['message'] = $errFileCode == 0 ? 'Upload success' : $errFileMessage;
                $resultData['url'] = $url;
                $resultData['fullUrl'] = trim(curl::httpbase(), '/') . $url;
                $resultData['type'] = $filetype;
                $resultPutContent = file_put_contents($fullfilenameinf, json_encode($resultData));

                $result[$k][] = $resultData;
            }
        }

        echo json_encode($result);
    }

    public function qrcode() {
        $request = $_GET;
        $data = carr::get($request, 'd');
        $options = [];
        $options['s'] = carr::get($request, 's', 'qr');
        $qrcode = new CImage_QRCode($data, $options);
        $qrcode->outputImage();
    }

    public function symlink($appCode) {
        $appDir = DOCROOT . 'application' . DS . $appCode;
        if (is_dir($appDir)) {
            //valid appCode, we will create the symlink for this appCode
            $linkMap = ['media'];
            foreach ($linkMap as $folder) {
                $publicLink = DOCROOT . 'public' . DS . 'application' . DS . $appCode . DS . 'default' . DS . $folder;
                $targetFolder = DOCROOT . 'application' . DS . $appCode . DS . 'default' . DS . $folder;
                $publicFolder = dirname($publicLink);
                if (!is_dir($publicLink)) {
                    CFile::makeDirectory($publicFolder, 0755, true);
                }
                if (!file_exists($publicLink)) {
                    symlink($targetFolder, $publicLink);
                }
            }
        }
    }

    public function health() {
        return c::response('OK');
    }

    public function clear() {
        CView::blade()->clearCompiled();
    }

    public function broadcast($method = null) {
        $request = CHTTP::request();
        if ($method == 'auth') {
            if ($request->hasSession()) {
                $request->session()->reflash();
            }

            return CBroadcast::manager()->driver()->auth($request);
        }

        return c::response('Cresenity Broadcasting Endpoint', 200);
    }

    public function version() {
        return c::response(CF::version());
    }

    public function alive() {
        return $this->version();
    }

    /**
     * @param string $method
     *
     * @return \CApp
     */
    public function cache($method) {
        if ($method == 'delete') {
            $key = c::request()->key;
            $tags = c::request()->tags;

            $cache = CCache::manager();

            if (!empty($tags)) {
                $tags = json_decode($tags, true);
                $cache = $cache->tags($tags);
            } else {
                unset($tags);
            }

            $success = $cache->forget($key);

            return c::response()->json(compact('success'));
        }

        return c::abort(404);
    }

    public function editorjs($method, $submethod) {
        if ($method == 'upload') {
            $uploadHandler = c::manager()->editorJs()->createImageUploadHandler();

            if ($submethod == 'file') {
                return c::response()->json($uploadHandler->byFile(c::request()));
            }
            if ($submethod == 'url') {
                return c::response()->json($uploadHandler->byUrl(c::request()));
            }
        }

        return c::abort(404);
    }

    public function chat() {
        return c::view('cresenity.bot.chat');
    }

    public function chart() {
        $url = 'http://chart.apis.google.com/chart';
        $get = $_GET;
        if (!isset($get['chid'])) {
            $get['chid'] = md5(uniqid(rand(), true));
        }
        $url .= '?' . curl::asPostString($get);

        try {
            $context = stream_context_create(
                ['http' => [
                    'method' => 'GET',
                    'header' => 'Content-type: application/x-www-form-urlencoded' . "\r\n",
                ]]
            );
            fpassthru(fopen($url, 'r', false, $context));
            header('Content-type: image/png');
        } catch (Exception $ex) {
            $response = [];
            $response['errCode'] = '1';
            $response['errMessage'] = $ex->getMessage();
            $response['data'] = [];
            $response['data']['exception'] = get_class($ex); // Reflection might be better here
            $response['data']['trace'] = $ex->getTraceAsString();

            return c::response()->json($response);
        }
    }

    public function sse() {
        $request = c::request();
        $request->headers->set('X-Socket-Id', sprintf('%d.%d', random_int(1, 1_000_000_000), random_int(1, 1_000_000_000)));
        $responseFactory = CBroadcast_SSE::createServerSentEventStream();

        return $responseFactory->toResponse($request);
    }

    /**
     * @param string $method
     *
     * @return \CApp
     */
    public function auth($method) {
        if ($method == 'ping') {
            $appCode = c::request()->appCode;
            $sid = c::request()->sid;

            $user = c::app()->user();

            $lastActivity = c::session()->get('_last_activity');
            $diff = 0;
            if ($lastActivity) {
                $current = time();
                $diff = $current - $lastActivity;
            }

            return c::response()->json([
                'errCode' => 0,
                'errMessage' => '',
                'data' => [
                    'isLogin' => $user != null,
                    'elapsedInSeconds' => $diff,
                ]
            ]);
        }

        return c::abort(404);
    }

    public function tus() {
        $server = CStorage::tus()->createServer();
        // $entityBody = file_get_contents('php://input');
        // print_r(c::request()->headers);
        // die;

        $server->setApiPath('/cresenity/tus');
        $response = $server->serve(); // return an TusPhpS3\Http\Response

        return $response;
    }
}
