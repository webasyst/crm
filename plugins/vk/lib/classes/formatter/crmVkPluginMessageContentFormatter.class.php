<?php

abstract class crmVkPluginMessageContentFormatter
{
    protected $message;
    public function __construct($message)
    {
        $this->message = $message;
    }

    public function execute()
    {
        return $this->renderTemplate($this->getTemplate(), $this->getAssigns());
    }

    /**
     * @return array
     */
    abstract protected function getAssigns();

    /**
     * @return string
     */
    abstract protected function getTemplate();

    protected function renderTemplate($template, $assign = array())
    {
        $view = wa()->getView();
        $old_vars = $view->getVars();
        $view->clearAllAssign();
        $view->assign($assign);
        $html = $view->fetch($template);
        $view->clearAllAssign();
        $view->assign($old_vars);
        return $html;
    }

    protected function prepareSticker($width = 64)
    {
        if (empty($this->message['params']['sticker'])) {
            return null;
        }
        $sticker = $this->message['params']['sticker'];

        $rate = ifset($sticker['width']) ? $sticker['height'] / $sticker['width'] : 1;
        $height = $rate * $width;
        if ($width <= 64) {
            $photo_url = $sticker['photo_64'];
        } elseif ($width <= 128) {
            $photo_url = $sticker['photo_128'];
        } elseif ($width <= 256) {
            $photo_url = $sticker['photo_256'];
        } else {
            $photo_url = $sticker['photo_352'];
        }
        
        return array(
            'photo_url' => $photo_url,
            'width' => $this->formatNumber($width),
            'height' => $this->formatNumber($height)
        );
    }

    protected function choosePhotoUrlByWidth($width, $photo_urls)
    {
        if (!is_array($photo_urls)) {
            $photo_urls = array();
        }

        $_photo_urls = array();
        foreach ($photo_urls as $key => $value) {
            $_key = $key;
            if (substr($key, 0, 6) == 'photo_') {
                $_key = substr($key, 6);
            }
            if (wa_is_int($_key) && $this->looksLikeUrl($value)) {
                $_photo_urls[$_key] = $value;
            }
        }

        $photo_url = null;
        $photo_urls = $_photo_urls;

        $photo_widths = array_keys($photo_urls);
        sort($photo_widths, SORT_NUMERIC);

        foreach ($photo_widths as $photo_width) {
            if ($width <= $photo_width) {
                $photo_url = $photo_urls[$photo_width];
                break;
            }
        }

        if (!$photo_url) {
            $photo_url = array_pop($photo_urls);
        }

        return $photo_url;

    }

    protected function looksLikeUrl($value)
    {
        return substr($value, 0, 7) == 'http://' || substr($value, 0, 8) == 'https://';
    }

    protected function formatNumber($number)
    {
        return is_int($number) ? $number : number_format($number, 2, '.', '');
    }
}
