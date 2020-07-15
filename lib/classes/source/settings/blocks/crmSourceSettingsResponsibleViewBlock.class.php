<?php

class crmSourceSettingsResponsibleViewBlock extends crmSourceSettingsViewBlock
{
    public function getAssigns()
    {
        $funnel_id = $this->getFunnelId();
        $responsible_contact_id = $this->source->getResponsibleContactId();

        $group_id = $responsible_contact_id < 0 ? -$responsible_contact_id : '';
        $user = array(
            'id' => '',
            'photo_url' => '',
            'name' => ''
        );

        if ($responsible_contact_id == 0 && ifset($this->options['group_id']) === 'personally') {
            $group_id = 'personally';
        }

        if ($responsible_contact_id > 0) {
            $group_id = 'personally';
            if ($this->hasContactAccessToFunnel($responsible_contact_id, $funnel_id)) {
                $contact = new waContact($responsible_contact_id);
                $user = array(
                    'id' => $contact['id'],
                    'photo_url' => waContact::getPhotoUrl($contact['id'], $contact['photo'], 96),
                    'name' => waContactNameField::formatName($contact)
                );
            }
        }

        if (empty($this->source->getParam('create_deal'))) {
            $groups = $this->getCrmRights()->getAvailableGroupsForCrm();
        } else {
            $groups = $this->getCrmRights()->getAvailableGroupsForFunnel($funnel_id);
        }

        return array(
            'responsible_contact_id' => $responsible_contact_id,
            'group_id' => $group_id,
            'groups' => $groups,
            'user' => $user
        );
    }

    protected function hasContactAccessToFunnel($contact_id, $funnel_id)
    {
        if ($contact_id > 0 && ifset($this->options['check_user_funnel_right'])) {
            $r = new crmRights(array(
                'contact' => $contact_id
            ));
            return $r->funnel($funnel_id);
        }
        return true;
    }
}
