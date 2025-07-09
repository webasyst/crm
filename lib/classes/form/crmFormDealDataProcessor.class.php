<?php

class crmFormDealDataProcessor
{
    /**
     * @var crmForm
     */
    protected $form;

    /**
     * @var array
     */
    protected $options;


    public function __construct(crmForm $form, $options = array())
    {
        $this->form = $form;
        $this->options = $options;
    }

    /**
     * @param $data
     * @param crmContact $contact
     * @return array|bool
     */
    public function process($data, crmContact $contact)
    {
        $deal = $this->createDeal($contact, $data);
        if (!$deal) {
            return false;
        }
        return array(
            'deal' => $deal
        );
    }

    protected function getFormFields()
    {
        return crmFormProcessor::getFormFields($this->form);
    }

    /**
     * @param waContact $contact
     * @param array $data
     * @return bool|array
     */
    protected function createDeal($contact, $data)
    {
        $source = $this->form->getSource();

        $deal = array(
            'name' => $contact->getName(),
            'contact_id' => $contact->getId(),
            'creator_contact_id' => $contact->getId(),
        );

        if (!empty($data['!deal_description'])) {
            $deal['description'] = (string)$data['!deal_description'];
            unset($data['!deal_description']);
            $fld = $this->form->getFieldByUid('!deal_description');
            if (!$fld || empty($fld['redactor'])) {
                $deal['description'] = nl2br($deal['description']);
            }
        }

        if (!empty($data['!deal_attachments'])) {
            $deal['files'] = $data['!deal_attachments'];
            unset($data['!deal_attachments']);
        }

        $params = array();
        foreach ($this->getFormFields() as $field) {
            if (crmFormConstructor::isFieldOfDeal($field, false) && isset($data[$field['id']])) {
                $params[$field['id']] = $data[$field['id']];
            }
        }
        if (!empty($data['!form_page_url'])) {
            $params['!form_page_url'] = $data['!form_page_url'];
        }
        $deal['params'] = $params;

        $source->setAsEnabled();
        $id = $source->createDeal($deal);
        if ($id <= 0) {
            return false;
        }

        $dm = new crmDealModel();

        $deal = $dm->getById($id);
        $this->logCreateDeal($deal);

        return $deal;
    }

    protected function logCreateDeal($deal)
    {
        $this->logAction(crmDealModel::LOG_ACTION_ADD, array('deal_id' => $deal['id']), $deal['creator_contact_id']);
    }

    protected function logAction($action, $params = null, $subject_contact_id = null, $contact_id = null)
    {
        if (!class_exists('waLogModel')) {
            wa('webasyst');
        }
        $log_model = new waLogModel();
        return $log_model->add($action, $params, $subject_contact_id, $contact_id);
    }
}
