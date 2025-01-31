<?php

class crmFileUploadTmpController extends crmJsonController
{
    public function execute()
    {
        $hash = waRequest::post('hash', null, waRequest::TYPE_STRING_TRIM);

        if (!$hash) {
            return;
        }

        foreach (waRequest::file('files') as $f) {
            if ($f->uploaded()) {
                if (!self::saveFile($f, $hash)) {
                    $this->errors[] = sprintf(_w('Failed to upload file %s.'), $f->name);
                }
            } else {
                $this->errors[] = sprintf(_w('Failed to upload file %s.'), $f->name).' ('.$f->error.')';
            }
        }
    }

    protected static function saveFile(waRequestFile $f, $hash)
    {
        $is_request_file = $f instanceof waRequestFile;

        if ($is_request_file) {

            $temp_path = wa('crm')->getTempPath('mail', 'crm');

            $mail_dir = $temp_path.'/'.$hash;
            waFiles::create($mail_dir);

            if (!$f->moveTo($mail_dir, $f->name)) {
                return false;
            }
            return true;
        }
        return false;
    }
}
