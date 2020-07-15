<?php

class crmFileUploadController extends crmJsonController
{
    public function execute()
    {
        $deal_id = waRequest::post('deal_id', null, waRequest::TYPE_INT);
        $contact_id = waRequest::post('contact_id', null, waRequest::TYPE_INT);
        $contact_id = $deal_id ? $deal_id * -1 : ($contact_id ? $contact_id : 0);

        if (!$contact_id) {
            return;
        }
        if (!$this->getCrmRights()->contactOrDeal($contact_id)) {
            throw new waRightsException();
        }

        foreach (waRequest::file('files') as $f) {
            if ($f->uploaded()) {

                $file_id = self::saveFile($f, $contact_id);

                if ($file_id) {
                    $this->logAction('file_add', array('file_id' => $file_id));
                    $lm = new crmLogModel();
                    $lm->log(
                        'file_add',
                        $contact_id,
                        $file_id,
                        null,
                        $f->name
                    );
                } else {
                    $this->errors[] = sprintf(_w('Failed to upload file %s.'), $f->name);
                }
            } else {
                $this->errors[] = sprintf(_w('Failed to upload file %s.'), $f->name).' ('.$f->error.')';
            }
        }
    }

    protected static function saveFile(waRequestFile $f, $contact_id)
    {
        static $file_model = null;
        if (!$file_model) {
            $file_model = new crmFileModel();
        }
        return $file_model->add(array('contact_id' => $contact_id), $f);
    }
}
