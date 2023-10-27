<?php

abstract class crmContactsAction extends crmContactViewAction
{
    /**
     * This var is dynamic, see constructor
     * @var array
     */
    protected $available_sorts = array(
        'name' => array('name', 'ASC'),
        'create_datetime' => array('create_datetime', 'DESC'),
        'last_datetime' => array('last_datetime', 'DESC')
    );

    protected $available_view_types = array('thumbs','thumb-list', 'list');

    /**
     * @var waContactsCollection
     */
    private $collection;

    /**
     * @var string
     */
    private $raw_sort;

    /**
     * @var string
     */
    private $view_type;

    /**
     * @var string asc
     */
    private $asc;

    /**
     * @var array
     */
    private $columns;

    /**
     * @var array
     */
    private $columns_order;

    public function __construct($params = null)
    {
        parent::__construct($params);
        foreach ($this->getColumns() as $column) {
            if ($column['is_sortable']) {
                $order = 'ASC';
                if (isset($column['field']) && $column['field'] instanceof waContactDateField) {
                    $order = 'DESC';
                }
                $this->available_sorts[$column['id']] = array($column['id'], $order);
            }
        }
    }

    public function execute()
    {
        $view = $this->getViewType();

        $collection = $this->getCollection();
        $contacts = $this->getCollection()->getContacts($this->getFields(), $this->getOffset(), $this->getLimit());
        $contacts = $this->workupContacts($contacts);

        $limit = $this->getLimit();
        $total_count = $collection->count();
        $page_count = min($limit, $total_count);

        $this->view->assign(array(
            'asc'                 => $this->isAsc(),
            'view'                => $view,
            'sort'                => $this->getSort(),
            'page'                => $this->getPage(),
            'limit'               => $limit,
            'contacts'            => $contacts,
            'title'               => $collection->getTitle(),
            'total_count'         => $total_count,
            'page_count'          => $page_count,
            'all_contacts_count'  => $this->countAllContacts(),
            'hash'                => $this->getHash(),
            'contact_max_id'      => waRequest::cookie('contact_max_id', 0, waRequest::TYPE_INT),
            'contact_create_type' => wa()->getUser()->getSettings('crm', 'contact_create_type', 'dialog'),
        ));

        $this->view->assign($this->getColumns(true));

        $wcm = new waContactModel();
        wa()->getResponse()->setCookie('contact_max_id', $wcm->select('MAX(id) mid')->fetchField('mid'), time() + 86400);

        $this->afterExecute();

        wa('crm')->getConfig()->setLastVisitedUrl('contact/');
    }

    protected function afterExecute()
    {

    }

    protected function getFields()
    {
        $fields = ['*', 'photo_url_32', 'photo_url_50', 'photo_url_144', '_online_status', 'tags', 'address',
            'phone', 'im', 'url', 'socialnetwork', 'email'];
        $cm = $this->getContactModel();
        foreach ($this->getColumns() as $field_id => $field) {
            if (!$cm->fieldExists($field_id) && array_search($field_id, $fields, true) === false) {
                $fields[] = $field_id;
            }
        }
        return join(',', $fields);
    }

    protected function getSort()
    {
        $sort = $this->getRawSort();
        return is_array($sort) ? $sort[0] : $sort;
    }

    protected function getOrderBy()
    {
        return array($this->getSort(), $this->isAsc() ? 'ASC' : 'DESC');
    }

    protected function getColumns($with_order = false)
    {
        if ($this->columns === null) {
            $this->obtainColumns();
        }
        if ($with_order) {
            return array(
                'columns' => $this->columns,
                'columns_order' => $this->columns_order
            );
        } else {
            return $this->columns;
        }
    }

    /**
     * With side-effect
     */
    private function obtainColumns()
    {
        $contact = new crmContact($this->getUser()->getId());
        $contact_columns = $contact->getContactColumns();

        $columns = array();
        $columns_order = array();

        $all_columns = crmContact::getAllColumns();
        foreach ($all_columns as $column_id => $column) {


            if ($column['is_composite']) {

                foreach ($column['sub_columns'] as $sub_column_id => $sub_column) {

                    $full_column_id = $column_id . ':' . $sub_column_id;

                    if (empty($contact_columns[$full_column_id]) || !empty($contact_columns[$full_column_id]['off'])) {
                        continue;
                    }

                    if (empty($columns[$column_id])) {
                        $columns[$column_id] = $column;
                    }
                    $columns[$column_id]['sub_columns'][$sub_column_id] = $sub_column;

                    $sort = (int)ifset($contact_columns[$full_column_id]['sort']);
                    $width = (string)ifset($contact_columns[$full_column_id]['width']);
                    $columns_order[$sort] = (array) ifset($columns_order[$sort]);
                    $columns_order[$sort][] = array(
                        'width' => $width,
                        'is_composite' => true,
                        'column_id' => $column_id,
                        'sub_column_id' => $sub_column_id,
                        'full_column_id' => $full_column_id
                    );
                }
            } else {

                if (empty($contact_columns[$column_id]) || !empty($contact_columns[$column_id]['off'])) {
                    continue;
                }

                $columns[$column_id] = $column;

                $sort = (int)ifset($contact_columns[$column_id]['sort']);
                $width = (string)ifset($contact_columns[$column_id]['width']);
                $columns_order[$sort] = (array) ifset($columns_order[$sort]);
                $columns_order[$sort][] = array(
                    'width' => $width,
                    'is_composite' => false,
                    'column_id' => $column_id,
                    'sub_column_id' => '',
                    'full_column_id' => $column_id
                );
            }
        }

        ksort($columns_order);

        $plain_columns_order = array();
        foreach ($columns_order as $column_order) {
            foreach ($column_order as $order) {
                $plain_columns_order[] = $order;
            }
        }

        $this->columns = $columns;
        $this->columns_order = $plain_columns_order;

    }

    /**
     * @return waContactsCollection
     */
    protected function getCollection()
    {
        if ($this->collection instanceof waContactsCollection) {
            return $this->collection;
        }

        $order_by = $this->getOrderBy();

        // Does the ordering require a special join?
        $order_join_table = null;
        $order_join_table_on = '';
        $order_join_table_field = null;
        $columns = $this->getColumns();

        $cm = $this->getContactModel();
        $is_horizontal_field = $cm->fieldExists($order_by[0]);

        if (!$is_horizontal_field && !empty($columns[$order_by[0]]['field'])) {
            if ($columns[$order_by[0]]['field']->getStorage() instanceof waContactDataStorage) {
                $order_join_table = 'wa_contact_data';
                $order_join_table_field = 'value';
                $order_join_table_on = " AND :table.field='".$cm->escape($order_by[0])."'";
            } elseif ($columns[$order_by[0]]['field']->getStorage() instanceof waContactEmailStorage) {
                $order_join_table = 'wa_contact_emails';
                $order_join_table_field = 'email';
            }
        }

        $hash = $this->getHash();
        $options = array(
            'check_rights' => true,
            'transform_phone_prefix' => 'all_domains'
        );
        if ($order_join_table) {
            $options['update_count_ignore'] = true;
        }
        $this->collection = new crmContactsCollection($hash, $options);

        if ($order_join_table) {

            //
            // When sorting by email or data field, we have to combine resulting list
            // from two collections.
            //
            $collection1 = $this->collection;
            $collection2 = clone $collection1;
            $this->collection = new crmContactsCompositeCollection(array(
                $collection1, $collection2
            ));

            // First collection contains contacts with specified field set
            $table_alias = $collection1->addJoin(array(
                'table' => $order_join_table,
                'on' => 'c.id=:table.contact_id AND :table.sort=0 AND :table.`'.$order_join_table_field."`<>'' ".$order_join_table_on,
            ));
            $collection1->orderBy('~'.$table_alias.'.'.$order_join_table_field, $order_by[1]);

            // Second collection contains contacts with specified field not set
            $collection2->addLeftJoin(array(
                'table' => $order_join_table,
                'on' => 'c.id=:table.contact_id AND :table.sort=0 AND :table.`'.$order_join_table_field."`<>'' ".$order_join_table_on,
                'where' => ':table.contact_id IS NULL',
            ));

        } else {
            $this->collection->orderBy($order_by[0], $order_by[1]);
        }
        return $this->collection;
    }


    protected function workupContacts($contacts)
    {
        $columns = $this->getColumns();
        $all_columns = crmContact::getAllColumns();

        if (empty($columns)) {
            return $contacts;
        }

        // address.region is tight with address.country (if address.region present, so must be presented address.country)
        foreach ($columns as $column_id => $column) {
            if (!($column['field'] instanceof waContactAddressField)) {
                continue;
            }

            $region_presented = false;
            foreach ($column['sub_columns'] as $sub_column_id => $sub_column) {
                if ($sub_column['field'] instanceof waContactRegionField) {
                    $region_presented = true;
                    break;
                }
            }

            if (!$region_presented) {
                continue;
            }

            foreach ((array)ifset($all_columns[$column_id]['sub_columns']) as $sub_column_id => $sub_column) {
                if ($sub_column['field'] instanceof waContactCountryField) {
                    $columns[$column_id]['sub_columns'][$sub_column_id] = array(
                        'id'           => $sub_column_id,
                        'name'         => $sub_column['name'],
                        'is_composite' => false,
                        'is_multi'     => $sub_column['is_multi'],
                        'field'        => $sub_column['field']
                    );
                    break;
                }
            }
        }

        $regions = array();
        $countries = array();

        foreach ($contacts as &$contact) {

            $contact['columns'] = array();

            foreach ($columns as $column_id => $column) {

                if ($column['is_composite']) {
                    foreach ($column['sub_columns'] as $sub_column_id => $sub_column) {
                        $contact_column_value = $this->getSubColumnValue($contact, $column, $sub_column);

                        $is_country = $sub_column['field'] instanceof waContactCountryField;
                        $is_region = $sub_column['field'] instanceof waContactRegionField;

                        foreach ((array)$contact_column_value as $val) {
                            if ($is_country) {
                                $countries[$val] = $val;
                            }
                            if ($is_region) {
                                $regions[$val] = $val;
                            }
                        }

                        $contact['columns'][$column_id][$sub_column_id] = $contact_column_value;
                    }
                    continue;
                }

                if ($column['field'] instanceof waContactBirthdayField) {
                    $contact_column_value = $this->getBirthdayValue($contact, $column['field']);
                } else {
                    $contact_column_value = $this->getColumnValue($contact, $column);
                }

                $contact['columns'][$column_id] = $contact_column_value;

            }
        }
        unset($contact);

        if ($countries) {
            $cm = new waCountryModel();
            $cntrs = array();
            foreach ($cm->getByField(array('iso3letter' => array_keys($countries)), 'iso3letter') as $item) {
                $cntrs[$item['iso3letter']] = $item['name'];
                $countries = $cntrs;
            }
        }
        if ($regions) {
            $rm = new waRegionModel();
            $regs = array();
            foreach ($rm->getByField(array('country_iso3' => array_keys($countries), 'code' => $regions), true) as $item) {
                $regs[$item['country_iso3'] . ':' . $item['code']] = $item['name'];
            }
            $regions = $regs;
        }

        // bind countries and regions values

        if ($countries || $regions) {
            foreach ($contacts as &$contact) {
                foreach ($columns as $column_id => $column) {

                    if (!($column['field'] instanceof waContactAddressField)) {
                        continue;
                    }

                    $region_sub_column_id = '';
                    $country_sub_column_id = '';
                    foreach ($column['sub_columns'] as $sub_column_id => $sub_column) {
                        if ($sub_column['field'] instanceof waContactCountryField) {
                            $country_sub_column_id = $sub_column_id;
                        }
                        if ($sub_column['field'] instanceof waContactRegionField) {
                            $region_sub_column_id = $sub_column_id;
                        }
                    }

                    if ($country_sub_column_id && isset($contact['columns'][$column_id][$country_sub_column_id])) {

                        if ($region_sub_column_id && isset($contact['columns'][$column_id][$region_sub_column_id])) {
                            if (is_array($contact['columns'][$column_id][$region_sub_column_id])) {
                                foreach ($contact['columns'][$column_id][$region_sub_column_id] as $index => &$region_val) {
                                    $country_val = ifset($contact['columns'][$column_id][$country_sub_column_id][$index]);
                                    $region_key = $country_val . ':' . $region_val;
                                    $region_val = isset($regions[$region_key]) ? $regions[$region_key] : $region_val;
                                }
                                unset($region_val);
                            } else {
                                $country_val = $contact['columns'][$column_id][$country_sub_column_id];
                                $region_val = $contact['columns'][$column_id][$region_sub_column_id];
                                $region_key = $country_val . ':' . $region_val;
                                $value = isset($regions[$region_key]) ? $regions[$region_key] : $region_val;
                                $contact['columns'][$column_id][$region_sub_column_id] = $value;
                            }
                        }

                        if (is_array($contact['columns'][$column_id][$country_sub_column_id])) {
                            foreach ($contact['columns'][$column_id][$country_sub_column_id] as &$value) {
                                $value = isset($countries[$value]) ? _ws($countries[$value]) : $value;
                            }
                            unset($value);
                        } else {
                            $value = $contact['columns'][$column_id][$country_sub_column_id];
                            $contact['columns'][$column_id][$country_sub_column_id] = isset($countries[$value]) ? $countries[$value] : $value;
                        }

                    }


                }
            }
            unset($contact);
        }
        if ($this->getViewType() == 'thumb-list') {
            class_exists('waContactPhoneField');
            class_exists('waContactFieldFormatter');
            $phone_formatter = new waContactPhoneTopFormatter();
            $address_formatter = new waContactAddressOneLineFormatter();
            foreach ($contacts as &$contact) {
                if ($contact['phone']) {
                    $contact['phone_format'] = array();
                    foreach ($contact['phone'] as $phone) {
                        $contact['phone_format'][] = $phone_formatter->format(array(
                            'value' => $phone['value'],
                            'ext'   => $phone['ext'],
                        ));
                    }
                }
                if ($contact['address']) {
                    $contact['address_format'] = array();
                    foreach ($contact['address'] as $address) {
                        $adr = $address_formatter->format($address);
                        $contact['address_format'][] = array(
                            'address' => $adr['value'],
                            'ext'     => $adr['ext'],
                        );
                    }
                }
            }
            unset($contact);
        }
        return $contacts;
    }

    protected function getHash()
    {
        return '';
    }

    protected function getOffset()
    {
        $page = $this->getPage();
        $limit = $this->getLimit();
        $offset = $page ? ($limit * ($page - 1)) : 0;
        return $offset;
    }

    protected function getPage()
    {
        $page = (int) $this->getParameter('page');
        return $page >= 0 ? $page : 0;
    }

    protected function getLimit()
    {
        $limit = $this->getParameter('limit');
        return $limit === null ? $this->getConfig()->getContactsPerPage() : (int) $limit;
    }

    /**
     * @return bool if direction for current sort is ascending or descending
     */
    protected function isAsc()
    {
        if ($this->asc !== null) {
            return $this->asc;
        }

        $sort = (array) $this->getRawSort();
        $sort[1] = strtoupper(trim((string) ifset($sort[1])));
        $this->asc = !$sort[1] || $sort[1] == 'ASC' ? true : false;
        return $this->asc;
    }

    protected function countAllContacts()
    {
        $collection = new crmContactsCollection();
        return $collection->count();
    }

    protected function getColumnValue($contact, $column)
    {
        $column_id = $column['id'];
        $is_multi = $column['is_multi'];
        $field = $column['field'];
        $need_to_format = $this->needToFormat($field);
        if ($is_multi) {
            $contact_column_value = array();
            $values = array_values((array)ifset($contact[$column_id]));
            foreach ($values as $value) {
                if (is_array($value) && isset($value['value'])) {
                    $val = $value['value'];
                } else {
                    $val = $value;
                }
                if ($need_to_format) {
                    $val = $this->formatFieldValue($field, $val);
                }
                $contact_column_value[] = $val;
            }
            return $contact_column_value;
        } else {
            $value = ifset($contact[$column_id]);
            $val = is_array($value) && isset($value['value']) ? $value['value'] : $value;
            if ($need_to_format) {
                $val = $this->formatFieldValue($field, $val);
            }
            return $val;
        }
    }

    protected function getSubColumnValue($contact, $column, $sub_column)
    {
        $column_id = $column['id'];
        $sub_column_id = $sub_column['id'];
        $is_multi = $column['is_multi'];
        $field = $sub_column['field'];
        $need_to_format = $this->needToFormat($field);
        if ($is_multi) {
            $values = array_values((array)ifset($contact, $column_id, array()));
            $contact_column_value = array();
            foreach ($values as $value) {
                $val = ifset($value, 'data', $sub_column_id, '');
                if ($need_to_format) {
                    $val = $this->formatFieldValue($field, $val);
                }
                $contact_column_value[] = $val;
            }
            return $contact_column_value;
        } else {
            $val = ifset($contact[$column_id][$sub_column_id]);
            if ($need_to_format) {
                $val = $this->formatFieldValue($field, $val);
            }
            return $val;
        }
    }

    protected function formatFieldValue($field, $value)
    {
        if ($field instanceof waContactDateField) {
            return $this->formatDatetime($value);
        }
        return $field->format($value, 'html');
    }

    protected function formatDatetime($datetime)
    {
        $datetime = (string)$datetime;

        if (!$datetime) {
            return '';
        }

        // check correctness (work for php if even version < 5.3) of datetime string
        if (strtotime($datetime) === false) {
            return '';
        }

        try {
            $datetime_str = waDateTime::format('humandatetime', $datetime, null, 'en_US');
            if (strpos($datetime_str, 'Yesterday') !== false || strpos($datetime_str, 'Today') !== false) {
                $datetime_str = $datetime_str = waDateTime::format('humandatetime', $datetime);
            } else {
                $datetime_str = waDateTime::format('humandate', $datetime);
            }
        } catch (Exception $e) {
            $datetime_str = '';
        }

        return $datetime_str;
    }

    protected function getBirthdayValue($contact, waContactBirthdayField $field)
    {
        return $field->format(array(
            'data' => array(
                'day' => $contact['birth_day'],
                'month' => $contact['birth_month'],
                'year' => $contact['birth_year']
            )
        ), 'html');
    }

    protected function needToFormat(waContactField $field)
    {
        return $field instanceof waContactDateField || $field instanceof waContactPhoneField || ($field instanceof waContactSelectField &&
            !($field instanceof waContactCountryField) &&
            !($field instanceof waContactRegionField));
    }

    private function getViewType()
    {
        if ($this->view_type !== null) {
            return $this->view_type;
        }

        $save_params = $this->getSavedParams();
        $view = ifset($save_params['view']);

        if (!$view) {
            $view = reset($this->available_view_types);
        }

        $this->view_type = $this->getParameter('view', '',waRequest::TYPE_STRING_TRIM);
        if (!$this->view_type) {
            $this->view_type = $view;
        }

        if (!in_array($this->view_type, $this->available_view_types)) {
            $this->view_type = reset($this->available_view_types);
        }

        if ($save_params['view'] !== $this->view_type) {
            $save_params['view'] = $this->view_type;
            $this->saveParams($save_params);
        }

        return $this->view_type;
    }

    /**
     * @return array|string
     */
    private function getRawSort()
    {
        if ($this->raw_sort !== null) {
            return $this->raw_sort;
        }

        $save_params = $this->getSavedParams();

        $default_raw_sort = ifset($save_params['raw_sort']);
        if (!$default_raw_sort) {
            $default_raw_sort = reset($this->available_sorts);
        }

        $sort = $this->getParameter('sort');
        if (!$sort) {
            $sort = $default_raw_sort[0];
        }

        $asc = $this->getParameter('asc');
        if ($asc === null) {
            if ($default_raw_sort[0] === $sort) {
                $asc = $default_raw_sort[1] === 'ASC';
            } elseif (isset($this->available_sorts[$sort])) {
                $asc = $this->available_sorts[$sort][1] === 'ASC';
            } else {
                $asc = true;
            }
        }

        $this->raw_sort = array($sort, $asc = $asc ? 'ASC' : 'DESC');

        if ($save_params['raw_sort'] !== $this->raw_sort) {
            $save_params['raw_sort'] = $this->raw_sort;
            $this->saveParams($save_params);
        }

        return $this->raw_sort;

    }

    private function getSavedParams()
    {
        $csm = new waContactSettingsModel();
        $params = (string) $csm->getOne($this->getUser()->getId(), $this->getAppId(), 'contacts_action_params');
        $params = json_decode($params, true);
        $params = is_array($params) ? $params : array();
        return $params;
    }

    private function saveParams($params)
    {
        $csm = new waContactSettingsModel();
        $csm->set($this->getUser()->getId(), $this->getAppId(), 'contacts_action_params', json_encode($params));
    }

}

