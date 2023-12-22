<?php

class crmContactAddCompanyContactSaveController extends crmJsonController
{
    public function execute()
    {
        $client = waRequest::post('contact', null, waRequest::TYPE_ARRAY);
        if (!$client['id']) {
            throw new waException('No contact identifier', 404);
        }

        $contact = new crmContact($client['id']);

        if (empty($contact) || !$contact->exists()) {
            $this->notFound(_w('Contact not found'));
        }
        if ($contact['is_company'] > 0) {
            throw new waException('This contact is a company', 403);
        }
        if (!$this->getCrmRights()->contactEditable($contact)) {
            throw new waRightsException('Access to the contact is denied.');
        }

        $company = waRequest::post('company', 0, waRequest::TYPE_ARRAY);
        if ((int) $company['id'] < 0) {
            throw new waException('No company_id', 404);
        }

        // If the company is selected
        if ((int) $company['id'] > 0) {
            $company = new waContact((int) $company['id']);
            if (empty($company) || !$company->exists()) {
                $this->notFound('Company not found');
            }
            if (!$company['is_company']) {
                throw new waException('This contact is not a company', 403);
            }
            if (!$this->getCrmRights()->contactEditable($company)) {
                throw new waRightsException('Access to a company is denied');
            }

            // Update contact
            $data = array(
                'company'            => $company['name'],
                'company_contact_id' => $company['id'],
            );
            if (!empty($client['position'])) {
                $data['jobtitle'] = $client['position'];
            }
            $contact->save($data);
            return;
        }

        //
        if ((int) $company['id'] === 0) {
            if (!trim($company['name'])) {
                throw new waException('No company name', 403);
            }

            //Create new company contact
            $data = array(
                'company'     => trim($company['name']),
                'is_company'  => 1,
                'crm_user_id' => $this->autoResponsible(),
            );

            $company = new crmContact();
            $company->save($data);

            // Update contact
            $data = array(
                'company_contact_id' => $company['id'],
                'company'            => $company['name'],
            );
            if (!empty($client['position'])) {
                $data['jobtitle'] = trim($client['position']);
            }

            $contact->save($data);

            return;
        }

        $this->errors = array(_w('Unknown error'));
    }

    protected function autoResponsible()
    {
        if (!wa()->getUser()->getSettings('crm', 'contact_create_not_responsible')) {
            return wa()->getUser()->getId();
        }
        return null;
    }
}
