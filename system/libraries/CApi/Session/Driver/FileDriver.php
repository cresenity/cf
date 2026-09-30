<?php

class CApi_Session_Driver_FileDriver extends CApi_Session_DriverAbstract {
    public function close() {
        return true;
    }

    public function destroy($id) {
        return true;
        //return $this->disk->delete($this->getFilePath($id));
    }

    public function gc($maxlifetime) {
        return true;
    }

    public function exists($id) {
        $path = $this->getFilePath($id);

        return file_exists($path);
    }

    public function read($id) {
        $path = $this->getFilePath($id);
        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);

            //return include $path;
        }

        return [];
    }

    public function regenerate() {
    }

    public function write($id, $data) {
        $path = $this->getFilePath($id);
        $dir = dirname($path);
        // is_dir() lalu mkdir() adalah check-then-act: dua request bersamaan bisa lolos
        // pengecekan is_dir() yang sama lalu berebut mkdir(), salah satu gagal diam-diam (@).
        // Cek ulang is_dir() setelah mkdir() gagal - request lain mungkin sudah membuatnya duluan.
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }
        CFile::put($path, json_encode($data), true);
        //CFile::putPhpValue($path, $data);
    }

    public function getFilePath($sessionId) {
        $strYmd = substr($sessionId, 0, 8);
        $strH = substr($sessionId, 8, 2);
        $sessionPath = rtrim($this->basePath(), '/') . '/' . $strYmd . '/' . $strH . '/';

        return $sessionPath . $sessionId . '.php';
    }

    protected function basePath() {
        $basePath = DOCROOT . 'application/' . CF::appCode() . '/default/sessions/';
        if (strlen($this->group) > 0) {
            $basePath .= $this->group . '/';
        }

        return $basePath;
    }
}
