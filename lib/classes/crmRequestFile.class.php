<?php

class crmRequestFile extends waRequestFile
{
    public static function convertFrom(waRequestFile $file, $persistent_path = null)
    {
        $res = new crmRequestFile($file->data, true);
        if ($persistent_path) {
            $res->conserve($persistent_path);
        }
        return $res;
    }

    /**
     * @param $persistent_path
     */
    public function conserve($persistent_path)
    {
        $this->copyTo($persistent_path);
        $this->data['tmp_name'] = $persistent_path;
    }

    public function exists()
    {
        return file_exists($this->tmp_name);
    }
}
