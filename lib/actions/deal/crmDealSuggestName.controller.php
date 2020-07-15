<?php
/**
 * !!! TODO: not used?..
 */
class crmDealSuggestNameController extends crmJsonController
{
    public function execute()
    {
        $contact_info = $this->getContactInfo();
        $this->response = array(
            'name' => crmHelper::suggestName($contact_info)
        );
    }

    protected function getContactInfo()
    {
        $data = (array) $this->getRequest()->post('data');
        $data = (array) ifset($data['contact']);
        $id = (int) ifset($data['id']);

        if ($id <= 0) {

            unset($data['id']);

            if (isset($data['email'])) {
                if (isset($data['email']['value'][0])) {
                    $email = $data['email']['value'][0];
                    $data['email'] = array(
                        array(
                            'value' => $email
                        )
                    );
                } else {
                    unset($data['email']);
                }
            }

            return $data;
        }

        return new waContact($id);
    }
}