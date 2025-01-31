<?php

class crmJsonController extends waJsonController
{
    protected $params;

    /**
     * @var crmRights
     */
    protected $crm_rights;

    /**
     * cached models for models getters
     * @var array
     */
    protected $models;

    /**
     * @var crmContact
     */
    protected $user_contact;

    public function getCrmRights($options = array())
    {
        return $this->crm_rights ? $this->crm_rights : ($this->crm_rights = new crmRights($options));
    }

    public function error($msg = null)
    {
        throw new waException($msg ? $msg : _w('Server error'));
    }

    /**
     * @return crmContact
     */
    public function getUserContact()
    {
        return $this->user_contact !== null ? $this->user_contact : ($this->user_contact = new crmContact($this->getUserId()));
    }

    /**
     * @param null $msg
     * @throws waException
     */
    public function notFound($msg = null)
    {
        throw new crmNotFoundException($msg ? $msg : _w('Page not found'), 404);
    }

    /**
     * @param null $msg
     * @throws waRightsException
     */
    public function accessDenied($msg = null)
    {
        throw new crmAccessDeniedException($msg ? $msg : _w('Access denied'), 403);
    }

    public function accessDeniedForNotAdmin($msg = null)
    {
        if (!$this->getCrmRights()->isAdmin()) {
            $this->accessDenied($msg);
        }
    }

    /**
     * @param string|null $name
     * @param mixed $default
     * @param string $type
     * @return mixed
     */
    public function getParameter($name = null, $default = null, $type = null)
    {
        $params = (array) $this->params;
        if (array_key_exists($name, $params)) {
            $ext_name = __CLASS__ . "::\$params[{$name}]";
            waRequest::setParam($ext_name, $params[$name]);
            return waRequest::param($ext_name, $default, $type);
        }
        $test_default = uniqid(__METHOD__);
        $value = waRequest::param($name, $test_default);
        if ($value !== $test_default) {
            return waRequest::param($name, $default, $type);
        }
        return waRequest::request($name, $default, $type);
    }

    /**
     * @return crmConfig
     */
    public function getConfig()
    {
        return parent::getConfig();
    }

    protected function renderTemplate($template, $assign = array())
    {
        if (!file_exists($template)) {
            return '';
        }
        $view = wa()->getView();
        $old_vars = $view->getVars();
        $view->clearAllAssign();
        $view->assign($assign);
        $html = $view->fetch($template);
        $view->clearAllAssign();
        $view->assign($old_vars);
        return $html;

    }

    /**
     * @return crmAdhocGroupModel
     */
    protected function getAdhocGroupModel()
    {
        return $this->getModel('adhoc_group', 'crmAdhocGroupModel');
    }

    /**
     * @return crmCallModel
     */
    protected function getCallModel()
    {
        return $this->getModel('call', 'crmCallModel');
    }

    /**
     * @return crmCallParamsModel
     */
    protected function getCallParamsModel()
    {
        return $this->getModel('call_params', 'crmCallParamsModel');
    }

    /**
     * @return crmPbxModel
     */
    protected function getPbxModel()
    {
        return $this->getModel('pbx', 'crmPbxModel');
    }

    /**
     * @return crmPbxUsersModel
     */
    protected function getPbxUsersModel()
    {
        return $this->getModel('pbx_users', 'crmPbxUsersModel');
    }

    /**
     * @return crmPbxParamsModel
     */
    protected function getPbxParamsModel()
    {
        return $this->getModel('pbx_params', 'crmPbxParamsModel');
    }

    /**
     * @return crmCompanyModel
     */
    protected function getCompanyModel()
    {
        return $this->getModel('company', 'crmCompanyModel');
    }

    /**
     * @return crmContactModel
     */
    protected function getContactModel()
    {
        return $this->getModel('contact', 'crmContactModel');
    }

    /**
     * @return crmContactTagsModel
     */
    protected function getContactTagsModel()
    {
        return $this->getModel('contacts_tag', 'crmContactTagsModel');
    }

    /**
     * @return crmCurrencyModel
     */
    protected function getCurrencyModel()
    {
        return $this->getModel('currency', 'crmCurrencyModel');
    }

    /**
     * @return crmDealModel
     */
    protected function getDealModel()
    {
        return $this->getModel('deal', 'crmDealModel');
    }

    /**
     * @return crmDealLostModel
     */
    protected function getDealLostModel()
    {
        return $this->getModel('deal_lost', 'crmDealLostModel');
    }

    /**
     * @return crmDealParticipantsModel
     */
    protected function getDealParticipantsModel()
    {
        return $this->getModel('deal_participants', 'crmDealParticipantsModel');
    }

    /**
     * @return crmFileModel
     */
    protected function getFileModel()
    {
        return $this->getModel('file', 'crmFileModel');
    }

    /**
     * @return crmFormModel
     */
    protected function getFormModel()
    {
        return $this->getModel('from', 'crmFormModel');
    }

    /**
     * @return crmFormParamsModel
     */
    protected function getFormParamsModel()
    {
        return $this->getModel('from_params', 'crmFormParamsModel');
    }

    /**
     * @return crmFunnelModel
     */
    protected function getFunnelModel()
    {
        return $this->getModel('funnel', 'crmFunnelModel');
    }

    /**
     * @return crmFunnelStageModel
     */
    protected function getFunnelStageModel()
    {
        return $this->getModel('funnel_stage', 'crmFunnelStageModel');
    }

    /**
     * @return crmInvoiceModel
     */
    protected function getInvoiceModel()
    {
        return $this->getModel('invoice', 'crmInvoiceModel');
    }

    /**
     * @return crmInvoiceItemsModel
     */
    protected function getInvoiceItemsModel()
    {
        return $this->getModel('invoice_items', 'crmInvoiceItemsModel');
    }

    /**
     * @return crmInvoiceParamsModel
     */
    protected function getInvoiceParamsModel()
    {
        return $this->getModel('invoice_params', 'crmInvoiceParamsModel');
    }

    /**
     * @return crmLogModel
     */
    protected function getLogModel()
    {
        return $this->getModel('log', 'crmLogModel');
    }

    /**
     * @return crmMessageModel
     */
    protected function getMessageModel()
    {
        return $this->getModel('message', 'crmMessageModel');
    }

    /**
     * @return crmMessageAttachmentsModel
     */
    protected function getMessageAttachmentsModel()
    {
        return $this->getModel('message_attachments', 'crmMessageAttachmentsModel');
    }

    /**
     * @return crmMessageRecipientsModel
     */
    protected function getMessageRecipientsModel()
    {
        return $this->getModel('message_recipients', 'crmMessageRecipientsModel');
    }

    /**
     * @return crmMessageParamsModel
     */
    protected function getMessageParamsModel()
    {
        return $this->getModel('message_params', 'crmMessageParamsModel');
    }

    /**
     * @return crmMessageReadModel
     * @throws waException
     */
    protected function getMessageReadModel()
    {
        return $this->getModel('message_read', 'crmMessageReadModel');
    }

    /**
     * @return crmConversationModel
     */
    protected function getConversationModel()
    {
        return $this->getModel('conversation', 'crmConversationModel');
    }


    /**
     * @return crmNoteModel
     */
    protected function getNoteModel()
    {
        return $this->getModel('note', 'crmNoteModel');
    }

    /**
     * @return crmNotificationModel
     */
    protected function getNotificationModel()
    {
        return $this->getModel('notification', 'crmNotificationModel');
    }

    /**
     * @return crmPaymentModel
     */
    protected function getPaymentModel()
    {
        return $this->getModel('payment', 'crmPaymentModel');
    }

    /**
     * @return crmPaymentSettingsModel
     */
    protected function getPaymentSettingsModel()
    {
        return $this->getModel('payment_settings', 'crmPaymentSettingsModel');
    }

    /**
     * @return crmRecentModel
     */
    protected function getRecentModel()
    {
        return $this->getModel('recent', 'crmRecentModel');
    }

    /**
     * @return crmReminderModel
     */
    protected function getReminderModel()
    {
        return $this->getModel('reminder', 'crmReminderModel');
    }

    /**
     * @return crmSegmentModel
     */
    protected function getSegmentModel()
    {
        return $this->getModel('segment', 'crmSegmentModel');
    }

    /**
     * @return crmTempModel
     */
    protected function getSignupTempModel()
    {
        return $this->getModel('temp', 'crmTempModel');
    }

    /**
     * @return crmTagModel
     */
    protected function getTagModel()
    {
        return $this->getModel('tag', 'crmTagModel');
    }

    /**
     * @return crmVaultModel
     */
    protected function getVaultModel()
    {
        return $this->getModel('vault', 'crmVaultModel');
    }

    /**
     * @return crmSourceModel
     */
    protected function getSourceModel()
    {
        return $this->getModel('source', 'crmSourceModel');
    }

    /**
     * @return crmSourceParamsModel
     */
    protected function getSourceParamsModel()
    {
        return $this->getModel('source_params', 'crmSourceParamsModel');
    }

    /**
     * @param $key
     * @param $class
     * @throws waException
     * @return waModel
     */
    private function getModel($key, $class)
    {
        if (!isset($this->models[$key]) || get_class($this->models[$key]) !== $class) {
            $this->models[$key] = new $class();
        }
        if (!($this->models[$key] instanceof waModel)) {
            throw new waException('Class must be instance of crmModel');
        }
        return $this->models[$key];
    }

    protected function isSMSConfigured()
    {
        return waSMS::adapterExists();
    }
}
