<?php

class crmCurrency
{
    protected $app_settings_model;

    public function change($code)
    {
        $asm = $this->getAppSettingsModel();
        $old_currency = $asm->get('crm', 'currency');

        if ($old_currency != $code) {
            $asm->set('crm', 'currency', $code);

            $cm = new crmCurrencyModel();
            $currencies = $cm->getAll('code');

            if (!empty($currencies[$code])) {
                $dm = new crmDealModel();
                $im = new crmInvoiceModel();

                $cm->exec("UPDATE {$cm->getTableName()} SET rate = rate / '".$cm->escape($currencies[$code]['rate'])
                    ."' WHERE code <> '".$cm->escape($code)."'");
                $cm->updateById($code, array('rate' => 1));
                $dm->exec("UPDATE {$dm->getTableName()} SET currency_rate = currency_rate / '".$dm->escape($currencies[$code]['rate'])
                    ."' WHERE currency_id <> '".$dm->escape($code)."'");
                $dm->updateByField('currency_id', $code, array('currency_rate' => 1));
                $im->exec("UPDATE {$im->getTableName()} SET currency_rate = currency_rate / '".$im->escape($currencies[$code]['rate'])
                    ."' WHERE currency_id <> '".$im->escape($code)."'");
                $im->updateByField('currency_id', $code, array('currency_rate' => 1));
            }
        }
    }

    public function copy($rate = null)
    {
        $asm = new waAppSettingsModel();
        if (! $asm->get('crm', 'use_shop_currencies')) {
            return null;
        }
        $asm = $this->getAppSettingsModel();
        $cm = new crmCurrencyModel();
        $crm_currencies = $cm->getAll('code');
        $crm_currency_code = $asm->get('crm', 'currency');
        wa('shop');
        $scm = new shopCurrencyModel();
        $shop_currencies = $scm->getAll('code');
        $shop_currency_code = $asm->get('shop', 'currency');

        $diff = $crm_currency_code != $shop_currency_code || count($crm_currencies) != count($shop_currencies);
        if (!$diff) {
            foreach ($shop_currencies as $sc) {
                if (empty($crm_currencies[$sc['code']]) || round($sc['rate'], 4) != round($crm_currencies[$sc['code']]['rate'], 4)) {
                    $diff = true;
                    break;
                }
            }
        }
        if (!$diff) {
            return null;
        }
        if ($crm_currencies) {
            if (!isset($crm_currencies[$shop_currency_code])) {
                if (isset($shop_currencies[$crm_currency_code])) {
                    $ins = $shop_currencies[$shop_currency_code];
                    $ins['rate'] = $shop_currencies[$shop_currency_code]['rate'] / $shop_currencies[$crm_currency_code]['rate'];
                    $cm->insert($ins);
                } else if (!isset($shop_currencies[$crm_currency_code])) {
                    $rate = floatval(preg_replace('/\s*[,\.]\s*/', '.', $rate));
                    if (!$rate) {
                        return null;
                    }
                    $ins = array(
                        'code' => $shop_currency_code,
                        'rate' => 1 / $rate,
                    );
                    $cm->insert($ins);
                }
            }
        }

        if ($crm_currency_code && $shop_currency_code != $crm_currency_code) {
            $this->change($shop_currency_code);
        }

        $cm->exec("TRUNCATE crm_currency");
        $sql = "INSERT INTO {$cm->getTableName()} (code, rate, sort)
                  SELECT code, rate, sort FROM shop_currency";
        $cm->exec($sql);

        $asm->set('crm', 'currency', $shop_currency_code);

        return count($shop_currencies);
    }

    protected function getAppSettingsModel()
    {
        $this->app_settings_model = $this->app_settings_model ? $this->app_settings_model : new waAppSettingsModel();
        return $this->app_settings_model;
    }
}
