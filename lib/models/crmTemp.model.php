<?php

class crmTempModel extends crmModel
{
    protected $table = 'crm_temp';
    protected $expired_offset = '-3 day';

    public function getByHash($hash, $to_unserialize = true)
    {
        $data = $this->getByField('hash', $hash);
        if (!$data) {
            return false;
        }

        if ($to_unserialize) {
            $data['data'] = unserialize($data['data']);
        }

        return $data;
    }

    public function save($hash, $data)
    {
        $this->prepareBeforeSave($hash, $data);
        return $this->insert(array(
            'hash' => $hash,
            'data' => serialize($data),
            'create_datetime' => date('Y-m-d H:i:s')
        ), 2);
    }

    protected function prepareBeforeSave($hash, &$data)
    {
        $folder = "tmp/{$hash}/files/";
        foreach ($data as $key => &$value) {
            if (($value instanceof waRequestFile) && $value->error == UPLOAD_ERR_OK) {
                $file_path = wa()->getDataPath($folder, false, 'crm', true) . "/{$key}";
                $file = crmRequestFile::convertFrom($value, $file_path);
                $value = $file;
            } elseif (is_array($value)) {
                $this->prepareBeforeSave($hash, $value);
            }
        }
        unset($value);
    }

    public function deleteByHash($hash)
    {
        $this->deleteByField('hash', $hash);
    }

    /**
     * Delete expired data
     */
    public function clean()
    {
        $expired = date('Y-m-d H:i:s', strtotime($this->expired_offset));
        $where = "`create_datetime` < '{$expired}'";
        $hashes = $this->select('hash')->where($where)->fetchAll(null, true);
        $this->deleteByField('hash', $hashes);

        foreach ($hashes as $hash) {
            $path = wa()->getDataPath("tmp/{$hash}", false, 'crm', false);
            try {
                waFiles::delete($path);
            } catch (waException $e) {

            }
        }
    }
}
