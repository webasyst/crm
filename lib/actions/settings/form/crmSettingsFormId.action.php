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
            'messages' => $form->getMessages(),
            'type' => 'form'
        );
        return crmHelper::renderViewAction(
            new crmSettingsMessagesBlockAction($params)
        );
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
