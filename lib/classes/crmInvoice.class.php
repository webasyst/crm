<?php

class crmInvoice
{
    /**
     * @var array[string]waModel
     */
    static protected $models;

    public static function getState($state_id = null)
    {
        $states = self::getStates();
        if ($state_id) {
            return ifempty($states, $state_id, array('name' => $state_id));
        } else {
            return $states;
        }
    }

    public static function getStates($all = false)
    {
        $wa_app_url = wa()->getAppUrl('crm', true);
        $states = array(
            "DRAFT" => array(
                "id" => "DRAFT",
                "name" => _w('Draft'),
                "uri" => $wa_app_url."invoice/?state=draft",
                "icon" => "status-gray-tiny",
                "class" => "draft"
            ),
            "PENDING" => array(
                "id" => "PENDING",
                "name" => _w('Pending'),
                "uri" => $wa_app_url."invoice/?state=pending",
                "icon" => "status-green-tiny",
                "class" => "pending"
            ),
            "PROCESSING" => array(
                "id" => "PROCESSING",
                "name" => _w('Processing'),
                "uri" => $wa_app_url."invoice/?state=processing",
                "icon" => "light-bulb",
                "class" => "processing"
            ),
            "PAID" => array(
                "id" => "PAID",
                "name" => _w('Paid'),
                "uri" => $wa_app_url."invoice/?state=paid",
                "icon" => "yes-bw",
                "class" => "paid"
            ),
            "REFUNDED" => array(
                "id" => "REFUNDED",
                "name" => _w('Refunded'),
                "uri" => $wa_app_url."invoice/?state=refunded",
                "icon" => "no-bw",
                "class" => "refunded"
            ),
            "ARCHIVED" => array(
                "id" => "ARCHIVED",
                "name" => _w('Archived'),
                "uri" => $wa_app_url."invoice/?state=archived",
                "icon" => "trash",
                "class" => "archived"
            ),
        );

        if ($all) {
            return array(
                "ALL" => array(
                    "name" => _w('All'),
                    "uri" => $wa_app_url."invoice/?state=all"
                ),
            ) + $states;
        } else {
            return $states;
        }
    }

    /**
     * @param array $options
     * @return null|array
     */
    public static function cliInvoicesArchive($options = array())
    {
        self::getSettingsModel()->set('crm', 'invoices_archive_cli_start', date('Y-m-d H:i:s'));

        /**
         * @event start_reminders_recap_worker
         */
        wa('crm')->event('start_invoices_archive_worker');

        $im = new crmInvoiceModel();
        $now = date('Y-m-d');
        $count = 0;

        $list = $im->select('*')->where("due_date IS NOT NULL AND due_date < '$now' AND state_id = 'PENDING'")->fetchAll();
        foreach ($list as $invoice) {

            $im->updateById($invoice['id'], array(
                'state_id'        => 'ARCHIVED',
                'update_datetime' => date('Y-m-d H:i:s'),
            ));

            $params = array('invoice' => $invoice);
            /**
             * @event invoice_expire
             * @param array [string]mixed $params
             * @param array [string]array $params['invoice']
             * @return bool
             */
            wa('crm')->event('invoice_expire', $params);
        }

        self::getSettingsModel()->set('crm', 'invoices_archive_cli_end', date('Y-m-d H:i:s'));

        return array(
            'total_count'     => $count,
            'processed_count' => $count,
            'count'           => $count,
            'done'            => $count,
        );
    }

    public static function getLastCliRunDateTime()
    {
        return self::getSettingsModel()->get('crm', 'invoices_archive_cli_end');
    }

    public static function isCliOk()
    {
        return !!self::getLastCliRunDateTime();
    }

    protected static function getSettingsModel()
    {
        return !empty(self::$models['asm']) ? self::$models['asm'] : (self::$models['asm'] = new waAppSettingsModel());
    }
}
