<?php

class crmContactUserpicMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;
    const USERPIC_EXT = 'jpg';

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $contact_id = (int) $this->get('id', true);
        $userpic_size = (int) abs($this->get('userpic_size'));
        $picture = base64_decode(ifset($_json, 'photo', null));
        $crop_x = (int) abs(ifset($_json, 'crop', 'x', 0));
        $crop_y = (int) abs(ifset($_json, 'crop', 'y', 0));
        $crop_size = (int) abs(ifset($_json, 'crop', 'size', 0));

        if (empty($picture)) {
            throw new waAPIException('empty_file', sprintf_wp('Missing required parameter: “%s”.', 'photo'), 400);
        } elseif ($contact_id < 1 || !$this->getContactModel()->getById($contact_id)) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        } elseif (!$this->getCrmRights()->contactEditable($contact_id)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $rand = mt_rand();
        $dir  = waContact::getPhotoDir($contact_id);
        $path = wa()->getDataPath($dir, true, 'contacts');
        if (file_exists($path)) {
            waFiles::delete($path);
        }
        waFiles::create($path);

        $original_file_name = "$rand.original";
        if (!file_put_contents($path.$original_file_name, $picture)) {
            throw new waAPIException('server_error', _w('File could not be saved.'), 500);
        }

        try {
            $img = waImage::factory($path.$original_file_name);
            $pic_ext = self::USERPIC_EXT;
            $jpg_original_file_name = "$rand.original.$pic_ext";
            $img->save($path.$jpg_original_file_name);
            $cropped_file_name = "$rand.$pic_ext";

            if (empty($crop_size) || $crop_size >= max($img->height, $img->width)) {
                $crop_size = min($img->height, $img->width);
            }
            if ($crop_x >= $img->width) {
                $crop_x = (int) max(0, ($img->width - $crop_size)/2);
            }
            if ($crop_y >= $img->height) {
                $crop_y = (int) max(0, ($img->height - $crop_size)/2);
            }
            if (empty($userpic_size) || $userpic_size >= $crop_size) {
                $userpic_size = self::USERPIC_SIZE;
            }

            $thumb_file_name = "$rand.{$userpic_size}x$userpic_size.$pic_ext";
            if (method_exists($img, 'fixImageOrientation')) {
                $img->fixImageOrientation();
            }
            $img->crop($crop_size, $crop_size, $crop_x, $crop_y);
            $img->save($path.$cropped_file_name);
        } catch (Exception $ex) {
            throw new waAPIException('server_error', sprintf_wp('Unable to crop image: %s', $ex->getMessage()), 500);
        } finally {
            unlink($path.$original_file_name);
        }

        $contact = new crmContact($contact_id);
        $contact['photo'] = $rand;
        $contact->save();

        $this->response = [
            'thumb'         => wa()->getDataUrl($dir.$thumb_file_name, true, 'contacts', true),
            'original'      => wa()->getDataUrl($dir.$jpg_original_file_name, true, 'contacts', true),
            'original_crop' => wa()->getDataUrl($dir.$cropped_file_name, true, 'contacts', true)
        ];
    }
}
