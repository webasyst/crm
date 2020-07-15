<?php

class crmSettingsCurrenciesPrimaryAction extends waViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $m = new crmCurrencyModel();
        $this->view->assign(array(
            'currencies' => $m->getAll('code'),
            'currency'   => waCurrency::getInfo($this->getConfig()->getCurrency()),
        ));
    }
}

