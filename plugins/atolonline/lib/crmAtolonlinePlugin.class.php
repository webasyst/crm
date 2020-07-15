<?php

class crmAtolonlinePlugin extends waPlugin
{
    private static $config;
    private static $token;
    private static $error;
    private $company_id;

    public function getSettings($name = null)
    {
        if ($this->settings) {
            if ($name) {
                return ifset($this->settings[$name]);
            }
            return $this->settings;
        }
        $app_settings_model = new waAppSettingsModel();
        $this->settings = $app_settings_model->get('crm.atolonline');
        if (!empty($this->settings['crm_company'])) {
            $this->settings['crm_company'] = json_decode($this->settings['crm_company'], true);
        }
        if ($name) {
            return ifset($this->settings[$name]);
        }
        return $this->settings;
    }

    public function saveSettings($settings = array())
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $settings = $this->validateSettings($settings);

        $company_id = $settings['crm_company_id'];

        $app_settings_model = new waAppSettingsModel();

        $crm_company = json_decode($app_settings_model->get('crm.atolonline', 'crm_company'), true);

        if (!empty($settings['crm_company_on'])) {
            $crm_company[$company_id] = $company_id;
            $app_settings_model->set('crm.atolonline', 'crm_company', json_encode($crm_company));

            unset($settings['crm_company_on']);
            unset($settings['crm_company_id']);
            foreach ($settings as $k => $v) {
                $app_settings_model->set('crm.atolonline', $company_id.':'.$k, is_array($v) ? json_encode($v) : $v);
            }
        } else {
            unset($crm_company[$company_id]);
            $app_settings_model->set('crm.atolonline', 'crm_company', json_encode($crm_company));

            $app_settings_model->exec("DELETE FROM {$app_settings_model->getTableName()} WHERE app_id='crm.atolonline' AND name LIKE '".intval($company_id).":%'");
        }
    }

    public function validateSettings($new_settings)
    {
        if (empty($new_settings['crm_company_id'])) {
            throw new waException('Для сохранения настроек нужна хотя бы одна компания в настройках CRM. Добавьте компанию в разделе «Настройки → Компании».');
        }
        if (empty($new_settings['crm_company_on'])) {
            return $new_settings;
        }
        $required = array('login', 'pass', 'inn', 'payment_address', 'group_code', 'external_id_prefix', 'sno', 'email');
        foreach ($required as $field) {
            if (empty($new_settings[$field])) {
                throw new waException('Все поля формы обязательны');
            }
        }
        if (mb_strlen($new_settings['email']) > 64) {
            throw new waException('Длина поля email не должна превышать 64 символа');
        }
        $new_settings['debug_mode'] = ifempty($new_settings['debug_mode'], 'off');

        if (!empty($new_settings['login']) && !empty($new_settings['pass']) && $new_settings['debug_mode'] != 'on') {
            $res = crmAtolonlinePluginReceipt::send('getToken', array(
                'login' => $new_settings['login'],
                'pass' => $new_settings['pass'],
                'company_id' => $new_settings['crm_company_id'],
                'api_version' => ifset($new_settings['api_version']),
                'debug_mode' => $new_settings['debug_mode'],
            ));
            if ($res['status'] != 'ok' || empty($res['data']['token'])) {
                if (!empty($res['data']['error']) && !empty($res['data']['error']['text'])) {
                    $error = $res['data']['error']['text'];
                } elseif (!empty($res['data']['text'])) {
                    $error = $res['data']['text'];
                } else {
                    $error = 'Подключение не удалось';
                }
                throw new waException($error);
            }
        }
        return $new_settings;
    }

    public function init($params)
    {
        $this->getSettings();
        $this->company_id = $params['invoice']['company_id'];
    }

    public function invoicePaid($params)
    {
        $this->init($params);

        $available_taxes = crmAtolonlinePlugin::getAvailableTaxes();
        if (
            empty($params['invoice']) ||
            empty($this->settings['crm_company'][$params['invoice']['company_id']]) ||
            $params['invoice']['currency_id'] != 'RUB' ||
            !in_array(floatval($params['invoice']['tax_percent']), $available_taxes) ||
            !$this->isPluginAvailable()
        ) {
            return;
        }
        crmAtolonlinePluginReceipt::sell($params);
    }

    public function invoiceRefund($params)
    {
        $this->init($params);

        $available_taxes = crmAtolonlinePlugin::getAvailableTaxes();

        $arm = new crmAtolonlineReceiptModel();

        if (
            empty($params['invoice']) ||
            empty($this->settings['crm_company'][$params['invoice']['company_id']]) ||
            $params['invoice']['currency_id'] != 'RUB' ||
            !in_array(floatval($params['invoice']['tax_percent']), $available_taxes) ||
            !$this->isPluginAvailable($this->settings)
        ) {
            return;
        }
        $receipts = array();

        if (!array_key_exists('data', $params)) {

            $receipts = $arm->select('*')->where(
                "invoice_id = ".(int)$params['invoice']['id']." AND operation = 'sell' AND status = 'done' AND refund_id IS NULL"
            )->fetchAll();
        } elseif (is_array($params['data']['receipt_id'])) {
            foreach ($params['data']['receipt_id'] as $id) {
                $receipt = $arm->getById($id);
                if (
                    $receipt &&
                    $receipt['invoice_id'] == $params['invoice']['id'] &&
                    $receipt['operation'] == 'sell' &&
                    $receipt['status'] == 'done' &&
                    !$receipt['refund_id']
                ) {
                    $receipts[] = $receipt;
                }
            }
        }
        if ($receipts) {
            foreach ($receipts as $receipt) {
                $params['receipt'] = $receipt;
                crmAtolonlinePluginReceipt::sell($params, 'sell_refund');
            }
        }
    }

    // backend_invoice handler
    public function backendInvoice($params)
    {
        $this->init($params);

        if (!$this->isPluginAvailable()) {
            return null;
        }
        $info_section = $this->backendInvoiceInfo($params);
        if ($info_section === null) {
            return null;
        }

        return array(
            'info_section'  => $info_section,
            'action_button' => $this->backendInvoiceActions($params),
        );
    }

    public function backendInvoiceInfo($params)
    {
        if (empty($params['invoice'])) {
            return null;
        }
        $rm = new crmAtolonlineReceiptModel();
        $receipts = $rm->select('*')->where('invoice_id = '.intval($params['invoice']['id']))->order('id ASC')->fetchAll('id');

        foreach ($receipts as &$r) {
            $r['html'] = $this->getReceiptHtml($r);
        }
        unset($r);

        $settings = $this->getSettings();
        $invalid_tax_percent = $invalid_tax_type = false;
        if ($params['invoice']['items'] && !empty($settings['crm_company'][$params['invoice']['company_id']])) {
            foreach ($params['invoice']['items'] as $i) {
                if (!in_array(floatval($i['tax_percent']), crmAtolonlinePlugin::getAvailableTaxes())) {
                    $invalid_tax_percent = true;
                }
                if ($i['tax_type'] == 'APPEND') {
                    $invalid_tax_type = true;
                }
            }
        }
        $view = wa()->getView();
        $view->assign(array(
            'receipts'            => $receipts,
            'invalid_tax_percent' => $invalid_tax_percent,
            'invalid_tax_type'    => $invalid_tax_type,
        ));
        return $view->fetch(wa()->getAppPath('plugins/atolonline/templates/InvoiceInfo.html', 'crm'));
    }

    public function backendInvoiceRefund($params)
    {
        $this->init($params);

        if (!$this->isPluginAvailable()) {
            return null;
        }
        if (empty($params['invoice'])) {
            return null;
        }
        $rm = new crmAtolonlineReceiptModel();
        $receipts = $rm->select('*')->where(
            'invoice_id = '.intval($params['invoice']['id'])." AND operation = 'sell' AND refund_id IS NULL AND status = 'done'"
        )->order('id DESC')->fetchAll('id');

        foreach ($receipts as &$r) {
            $r['html'] = $this->getReceiptHtml($r);
        }
        unset($r);

        $view = wa()->getView();
        $view->assign(array(
            'receipts' => $receipts,
        ));
        return array('receipts_block' => $view->fetch(wa()->getAppPath('plugins/atolonline/templates/InvoiceRefund.html', 'crm')));
    }

    public function backendAssetsHandler()
    {
        $sources = array();
        $sources[] = '<link rel="stylesheet" href="'.wa()->getAppStaticUrl('crm', true).'plugins/atolonline/css/atolonline.css">';
        $sources[] = '<script src="'.wa()->getAppStaticUrl('crm', true).'plugins/atolonline/js/atolonline.js"></script>';

        return join("", $sources);
    }

    public function backendInvoiceActions($params)
    {
        $settings = $this->getSettings();

        if (
            empty($params['invoice']) || $params['invoice']['currency_id'] != 'RUB' ||
            ($params['invoice']['state_id'] != 'PAID' && $params['invoice']['state_id'] != 'PROCESSING') ||
            empty($settings['crm_company'][$params['invoice']['company_id']])
        ) {
            return null;
        }

        if ($params['invoice']['items']) {
            foreach ($params['invoice']['items'] as $i) {
                if ($i['tax_type'] == 'APPEND' || !in_array(floatval($i['tax_percent']), crmAtolonlinePlugin::getAvailableTaxes())) {
                    return null;
                }
            }
        }

        $client_contact_id = $params['invoice']['contact_id'];
        $client_contact = new waContact($client_contact_id);
        if (!$client_contact->exists()) {
            return null;
        }

        $view = wa()->getView();
        $view->assign(array(
            'invoice' => $params['invoice']
        ));

        return $view->fetch(wa()->getAppPath('plugins/atolonline/templates/InvoiceActions.html', 'crm'));
    }

    protected function getReceiptHtml($receipt)
    {
        $html = '';
        switch ($receipt['operation']) {
            case 'sell':
                $operation = _w('Приход');
                break;
            case 'sell_refund':
                $operation = _w('Возврат');
                break;
            default:
                $operation = ucfirst($receipt['operation']);
        }
        switch ($receipt['status']) {
            case 'done':
                $html =
                    '<span class="c-text-group">'
                        .'<span class="c-text">' .  waDateTime::format('datetime', strtotime($receipt['receipt_datetime'])) . '</span>'
                        .'<span class="c-text">' . $operation.': '.waCurrency::format('%{s}', $receipt['amount'], 'RUB') . '</span>'
                    .'</span>'
                    .'<span class="c-text-group">'
                        .'<span class="c-text"><span class="c-label">Смена:</span> ' . $receipt['shift_number'].'</span>'
                        .'<span class="c-text"><span class="c-label">Чек:</span> ' . $receipt['fiscal_receipt_number'] . '</span>'
                        .'<span class="c-text"><span class="c-label">ФД:</span> ' . $receipt['fiscal_document_number'] . '</span>'
                        .'<span class="c-text"><span class="c-label">ФПД:</span> ' . $receipt['fiscal_document_attribute'] . '</span>'
                    .'</span>';
                    //.'KKT: '.$receipt['ecr_registration_number'].', ФН: '.$receipt['fn_number']
                break;
            case 'wait':
                $html = waDateTime::format('datetime', strtotime($receipt['atol_timestamp']))
                    .' &mdash; UUID: '.$receipt['atol_uuid'];
                break;
            case 'fail':
                $html =  '<span class="c-text">' . waDateTime::format('datetime', strtotime($receipt['atol_timestamp'])) . ' &mdash; UUID: ' . $receipt['atol_uuid'] . '</span>'
                        .'<span class="c-text error">' . $receipt['error_type'].': '.$receipt['error_text'] . '</span>';
                break;
            case null:
                $html = waDateTime::format('datetime', strtotime($receipt['create_datetime']));
        }
        return $html;
    }

    public static function getSno()
    {
        return array(
            array('value' => '', 'title' => 'выберите значение'),
            array('value' => 'osn', 'title' => 'общая СН'),
            array('value' => 'usn_income', 'title' => 'упрощенная СН (доходы)'),
            array('value' => 'usn_income_outcome', 'title' => 'упрощенная СН (доходы минус расходы)'),
            array('value' => 'envd', 'title' => 'единый налог на вмененный доход'),
            array('value' => 'esn', 'title' => 'единый сельскохозяйственный налог'),
            array('value' => 'patent', 'title' => 'патентная СН'),
        );
    }

    public static function getPaymentObject()
    {
        return array(
            array('value' => 'commodity', 'title' => 'товар'),
            array('value' => 'excise', 'title' => 'подакцизный товар'),
            array('value' => 'job', 'title' => 'работа'),
            array('value' => 'service', 'title' => 'услуга'),
            array('value' => 'gambling_bet', 'title' => 'ставка в азартной игре'),
            array('value' => 'gambling_prize', 'title' => 'выигрыш в азартной игре'),
            array('value' => 'lottery', 'title' => 'лотерейный билет'),
            array('value' => 'lottery_prize', 'title' => 'выигрыш в лотерею'),
            array('value' => 'intellectual_activity', 'title' => 'результаты интеллектуальной деятельности'),
            array('value' => 'payment', 'title' => 'платеж'),
            array('value' => 'agent_commission', 'title' => 'агентское вознаграждение'),
            array('value' => 'composite', 'title' => 'несколько вариантов'),
            array('value' => 'another', 'title' => 'другое'),
        );
    }

    public static function getPaymentMethod()
    {
        return array(
            array('value' => 'full_prepayment', 'title' => 'полная предоплата'),
            array('value' => 'partial_prepayment', 'title' => 'частичная предоплата'),
            array('value' => 'advance', 'title' => 'аванс'),
            array('value' => 'full_payment', 'title' => 'полный расчет'),
            array('value' => 'partial_payment', 'title' => 'частичный расчет и кредит'),
            array('value' => 'credit', 'title' => 'кредит'),
            array('value' => 'credit_payment', 'title' => 'выплата по кредиту'),
        );
    }

    public function checkReceipts()
    {
        try {
            $retry = 10; // attempts

            $rm = new crmAtolonlineReceiptModel();

            $check = $sell = 0;

            $check_list = $rm->select('*')->where("status<>'done' AND retry<$retry")->fetchAll();

            foreach ($check_list as $receipt) {

                $res = false;
                try {
                    $res = crmAtolonlinePluginReceipt::check($receipt);
                } catch (waException $e) {
                }
                if ($res) {
                    $check++;
                }
            }

            $sell_list = $rm->select('*')->where("atol_uuid IS NULL")->fetchAll();
            $im = new crmInvoiceModel();
            $iim = new crmInvoiceItemsModel();
            foreach ($sell_list as $receipt) {

                $invoice = $im->getById($receipt['invoice_id']);
                if (!$invoice) {
                    continue;
                } else {
                    $invoice['items'] = $iim->getItems($invoice['id']);
                }
                try {
                    $res = crmAtolonlinePluginReceipt::sell(array('invoice' => $invoice, 'receipt' => $receipt));
                } catch (waException $e) {
                }
                if ($res) {
                    $sell++;
                }
            }

            if (wa()->getEnv() == 'cli') {
                echo count($check_list) + count($sell_list)." undone receipts handled; $check checks; $sell sells\n";
            }

        } catch (waException $e) {

        }
    }

    /*
    public static function getAccessHtml($name, $params = array())
    {
        $view = wa()->getView();
        $view->assign(array(
            'name'  => $name,
            'value' => ifset($params['value']),
            'id'    => ifset($params['id']),
        ));
        return $view->fetch(wa()->getAppPath('plugins/atolonline/templates/SettingsAccess.html', 'crm'));
    }
    */

    protected function isPluginAvailable()
    {
        $fields = array('login', 'pass', 'inn', 'payment_address', 'group_code', 'sno', 'email');
        foreach ($fields as $f) {
            if (empty($this->settings[$this->company_id.':'.$f])) {
                return false;
            }
        }
        return true;
    }

    public static function getTaxPercent($tax)
    {
        switch ($tax) {
            case 'vat0':
                return 0;
            case 'vat10':
            case 'vat110':
                return 10;
            case 'vat18':
            case 'vat118':
                return 18;
            case 'vat20':
            case 'vat120':
                return 20;
        }
        return 0;
    }

    public static function getTaxAmount($tax, $amount)
    {
        $tax_pc = self::getTaxPercent($tax);

        switch ($tax) {
            case 'vat0':
                return 0;
            case 'vat10':
            case 'vat18':
            case 'vat20':
                return $amount - $amount / ((100 + $tax_pc) / 100);
            case 'vat110':
            case 'vat118':
            case 'vat120':
                return $amount * $tax_pc / 100;
        }
        return 0;
    }

    public static function getAvailableTaxes()
    {
        return array(0, 10, 18, 20);
    }
}
