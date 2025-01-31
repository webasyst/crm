<?php

class crmFileDownloadController extends crmJsonController
{
    public function execute()
    {
        $file = $this->getFile();

        wa()->getResponse()->addHeader("Cache-Control", "private, no-transform");

        $thumb = waRequest::get('thumb', 0, waRequest::TYPE_INT);
        if (in_array($file['ext'], ['jpg', 'jpeg', 'png', 'gif', 'pdf']) && $thumb > 10) {
            list($is_success, $path, $name) = $this->getThumb($file, $thumb);
            if ($is_success) {
                waFiles::readFile($path, $name);
            } else {
                $this->redirect(wa()->getAppStaticUrl('crm', true) . 'img/any-file.png');
            }
            return;
        }

        waFiles::readFile($file['path'], $file['name']);
    }

    /**
     * @throws waException
     * @throws waRightsException
     * @return array
     */
    protected function getFile()
    {
        $id = (int)$this->getRequest()->get('id');
        if ($id <= 0) {
            $this->notFound();
        }
        $file = $this->getFileModel()->getFile($id);
        if (!$file) {
            $this->notFound();
        }
        if (!$this->getCrmRights()->contactOrDeal($file['contact_id'])) {
            $this->accessDenied();
        }
        return $file;
    }

    protected function getThumb($file, $size)
    {
        if (!in_array($file['ext'], ['jpg', 'jpeg', 'png', 'gif', 'pdf'])) {
            return [ false, null, null ];
        }

        if ($file['ext'] == 'pdf') {
            return $this->getPdfThumb($file, $size);
        }

        $thumb_path = $file['path'].'-thumb-'.$size.'.'.$file['ext'];
        $thumb_name = $file['name'].'-thumb-'.$size.'.'.$file['ext'];
        if (file_exists($thumb_path)) {
            return [ true, $thumb_path, $thumb_name ];
        }

        //waFiles::copy($file['path'], $file['path'].'.'.$file['ext']);

        $img = waImage::factory($file['path']);
        if (method_exists($img, 'fixImageOrientation')) {
            $img->fixImageOrientation();
        }
        $crop_size = min($img->height, $img->width);
        $crop_x = (int) max(0, ($img->width - $crop_size)/2);
        $crop_y = (int) max(0, ($img->height - $crop_size)/2);
        $img->crop($crop_size, $crop_size, $crop_x, $crop_y)->resize($size, $size);
        $img->save($thumb_path);

        return [ true, $thumb_path, $thumb_name ];
    }

    protected function getPdfThumb($file, $size)
    {
        if (!class_exists('Imagick')) {
            return [ false, null, null ];
        }

        $thumb_path = $file['path'].'-thumb-'.$size.'.jpg';
        $thumb_name = $file['name'].'-thumb-'.$size.'.jpg';
        if (file_exists($thumb_path)) {
            return [ true, $thumb_path, $thumb_name ];
        }

        
        $pdf_path = $file['path'].'.pdf';
        $img_path = $file['path'].'.jpg';
        waFiles::copy($file['path'], $pdf_path);

        $imagick = new Imagick();
        $imagick->readImage($pdf_path.'[0]');
        $imagick->writeImage($img_path);

        $img = waImage::factory($img_path);
        $crop_size = min($img->height, $img->width);
        $crop_x = (int) max(0, ($img->width - $crop_size)/2);
        $crop_y = (int) max(0, ($img->height - $crop_size)/2);
        $img->crop($crop_size, $crop_size, $crop_x, $crop_y)->resize($size, $size);
        $img->save($thumb_path);

        return [ true, $thumb_path, $thumb_name ];
    }
}
