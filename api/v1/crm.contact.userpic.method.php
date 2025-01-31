<?php

class crmContactUserpicMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $contact_id = (int) $this->get('id', true);
        $userpic_size = (int) abs($this->get('userpic_size'));
        $picture = base64_decode(ifset($_json, 'photo', null));
        $content_type = trim(ifset($_json, 'content_type', ''));
        $crop_x = (int) abs(ifset($_json, 'crop', 'x', 0));
        $crop_y = (int) abs(ifset($_json, 'crop', 'y', 0));
        $crop_size = (int) abs(ifset($_json, 'crop', 'size', 0));

        if (empty($picture)) {
            throw new waAPIException('empty_file', sprintf_wp('Missing required parameter: “%s”.', 'photo'), 400);
        } elseif (empty($content_type)) {
            throw new waAPIException('empty_type', sprintf_wp('Missing required parameter: “%s”.', 'content_type'), 400);
        } elseif (!in_array($content_type, ['image/jpeg', 'image/png'])) {
            throw new waAPIException('invalid_type', _w('File’s MIME type not supported.'), 400);
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

        $pic_ext = (explode('/', $content_type)[1] == 'png' ? 'png' : 'jpg');
        $original_file_name = "$rand.original.$pic_ext";
        if (!file_put_contents($path.$original_file_name, $picture)) {
            throw new waAPIException('server_error', _w('File could not be saved.'), 500);
        }

        try {
            if ($pic_ext === 'png') {
                $pic_ext = 'jpg';
                waImage::factory($path.$original_file_name)->save($path."$rand.original.$pic_ext");
                unlink($path.$original_file_name);
                $original_file_name = "$rand.original.$pic_ext";
            }
            $cropped_file_name = "$rand.$pic_ext";
            $img = waImage::factory($path.$original_file_name);

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
        }

        $contact = new crmContact($contact_id);
        $contact['photo'] = $rand;
        $contact->save();

        $this->response = [
            'thumb'         => wa()->getDataUrl($dir.$thumb_file_name, true, 'contacts', true),
            'original'      => wa()->getDataUrl($dir.$original_file_name, true, 'contacts', true),
            'original_crop' => wa()->getDataUrl($dir.$cropped_file_name, true, 'contacts', true)
        ];
    }
}
