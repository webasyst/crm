<?php

class crmCurrencyModel extends crmModel
{
    protected $table = 'crm_currency';
    protected $id = 'code';

    public function getAll($key = null, $normalize = false)
    {
        $currencies = $this->select('*')->order('sort')->fetchAll($key, $normalize);
        foreach($currencies as $code => $c) {
            $info = waCurrency::getInfo($code);
            $currencies[$code]['sign'] = ifempty($info['sign'], $code);
            $currencies[$code]['title'] = ifempty($info['title'], $code);
            $currencies[$code]['precision'] = ifempty($info['precision'], 2);
        }
        return $currencies;
    }

    public function get($code)
    {
        $result = $this->getById($code);
        if ($result) {
            $info = waCurrency::getInfo($code);
            $result['sign'] = ifempty($info['sign'], $code);
            $result['title'] = ifempty($info['title'], $code);
            $result['precision'] = ifempty($info['precision'], 2);
        }
        return $result;
    }
}
