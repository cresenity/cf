<?php

class CAjax_Engine_FileUpload extends CAjax_Engine {
    use CAjax_Trait_UploadTrait;

    const FOLDER = 'fileupload';

    const FOLDER_INFO = 'fileuploadinfo';

    public function execute() {
        $data = $this->ajaxMethod->getData();
        $inputName = carr::get($data, 'inputName');
        $allowedExtension = carr::get($data, 'allowedExtension', []);
        $validationCallback = $this->resolveCallable(carr::get($data, 'validationCallback'));
        $asFileAjax = carr::get($data, 'asFileAjax');
        $withInfo = carr::get($data, 'withInfo', false);
        $diskName = carr::get($data, 'disk', CF::config('storage.temp'));
        $fileId = '';
        $fileName = '';
        $disk = $this->getDisk();
        $errCode = 0;
        $errMessage = '';

        try {
            if (isset($_FILES[$inputName], $_FILES[$inputName]['name'])) {
                for ($i = 0; $i < count($_FILES[$inputName]['name']); $i++) {
                    $fileName = $_FILES[$inputName]['name'][$i];
                    //$fileSize = $_FILES[$inputName]['size'][$i];
                    $ext = pathinfo($_FILES[$inputName]['name'][$i], PATHINFO_EXTENSION);
                    $this->checkExtension($ext, $allowedExtension);
                    if ($validationCallback && c::isCallable($validationCallback)) {
                        c::call($validationCallback, [$_FILES[$inputName]['name'][$i], $_FILES[$inputName]['tmp_name'][$i]]);
                    }

                    $extension = '.' . $ext;

                    $fileId = $this->generateFileId($extension);

                    $fullfilename = CTemporary::getPath(static::FOLDER, $fileId);

                    if (!$disk->put($fullfilename, file_get_contents($_FILES[$inputName]['tmp_name'][$i]))) {
                        throw new CAjax_Exception_UploadFailedException(c::__('element/upload.errorMessageUploadFailed'));
                    }

                    if ($withInfo) {
                        $infoData['filename'] = $fileName;
                        $infoData['fileId'] = $fileId;
                        $infoData['temporaryPath'] = $fullfilename;
                        $infoData['temporaryDisk'] = $diskName;
                        $infoData['url'] = CTemporary::getPublicUrl(static::FOLDER, $fileId);
                        $fullfilenameinf = CTemporary::publicPut(static::FOLDER_INFO, json_encode($infoData), $fileId);
                    }
                }
            }

            if (isset($_POST[$inputName])) {
                $fileDataArray = $_POST[$inputName];
                $filenameArray = $_POST[$inputName . '_filename'];

                if (!is_array($fileDataArray)) {
                    $fileDataArray = [$fileDataArray];
                }
                if (!is_array($filenameArray)) {
                    $filenameArray = [$filenameArray];
                }
                foreach ($fileDataArray as $k => $fileData) {
                    $fileName = carr::get($filenameArray, $k);

                    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                    $this->checkExtension($ext, $allowedExtension);
                    if ($validationCallback && c::isCallable($validationCallback)) {
                        c::call($validationCallback, [$fileName, $fileData]);
                    }

                    $extension = '.' . $ext;

                    $filteredData = substr($fileData, strpos($fileData, ',') + 1);
                    $unencodedData = base64_decode($filteredData);
                    $fileId = $this->generateFileId($extension);
                    $fullfilename = CTemporary::getPath(static::FOLDER, $fileId);

                    $disk->put($fullfilename, $unencodedData);
                    if ($withInfo) {
                        $infoData['filename'] = $fileName;
                        $infoData['fileId'] = $fileId;
                        $infoData['temporaryPath'] = $fullfilename;
                        $infoData['temporaryDisk'] = $diskName;
                        $infoData['url'] = CTemporary::getPublicUrl(static::FOLDER, $fileId);
                        $fullfilenameinf = CTemporary::publicPut(static::FOLDER_INFO, json_encode($infoData), $fileId);
                    }
                }
            }
        } catch (CAjax_Exception_UploadNotAllowedException $ex) {
            $errCode++;
            $errMessage = $ex->getMessage();
        } catch (CAjax_Exception_UploadFailedException $ex) {
            $errCode++;
            $errMessage = $ex->getMessage();
        }
        $returnData = [
            'fileId' => $fileId,
            'fileName' => $fileName,
            'url' => CTemporary::getPublicUrl(static::FOLDER, $fileId),
        ];
        $return = [
            'errCode' => $errCode,
            'errMessage' => $errMessage,
            'data' => $returnData
        ];

        return c::response()->json($return);
    }

    /**
     * Generates a unique file ID based on the current date and a random MD5 hash.
     *
     * @param string $extension the file extension
     *
     * @return string the generated file ID
     */
    public function generateFileId($extension) {
        return date('Ymd') . cutils::randmd5() . 'f' . $extension;
    }
}
