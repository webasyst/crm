<?php

return array(
    'name' => 'CRM',    // _w('CRM')
    'items' => array(
        'tagged' => array(
            'name'  => 'Tagged',    // _w('Tagged')
            'multi' => true,
            'joins' => array(
                ':contact_tags' => array(
                    'table' => 'crm_contact_tags'
                ),
                ':tag' => array(
                    'table' => 'crm_tag',
                    'on' => ':contact_tags.tag_id = :table.id'
                ),
            ),
            'items' => array(
                'tag' => array(
                    'name' => 'Tag', // _w('Tag')
                    'items' => array(
                        ':values' => array(
                            "autocomplete" => "WHERE `name` LIKE '%:term%'",
                            "limit" => 10,
                            "sql" => "SELECT `name`, `count` 
                                      FROM `crm_tag`
                                      :autocomplete
                                      LIMIT :limit",
                            'where' => array(
                                '=' => ":tag.`name` = ':value'",
                                '*=' => ":tag.`name` LIKE '%:value%'"
                            )
                        ),
                    ),
                ),
            )
        ),
        'deal_participants' => array(
            'name' => 'Participates in deals',  // _w('Participates in deals')
            'joins' => array(
                ':participants' => array(
                    'table' => 'crm_deal_participants',
                ),
                ':deal' => array(
                    'table' => 'crm_deal',
                    'on' => ':table.id = :participants.deal_id'
                ),
            ),
            'multi' => true,
            'items' => array(
                'period' => array(
                    'name' => 'Period',     // _w('Period')
                    'items' => array(
                        ':period' => array(
                            'name' => 'select a period',    // _w('select a period')
                            'where' => array(
                                ':between' => "DATE(:deal.create_datetime) >= ':0' AND DATE(:deal.create_datetime) <= ':1'",
                                ':gt' => "DATE(:deal.create_datetime) >= ':?'",
                                ':lt' => "DATE(:deal.create_datetime) <= ':?'",
                            )
                        )
                    )
                ),
                'funnel' => array(
                    'name' => 'Funnel',   // _w('Funnel')
                    ':class' => 'crmContactsSearchDealItem',
                    'options' => array('item_id' => crmContactsSearchDealItem::ITEM_ID_FUNNEL)
                ),
                'stage' => array(
                    'name' => 'Stage',      // _w('Stage')
                    ':class' => 'crmContactsSearchDealItem',
                    'options' => array('item_id' => crmContactsSearchDealItem::ITEM_ID_STAGE)
                ),
                'status' => array(
                    'name' => 'Status',     // _w('Status'),
                    ':class' => 'crmContactsSearchDealItem',
                    'options' => array('item_id' => crmContactsSearchDealItem::ITEM_ID_STATUS)
                ),
                'lost_reason' => array(
                    'name' => 'Lost reason',     // _w('Lost reason')
                    ':class' => 'crmContactsSearchDealItem',
                    'options' => array('item_id' => crmContactsSearchDealItem::ITEM_ID_LOST_REASON)
                )
            )
        ),
        'invoice' => array(
            'name' => 'Has invoices',  // _w('Has invoices')
            'joins' => array(
                ':invoice' => array(
                    'table' => 'crm_invoice',
                )
            ),
            'multi' => true,
            'items' => array(
                'period' => array(
                    'name' => 'Period',     // _w('Period')
                    'items' => array(
                        ':period' => array(
                            'name' => 'select a period',    // _w('select a period')
                            'where' => array(
                                ':between' => "DATE(:invoice.create_datetime) >= ':0' AND DATE(:invoice.create_datetime) <= ':1'",
                                ':gt' => "DATE(:invoice.create_datetime) >= ':?'",
                                ':lt' => "DATE(:invoice.create_datetime) <= ':?'",
                            )
                        )
                    )
                ),
                'status' => array(
                    'name' => 'Status',     // _w('Status'),
                    'readonly' => array('combobox' => false),
                    'items' => array(
                        array('value' => 'PENDING', 'name' => 'Pending'),   // _w('Pending')
                        array('value' => 'PAID', 'name' => 'Paid'),         // _w('Paid')
                        array('value' => 'REFUNDED', 'name' => 'Refunded'), // _w('Refunded')
                        array('value' => 'ARCHIVED', 'name' => 'Archived'), // _w('Archived')
                        array('value' => 'DRAFT', 'name' => 'Draft'),       // _w('Draft')
                        array('value' => 'PROCESSING', 'name' => 'Processing'), // _w('Processing')
                    ),
                    'where' => array(
                        '=' => array(
                            'PENDING' => ":invoice.state_id = 'PENDING'",
                            'PAID' => ":invoice.state_id = 'PAID'",
                            'REFUNDED' => ":invoice.state_id = 'REFUNDED'",
                            'ARCHIVED' => ":invoice.state_id = 'ARCHIVED'",
                            'DRAFT' => ":invoice.state_id = 'DRAFT'",
                            'PROCESSING' => ":invoice.state_id = 'PROCESSING'",
                        )
                    )
                ),
            )
        ),
        'category' => array(
            'name' => 'Segment',    // _w('Segment')
            'multi' => true,
            'joins' => array(
                ':contact_categories' => array(
                    'table' => 'wa_contact_categories'
                ),
                ':contact_category' => array(
                    'table' => 'wa_contact_category',
                    'on' => ':contact_categories.category_id = :table.id'
                ),
                ':segment' => array(
                    'table' => 'crm_segment',
                    'on' => ":contact_category.id = :table.category_id AND :table.type = 'category'"
                ),
            ),
            'items' => array(
                'category' => array(
                    'name' => 'Segment', // _w('Segment')
                    'items' => array(
                        ':values' => array(
                            'autocomplete' => 1,
                            'class' => 'crmContactsSearchSegmentValues'
                        )
                    )
                )
            ),
        )
    )
);
