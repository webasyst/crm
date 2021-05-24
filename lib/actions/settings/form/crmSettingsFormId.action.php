<?php

class crmSettingsFormIdAction extends crmSettingsViewAction
{
    public function execute()
    {
        $this->accessDeniedForNotAdmin();

        $id = $this->getParameter('id');

        $form_constructor = new crmFormConstructor($id);
        if ($id !== 'new' && !$form_constructor->formExists()) {
            $this->notFound();
        }

        $this->view->assign(array(
            'form' => $form_constructor->getFormInfo(),
            'available_fields' => $form_constructor->getAvailableFields(),
            'messages_block' => $this->getMessagesBlock($form_constructor->getForm()),
            'app_static_url' => wa()->getAppStaticUrl('crm', true),
            'frontend_form_iframe_url' => $this->getIFrameUrl($form_constructor->getFormId()),
            'segments' => $this->getSegmentModel()->getMergedSegments($form_constructor->getForm()),
            'default_checked_fields' => $this->getDefaultCheckedFields(),
            'blocks' => $this->getBlocks($form_constructor->getForm())
        ));
    }

    protected function getBlocks(crmForm $form)
    {
        $source = $form->getSource();

        $blocks = array();
        foreach (array(
            new crmSourceSettingsWithContactViewBlock('form_with_contact', $source),
            new crmSourceSettingsCreateDealViewBlock('form_create_deal', $source),
            new crmSourceSettingsResponsibleViewBlock('form_responsible', $source)
        ) as $block) {
            $blocks[$block->getId()] = $block->render(array(
                'namespace' => 'form[source]',
                'form' => $form->getInfo()
            ));
        }
        return $blocks;
    }

    protected function getMessagesBlock(crmForm $form)
    {
        $params = array(
            'namespace' => 'form[params][messages]',
            'messages' => $this->getMessages($form),
            'type' => 'form'
        );

        return crmHelper::renderViewAction(
            new crmSettingsMessagesBlockAction($params)
        );
    }

    private function getMessages(crmForm $form)
    {
        $messages = $form->getMessages();
        foreach ($messages as &$message) {
            if (empty($message['is_smarty_tmpl'])) {
                $message['is_smarty_tmpl'] = true;
                $tmpl = $this->convertToSmarty($message['tmpl']);
                $message['tmpl'] = $tmpl;
            }
        }
        unset($message);
        return $messages;
    }

    private function convertToSmarty($tmpl)
    {
        $convert = [
            '{ORIGINAL_TEXT}' => '{$original_text}',
            '{COMPANY_NAME}' => '{$company_name|escape}',
            '{CUSTOMER_ID}' => '{$customer.id}',
            '{CUSTOMER_NAME}' => '{$customer.getName()|escape}',
        ];

        foreach (waContactFields::getAll() as $field_id => $field) {
            $key = '{CUSTOMER_' . strtoupper($field_id) . '}';
            $value = "{\$customer.get('{$field_id}', 'default')|escape}";
            $convert[$key] = $value;
        }

        return str_replace(array_keys($convert), array_values($convert), $tmpl);
    }

    protected function getIFrameUrl($form_id)
    {
        return wa()->getRouting()->getUrl(
            'crm/frontend/formIframe',
            array('id' => $form_id),
            true
        );
    }

    protected function getDefaultCheckedFields()
    {
        return array(
            'firstname',
            'lastname',
            'email',
            'password'
        );
    }
}
