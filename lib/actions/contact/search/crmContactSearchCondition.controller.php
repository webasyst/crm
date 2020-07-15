<?php

class crmContactSearchConditionController extends waController
{  
    public function execute()
    {
        $id = $this->getRequest()->get('id');
        $op = $this->getRequest()->request('op');
        
        $user = wa()->getUser();
        $app_id = 'contacts';
        $name = 'search_form_items';

        if ($id) {
            if ($op === 'delete') {
                crmContactsSearchHelper::delContactItems($id);
                return;
            } else if ($op === 'remember') {
                crmContactsSearchHelper::setContactItems($id);
                return;
            } else if ($op === 'collapse_section' || $op === 'expand_section') {
                $map = $user->getSettings('contacts', 'crm_search_sidebar');
                if ($map) {
                    $map = array_fill_keys(explode(',', $map), 1);
                } else {
                    $map = array();
                }
                if ($op === 'collapse_section' && isset($map[$id])) {
                    unset($map[$id]);
                }
                if ($op === 'expand_section') {
                    $map[$id] = 1;
                }
                if (!$map) {
                    $user->delSettings('contacts', 'crm_search_sidebar');
                } else {
                    $user->setSettings('contacts', 'crm_search_sidebar', array_keys($map));
                }
                return;                
            }
        }

        echo wao(new crmContactSearchConditionAction())->display();
    }
}
