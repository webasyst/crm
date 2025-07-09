<?php

class crmRightConfig extends waRightConfig
{
    /**
     * Access levels to funnel
     */
    const RIGHT_FUNNEL_NONE = 0;
    const RIGHT_FUNNEL_OWN = 1;
    const RIGHT_FUNNEL_OWN_UNASSIGNED = 2;
    const RIGHT_FUNNEL_ALL = 3;

    /**
     * Access levels to invoices
     */
    const RIGHT_INVOICES_NONE = 0;
    const RIGHT_INVOICES_OWN = 1;
    const RIGHT_INVOICES_ALL = 2;

    /**
     * Dynamic (calculable, not stored in DB) access levels to deal
     * @see crmRights::deal()
     */
    const RIGHT_DEAL_NONE = 0;
    const RIGHT_DEAL_VIEW = 1;
    const RIGHT_DEAL_EDIT = 2;      // can view, can edit
    const RIGHT_DEAL_ALL  = 999;    // can view, can edit, can delete

    /**
     * Access levels to call(s)
     */
    const RIGHT_CALL_NONE = 0;
    const RIGHT_CALL_OWN = 1;
    const RIGHT_CALL_ALL = 999;

    /**
     * Access levels to conversations and messages only in list contexts
     */
    const RIGHT_CONVERSATION_OWN = 1;             // only own conversations
    const RIGHT_CONVERSATION_OWN_OR_FREE = 2;     // access only to own conversations or conversations without responsible (named free)
    const RIGHT_CONVERSATION_ALL = 999;

    public function init()
    {
        //
        // Contacts
        //
        $this->addItem('edit', _w('Can edit or delete contacts added by other users'), 'checkbox');
        $this->addItem('export', _w('Can export contacts and deals'), 'checkbox');

        //
        // Funnels
        //
        $fm = new crmFunnelModel();
        $funnels = $fm->select('id, name')->order('sort, name')->fetchAll('id', true);

        if ($funnels) {
            $this->addItem('funnel', _w('Access to funnels'), 'selectlist', array(
                'position' => 'right',
                'items'    => $funnels,
                'options'  => array(
                    self::RIGHT_FUNNEL_NONE =>           _w('No access'),
                    self::RIGHT_FUNNEL_OWN  =>           _w('Only own deals'),
                    self::RIGHT_FUNNEL_OWN_UNASSIGNED => _w('Own and unassigned deals'),
                    self::RIGHT_FUNNEL_ALL  =>           _w('Full access'),
                ),
            ));
        }

        //
        // Vaults
        //
        $vm = new crmVaultModel();
        $vaults = $vm->select('id, name')->order('sort, name')->fetchAll('id', true);
        if ($vaults) {
            $this->addItem('vault', _w('Access to vaults'), 'list', array(
                'position' => 'right',
                'items'    => $vaults,
                // 'hint1'    => 'all_checkbox',
            ));
        }

        //
        // Invoices
        //
        $this->addItem('manage_invoices', _w('Can manage invoices'), 'select', array(
            'options' => array(
                self::RIGHT_INVOICES_NONE => _w('No access'),
                self::RIGHT_INVOICES_OWN => _w('Only own invoices'),
                self::RIGHT_INVOICES_ALL => _w('Full access'),
            ),
        ));


        //
        // Calls
        //
        $this->addItem('calls', _w('Access to calls'), 'select', array(
            'options' => array(
                self::RIGHT_CALL_NONE => _w('No access'),
                self::RIGHT_CALL_OWN => _w('Only own calls'),
                self::RIGHT_CALL_ALL => _w('Full access')
            )
        ));

        //
        // Conversations
        //
        $this->addItem('conversations', _w('Access to conversations of accessible contacts'), 'select', array(
            'options' => array(
                self::RIGHT_CONVERSATION_OWN => _w('Only own'),
                self::RIGHT_CONVERSATION_OWN_OR_FREE => _w('Own or free'),
                self::RIGHT_CONVERSATION_ALL => _w('Full access')
            )
        ));

        wa('crm')->event('rights.config', $this);
    }

    public function getDefaultRights($contact_id)
    {
        return array(
            'conversations' => self::RIGHT_CONVERSATION_OWN_OR_FREE
        );
    }
}
