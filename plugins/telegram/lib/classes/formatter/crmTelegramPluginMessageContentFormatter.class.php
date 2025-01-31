<?php

abstract class crmTelegramPluginMessageContentFormatter
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

    protected function prepareSticker($width = 16)
    {
        $sticker_id = $this->message['params']['sticker_id'];
        $tsm = new crmTelegramPluginStickerModel();
        $sticker_file = $tsm->getById($sticker_id);
        if (!$sticker_file) {
            return array(
                'error' => _wd('crm_telegram', 'Unknown sticker'),
            );
        }

        return array(
            'url'   => wa()->getAppUrl('crm').'?module=file&action=download&id='.(int)$sticker_file['id'],
            'width' => $width,
        );
    }
}
