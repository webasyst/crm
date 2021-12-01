<?php
/**
 * This class represents a single image of a company invoice template.
 *
 * An image has two files: an original and a resized one.
 *
 * Class has methods to upload and remove these images.
 *
 */

class crmCompanyImageHandler extends crmJsonController
{
    /**
     * @int crm_company.id
     */
    public $company_id;

    /**
     * @string type of file to delete
     */
    public $type;

    /**
     * @string crm_template_params.code
     */
    public $code;

    /**
     * @int crm_company.template_id
     */
    public $template_id;

    /**
     * @string file extension
     */
    public $ext;

    /**
     * @string folder name with slash
     */
    public $folder;

    /**
     * @array
     */
    public $paths;

    /**
     * crmDeleteImage constructor.
     * @param array $options
     * @throws waException
     * @throws waRightsException
     */
    public function __construct($options = array())
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $options += array(
            'company_id'  => null,
            'type'        => null,
            'code'        => null,
            'template_id' => null,
            'ext'         => null,
        );

        $this->company_id = $options['company_id'];
        $this->type = $options['type'];
        $this->code = $options['code'];
        $this->template_id = $options['template_id'];
        $this->ext = $options['ext'];

    }

    /**
     * Delete picture from folder.
     * Deleting a logo:
     * It is required to transfer the company_id, type, and extension to the constructor.
     * Delete a picture from the company:
     * It is required in the constructor to transfer company_id, type, template_id and extension.
     * @return bool
     * @throws waException
     */
    public function deleteImage()
    {
        if (empty($this->company_id) || empty($this->type) || empty($this->ext)) {
            throw new waException(_w('Unknown company id, file extension or type.'));
        }

        if ($this->type == 'logo') {
            $this->folder = 'logos/';
            $this->paths = array(
                'orig_file' => $this->company_id.'.original.'.$this->ext,
                'logo_file' => $this->company_id.'.'.$this->ext,
            );
        } elseif ($this->type == 'param_image' && $this->template_id) {
            $this->folder = 'company_images/';
            $this->paths = array(
                'orig_file' => $this->company_id.'.original.'.$this->code.'.'.$this->template_id.'.'.$this->ext,
                'logo_file' => $this->company_id.'.'.$this->code.'.'.$this->template_id.'.'.$this->ext,
            );
        } else {
            return false;
        }

        foreach ($this->paths as $path) {
            $file = wa()->getDataPath($this->folder.$path, true, 'crm');
            if (file_exists($file)) {
                waFiles::delete($file);
            }
        }

        return true;
    }

    /**
     * Before saving, it will delete old files with that name
     * Saves as a logo, even if resize is not required.
     *
     * Dimensions for Resize:
     * max_width = 400;
     * max_height = 200;
     *
     * @param $file
     * @return bool
     */
    public function saveImage($file)
    {
        $max_width = 400;
        $max_height = 200;

        if ($this->deleteImage()) {
            $logo_file = wa()->getDataPath($this->folder.$this->paths['logo_file'], true, 'crm');
            $orig_file = wa()->getDataPath($this->folder.$this->paths['orig_file'], true, 'crm');
            try {
                $img = $file->waImage()->save($orig_file);
            } catch (Exception $e) {
                $this->errors = 'Unable to save new file '.$this->paths['orig_file'].' ('.pathinfo($this->paths['orig_file'], PATHINFO_EXTENSION).') as jpeg: '.$e->getMessage();
                return false;
            }
            unset($img);
        } else {
            return false;
        }

        // Resize and save selected area
        try {
            $img = waImage::factory($orig_file);
            $w = $img->width;
            $h = $img->height;

            if ($w > $max_width || $h > $max_height) {
                if (($w / $h) > ($max_width / $max_height)) {
                    $img->resize($max_width, null, waImage::WIDTH)->save($logo_file);
                } else {
                    $img->resize(null, $max_height, waImage::HEIGHT)->save($logo_file);
                }
            } else {
                $file->waImage()->save($logo_file);
            }

        } catch (Exception $e) {
            $this->errors = 'Unable to resize an image: '.$e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Delete all company images
     * @param $company_id int crm_company.id
     */
    public static function deleteCompanyImages($company_id)
    {
        if (waConfig::get('is_template')) {
            return;
        }

        $dir = wa()->getDataPath('company_images/', true, 'crm');

        foreach (waFiles::listdir($dir) as $file) {
            $parse_name = self::parseImageFilename($file);
            if ($parse_name['company_id'] == $company_id) {
                waFiles::delete(wa()->getDataPath('company_images/'.$file, true, 'crm'));
            }
        }
    }

    /**
     * Delete all images attached to the template
     * @param $template_id int crm_template.id
     */
    public static function deleteTemplateImages($template_id)
    {
        if (waConfig::get('is_template')) {
            return;
        }

        $dir = wa()->getDataPath('company_images/', true, 'crm');

        foreach (waFiles::listdir($dir) as $file) {
            $parse_name = self::parseImageFilename($file);
            if ($parse_name['template_id'] == $template_id) {
                waFiles::delete(wa()->getDataPath('company_images/'.$file, true, 'crm'));
            }
        }
    }

    /**
     * When deleting a template variable, delete all images
     * @param $template_id int crm_template.id
     * @param $code string crm_template.code
     */
    public static function deleteTemplatesImage($template_id, $code)
    {
        if (waConfig::get('is_template')) {
            return;
        }

        $dir = wa()->getDataPath('company_images/', true, 'crm');

        foreach (waFiles::listdir($dir) as $file) {
            $parse_name = self::parseImageFilename($file);
            if ($parse_name['template_id'] == $template_id && $parse_name['code'] == $code) {
                waFiles::delete(wa()->getDataPath('company_images/'.$file, true, 'crm'));
            }
        }
    }

    /**
     * Split the file name into parts
     * @param $filename string @example '1.original.1.jpg'
     * @return array|bool
     */
    protected static function parseImageFilename($filename)
    {
        $parts = explode('.', $filename);
        if (!isset($parts[1])) {
            return false;
        }

        $result = array(
            'company_id'  => $parts[0],
            'is_original' => $parts[1] === 'original',
        );

        array_shift($parts);
        if ($result['is_original']) {
            array_shift($parts);
        }

        if (!isset($parts[2])) {
            return false;
        }

        $result += array(
            'code'        => $parts[0],
            'template_id' => $parts[1],
            'extension'   => $parts[2],
        );

        return $result;
    }

}
