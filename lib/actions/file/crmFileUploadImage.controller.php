<?php

/**
 * Upload image using WYSIWYG toolbar button.
 */
class crmFileUploadImageController extends waUploadJsonController
{
    protected $name;

    protected function process()
    {
        $f = waRequest::file('file');
        $f->transliterateFilename();
        $this->name = $f->name;
        if ($this->processFile($f)) {
            $this->response = wa()->getDataUrl('images/'.$this->name, true, 'crm');
        }
    }

    public function display()
    {
        $this->getResponse()->sendHeaders();
        if (!$this->errors) {
            echo json_encode(['url' => $this->response]);
        } else {
            echo json_encode(['error' => $this->errors]);
        }
    }

    protected function getPath()
    {
        return wa()->getDataPath('images', true);
    }

    protected function isValid($f)
    {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        if (!in_array(strtolower($f->extension), $allowed)) {
            $this->errors[] = sprintf(_w("Files with extensions %s are allowed only."), '*.'.implode(', *.', $allowed));
            return false;
        }
        return true;
    }

    protected function save(waRequestFile $f)
    {
        if (file_exists($this->path.DIRECTORY_SEPARATOR.$f->name)) {
            $i = strrpos($f->name, '.');
            $name = substr($f->name, 0, $i);
            $ext = substr($f->name, $i + 1);
            $i = 1;
            while (file_exists($this->path.DIRECTORY_SEPARATOR.$name.'-'.$i.'.'.$ext)) {
                $i++;
            }
            $this->name = $name.'-'.$i.'.'.$ext;
            return $f->moveTo($this->path, $this->name);
        }
        return $f->moveTo($this->path, $f->name);
    }
}