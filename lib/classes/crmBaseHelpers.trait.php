<?php

trait crmBaseHelpersTrait
{
    /**
     * @var crmRights
     */
    protected $crm_rights;

    /**
     * cached models for models getters
     * @var array
     */
    protected $models;

    public function getCrmRights()
    {
        return $this->crm_rights ? $this->crm_rights : ($this->crm_rights = new crmRights());
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
     * @return crmDealParamsModel
     */
    protected function getDealParamsModel()
    {
        return $this->getModel('deal_params', 'crmDealParamsModel');
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
     * @return crmMessageReadModel
     */
    protected function getMessageReadModel()
    {
        return $this->getModel('message_read', 'crmMessageReadModel');
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
     * @return crmNoteAttachmentsModel
     */
    protected function getNoteAttachmentsModel()
    {
        return $this->getModel('note_attachments', 'crmNoteAttachmentsModel');
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
     * @return crmContactModel
     */
    protected function getResponsibleModel()
    {
        return $this->getModel('responsible', 'crmContactModel');
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
}