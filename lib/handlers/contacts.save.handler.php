<?php
class crmContactsSaveHandler extends waEventHandler
{
    public function execute(&$params)
    {
        /** @var waContact $contact */
        $contact = $params;

        if ($contact['is_company']) {
            $contact_model = new waContactModel();
            $contact_model->updateByField(array(
                'company_contact_id' => $contact['id'],
            ), array(
                'company' => $contact['name'],
            ));
        }
    }
}
