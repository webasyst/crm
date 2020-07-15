<?php
wa('team');
/**
 * Contact info editor tab in profile.
 */
class crmContactProfileInfoAction extends teamProfileInfoAction
{
    public function __construct($params = null)
    {
        if (empty($params['id'])) {
            $params['id'] = waRequest::request('id', null, 'int');
        }
        parent::__construct($params);
    }

    protected function getAssets()
    {
        return array();
    }

    protected function canEdit()
    {
        if ($this->contact['is_user'] > 0) {
            return false;
        }
        $rights = new crmRights();
        return $rights->contact($this->id);
    }

    protected function getSaveUrl($can_edit)
    {
        return wa()->getAppUrl('crm').'?module=contact&action=profileSave';
    }

    protected function getTemplate()
    {
        return wa()->getAppPath('templates/actions/profile/ProfileInfo.html', 'team');
    }

    public function display($clear_assign = true)
    {
        wa('team', 1);
        $result = parent::display($clear_assign);
        wa('crm', 1);
        return $result;
    }
}
