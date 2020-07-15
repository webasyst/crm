<?php

class crmAtolonlinePluginFrontendAtolController extends waController
{
    public function execute()
    {
        $data = file_get_contents("php://input");

        $json = json_decode($data, true);

        waLog::dump($data, 'crm/plugins/atolonline/callback.log');

        /*
        $json = array(
            "uuid" => "88449cbf-1646-41c0-bd5c-e76bace10b26",
            "error" => array(
                "code" => 2,
                "text" => "Некорректный запрос",
                "type" => "system"
            ),
            "status" => "fail",
            "payload" => null,
            "timestamp" => "12.04.2017 18:58:38",
            "callback_url" => ""
        );
        $json = array(
            "uuid" => "88449cbf-1646-41c0-bd5c-e76bace10b26",
            "error" => null,
            "status" => "done",
            "payload" => array(
                "total" => 1598,
                "fns_site" => "www.nalog.ru",
                "fn_number" => "1110000100238211",
                "shift_number" => 23,
                "receipt_datetime" => "12.04.2017 20:16:00",
                "fiscal_receipt_number" => 6,
                "fiscal_document_number" => 133,
                "ecr_registration_number" => "0000111118041361",
                "fiscal_document_attribute" => 3449555941
            ),
            "timestamp" => "12.04.2017 20:15:08",
            "group_code" => "MyCompany_MyShop",
            "daemon_code" => "prod-agent-1",
            "device_code" => "KSR13.00-1-11",
            "callback_url" => ""
        );
        */

        crmAtolonlinePluginReceipt::receipt($json);

        http_response_code(200);
        exit;
    }
}
