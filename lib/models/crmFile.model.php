<?php

class crmFileModel extends crmModel
{
    const SOURCE_TYPE_MESSAGE = 'MESSAGE';
    const SOURCE_TYPE_NOTE = 'NOTE';
    const SOURCE_TYPE_FILE = 'FILE';
    
    protected $table = 'crm_file';

    protected $link_contact_field = array('contact_id', 'creator_contact_id');

    protected $unset_contact_links_behavior = array('contact_id' => 'delete');

    /**
     * @param $id
     * @return array|null
     *   Has 'path' key, where physical file is located
     */
    public function getFile($id)
    {
        $files = $this->getFiles(array($id));
        if (!isset($files[$id])) {
            return null;
        }
        return $files[$id];
    }

    /**
     * @param array[]int $ids
     * @return array[]array
     *   Each item, has 'path' key, where physical file is localed
     */
    public function getFiles($ids)
    {
        $ids = crmHelper::toIntArray($ids);
        return $this->getFilesByField(array('id' => $ids));
    }

    /**
     * @param array $field field-value map
     * @return array[]array
     *   Each item, has 'path' key, where physical file is localed
     */
    public function getFilesByField($field)
    {
        $files = $this->getByField($field, 'id');
        if (!$files) {
            return array();
        }
        $paths = $this->getFilePaths($files);
        foreach ($paths as $file_id => $path) {
            if (isset($files[$file_id])) {
                $files[$file_id]['path'] = $path;
            }
        }
        return $files;
    }

    /**
     * @param $data
     * @param null|waRequestFile|string(file_path or url)|stream $file file to upload
     * @return bool|int
     */
    public function add($data, $file = null)
    {
        $data = array_merge($this->prepareDataByFile($file), $data);

        $data['create_datetime'] = date('Y-m-d H:i:s');
        if (!array_key_exists('creator_contact_id', $data)) {
            $data['creator_contact_id'] = (int) wa()->getUser()->getId();
        }
        if (!array_key_exists('contact_id', $data)) {
            $data['contact_id'] = $data['creator_contact_id'];
        }
        $data['contact_id'] = (int) ifset($data['contact_id']);
        $data['name'] = (string)ifset($data['name']);
        $data['size'] = (int)ifset($data['size']);
        $data['ext'] = strtolower((string)ifset($data['ext']));

        $id = $this->insert($data);

        $dir_to_upload = crmHelper::getFileFolderPath($id, true);
        $path_to_upload = $dir_to_upload . $id;
        $uploaded_size = null;
        if (!$this->uploadFile($file, $path_to_upload, $uploaded_size)) {
            $this->deleteById($id);
            return false;
        }

        if ($uploaded_size !== null && $uploaded_size > 0 && $data['size'] != $uploaded_size) {
            $this->updateById($id, array(
                'size' => $uploaded_size
            ));
        }

        return $id;
    }

    protected function prepareDataByFile($file)
    {
        if ($file instanceof waRequestFile) {
            return array(
                'name' => $file->name,
                'size' => $file->size,
                'ext' => strtolower($file->extension)
            );
        } elseif (is_resource($file) && get_resource_type($file) === 'stream') {
            $stat = fstat($file);
            if (!$stat) {
                return array();
            }
            $stat = array_slice($stat, 13);
            return array(
                'size' => (int)ifset($stat['size'])
            );
        } elseif (is_scalar($file)) {
            $file = (string)$file;
            $path_info = pathinfo($file);
            return array(
                'name' => (string)ifset($path_info['basename']),
                'size' => (int)@filesize($file),
                'ext' => (string)ifset($path_info['extension']),
            );
        }
        return array();
    }


    protected function uploadFile($file, $path_to_upload, &$uploaded_size)
    {
        if ($file instanceof waRequestFile) {
            $uploaded_size = null;
            return $file->moveTo($path_to_upload);
        } elseif (is_resource($file) && get_resource_type($file) === 'stream') {
            return $this->uploadFileByStream($file, $path_to_upload, $uploaded_size);
        } elseif (is_scalar($file)) {
            $stream = @fopen($file, 'rb');
            return $this->uploadFileByStream($stream, $path_to_upload, $uploaded_size);
        }
        return true;
    }

    protected function uploadFileByStream($stream, $path_to_upload, &$uploaded_size)
    {
        $dst_fh = @fopen($path_to_upload, 'wb');
        if (!$dst_fh) {
            sleep(1);
            $dst_fh = @fopen($path_to_upload, 'wb');
            if (!$dst_fh) {
                return false;
            }
        }

        $stat = fstat($stream);
        if ($stat) {
            $stat = array_slice($stat, 13);
        }

        $uploaded_size = (int)stream_copy_to_stream($stream, $dst_fh);
        if ($stat && ifset($stat['size']) > 0) {
            return $stat['size'] == $uploaded_size;
        } else {
            return $uploaded_size > 0;
        }
    }



    /**
     * @param int|int[] $id
     */
    public function delete($id)
    {
        $ids = crmHelper::toIntArray($id);
        $ids = crmHelper::dropNotPositive($ids);
        if (!$ids) {
            return;
        }
        $files = $this->getById($ids);
        $this->deleteById(array_keys($files));
        $this->deleteFilePaths($files);
    }

    /**
     * @param int|array[]int $contact_id
     */
    public function deleteByContact($contact_id)
    {
        $contact_ids = crmHelper::toIntArray($contact_id);
        if (!$contact_ids) {
            return;
        }
        $files = $this->getByField('contact_id', $contact_ids, 'id');
        $this->deleteById(array_keys($files));
        $this->deleteFilePaths($files);
    }

    public function unsetContactLinks($contact_id)
    {
        $contact_ids = crmHelper::toIntArray($contact_id);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        $this->deleteByContact($contact_ids);
    }

    /**
     * Path to dir or to original file
     * @param $files
     * @return array
     */
    public function getFilePaths($files)
    {
        if (!$files) {
            return array();
        }

        $file = reset($files);

        $item_type = '';
        if (wa_is_int($file)) {
            $item_type = 'int';
        } else if (is_array($file) && isset($file['id'])) {
            $item_type = 'record';
        }

        if (!$item_type) {
            return array();
        }

        if ($item_type === 'int') {
            $file_ids = crmHelper::toIntArray($files);
            $file_ids = crmHelper::dropNotPositive($file_ids);
            if (!$file_ids) {
                return array();
            }
            $files = $this->getById($file_ids);
        }

        $paths = array();
        foreach ($files as $file) {
            $paths[$file['id']] = crmHelper::getFileFolderPath($file['id']) . $file['id'];
        }
        return $paths;
    }

    public function deleteFilePaths($files)
    {
        $paths = $this->getFilePaths($files, true);
        foreach ($paths as $path) {
            try {
                waFiles::delete($path);
            } catch (Exception $e) {

            }
        }
    }

    public function getFilePath($file)
    {
        $paths = $this->getFilePaths(array($file));
        $file_id = is_array($file) ? (int) ifset($file['id']) : (int) $file;
        return ifset($paths[$file_id]);
    }
}
