<?php

class crmAutocompleteController extends waController
{
    protected $term;
    protected $limit = 10;
    protected $type;
    protected $add_new = false;
    protected $emailcomplete = false;
    protected $phonecomplete = false;

    public function execute()
    {
        $this->add_new = $this->getRequest()->request('add_new');
        $this->emailcomplete = $this->getRequest()->request('emailcomplete');
        $this->phonecomplete = $this->getRequest()->request('phonecomplete');
        $data = $this->getContacts($this->getTerm());

        $fields = $this->getJoinFields();
        if ($fields) {
            $data = $this->join($data, $fields);
        }
        die(json_encode($data));
    }

    public function getContacts($q)
    {
        $type = $this->getType();

        if (strlen($q) <= 0 && $type !== 'user') {
            return array();
        }

        $m = new waModel();

        // The plan is: try queries one by one (starting with fast ones),
        // until we find 5 rows total.
        $sqls = array();

        $fields = 'c.id, c.name, c.login, c.firstname, c.middlename, c.lastname, c.photo';

        $condition = '';
        if ($type === 'person') {
            $condition = ' AND c.is_company <= 0';
        } else {
            if ($type === 'company') {
                $condition = ' AND c.is_company > 0';
            } elseif ($type === 'user') {
                $condition = ' AND c.is_user = 1';

                $crm = new waContactRightsModel();
                $users = $crm->getUsers('crm');

                $condition .= " AND c.id IN ('".join("','", $m->escape($users))."')";
            }
        }
        if ($type === 'person' || !$type) {
            $r = new crmRights();
            $vault_ids = $r->getAvailableVaultIds();
            if ($vault_ids) {
                $condition .= " AND c.crm_vault_id IN('".join("','", $m->escape($vault_ids))."')";
            }
            $fields .= ', c.company, c.jobtitle';
        }

        if (strlen($q) <= 0 && $type === 'user') {
            // My self
            $sqls[] = "SELECT $fields 
                   FROM wa_contact AS c
                   WHERE c.id = ".(int)wa()->getUser()->getId();
            // All users
            $sqls[] = "SELECT $fields
                   FROM wa_contact AS c
                   WHERE 1 $condition AND c.id <> ".(int)wa()->getUser()->getId()."
                   LIMIT {LIMIT}";
        } else {
            // Name starts with requested string
            $sqls[] = "SELECT $fields
                   FROM wa_contact AS c
                   WHERE c.name LIKE '".$m->escape($q, 'like')."%' $condition
                   LIMIT {LIMIT}";
        }

        if (strlen($q) > 1) {
            // Email starts with requested string
            $sqls[] = "SELECT $fields, e.email
                       FROM wa_contact AS c
                           JOIN wa_contact_emails AS e
                               ON e.contact_id=c.id
                       WHERE e.email LIKE '".$m->escape($q, 'like')."%' $condition
                       LIMIT {LIMIT}";

            // Phone contains requested string
            if (preg_match('~^[wp0-9\-\+\#\*\(\)\. ]+$~', $q)) {
                $dq = preg_replace('/[^\d]+/', '', $q);
                $sqls[] = "SELECT $fields, d.value as phone
                           FROM wa_contact AS c
                               JOIN wa_contact_data AS d
                                   ON d.contact_id=c.id AND d.field='phone'
                           WHERE d.value LIKE '%".$m->escape($dq, 'like')."%' $condition
                           LIMIT {LIMIT}";
            }

            // Name contains requested string
            $name_ar = preg_split('/\s+/', $q);
            if (count($name_ar) == 2) {
                $name_condition =
                    "((c.firstname LIKE '%".$m->escape($name_ar[0], 'like')."%' AND c.lastname LIKE '%".$m->escape($name_ar[1], 'like')."%')
                    OR (c.firstname LIKE '%".$m->escape($name_ar[1], 'like')."%' AND c.lastname LIKE '%".$m->escape($name_ar[0], 'like')."%'))";
            } else {
                $name_condition = "c.name LIKE '_%".$m->escape($q, 'like')."%'";
            }
            $sqls[] = "SELECT $fields
                   FROM wa_contact AS c
                   WHERE $name_condition $condition
                   LIMIT {LIMIT}";

            // Email contains requested string
            $sqls[] = "SELECT $fields, e.email
                       FROM wa_contact AS c
                           JOIN wa_contact_emails AS e
                               ON e.contact_id=c.id
                       WHERE e.email LIKE '_%".$m->escape($q, 'like')."%' $condition
                       LIMIT {LIMIT}";

            if (!$type) {
                // Email contains requested string
                $sqls[] = "SELECT $fields, t.name tag
                       FROM wa_contact AS c
                           JOIN crm_contact_tags AS ct
                               ON ct.contact_id=c.id
                           JOIN crm_tag AS t
                               ON t.id=ct.tag_id
                       WHERE t.name LIKE '%".$m->escape($q, 'like')."%' $condition
                       LIMIT {LIMIT}";
            }
        }

        if (is_numeric($q) && $q > 0) {
            $sqls[] = "SELECT $fields
                   FROM wa_contact AS c
                   WHERE c.id LIKE '$q%'
                   LIMIT {LIMIT}";
        }

        $result = array();
        $term_safe = $this->escapeString($q);
        $deal = $this->getDeal();

        $funnel_info = $this->getFunnelInfo();
        $funnel = $funnel_info['funnel'];
        $funnel_admin_access_only = $funnel_info['admin_access_only'];

        $responsible = $this->getResponsible();
        foreach ($sqls as $sql) {
            if (count($result) >= $this->limit) {
                break;
            }
            foreach ($m->query(str_replace('{LIMIT}', $this->limit, $sql)) as $c) {
                if (empty($result[$c['id']])) {
                    if (!empty($c['firstname']) || !empty($c['middlename']) || !empty($c['lastname'])) {
                        $c['name'] = waContactNameField::formatName($c);
                    }

                    $name = $this->prepare($c['name'], $term_safe);
                    $email = $this->prepare(ifset($c['email'], ''), $term_safe);
                    $phone = $this->prepare(ifset($c['phone'], ''), $term_safe);
                    $tag = $this->prepare(ifset($c['tag'], ''), $term_safe);
                    $company = $this->prepare(ifset($c['company'], ''), $term_safe);
                    $jobtitle = $this->prepare(ifset($c['jobtitle'], ''), $term_safe);
                    if ($company || $jobtitle) {
                        if ($company && $jobtitle) {
                            $company = $jobtitle.' '._ws('@').' '.$company;
                        } elseif ($jobtitle) {
                            $company = $jobtitle;
                        }
                        $company = '<span class="small">'.$company.'</span>';
                    }
                    $phone && $phone = '<i class="icon16 phone"></i>'.$phone;
                    $email && $email = '<i class="icon16 email"></i>'.$email;
                    $tag && $tag = '<i class="icon16 tags"></i>'.$tag;
                    $result[$c['id']] = array(
                        'id'        => $c['id'],
                        'name'      => $c['name'],
                        'login'     => $c['login'],
                        'photo_url' => waContact::getPhotoUrl($c['id'], $c['photo'], 96),
                        'label'     => implode(' ', array_filter(array($name, $company, $email, $phone, $tag))),
                        'criteria'  => array(
                            'email' => ifset($c['email'])
                        )
                    );

                    if ($deal) {
                        $result[$c['id']]['rights'] = $this->getDealRights($deal, $c);
                    }

                    if ($funnel_admin_access_only) {
                        $result[$c['id']]['rights'] = $this->isAdmin($c);
                    } elseif ($funnel) {
                        $result[$c['id']]['rights'] = $this->getFunnelRights($funnel, $c);
                    }

                    if ($responsible) {
                        $result[$c['id']]['rights'] = $this->getResponsibleRights($c['id']);
                    }
                    if (count($result) >= $this->limit) {
                        break 2;
                    }
                }
            }
        }

        foreach ($result as &$c) {
            $contact = new waContact($c['id']);
            $c['label'] = "<i class='icon16 userpic20' style='background-image: url(\"".$contact->getPhoto(20)."\");'></i>".$c['label'];
            $c['link'] = 'contact/'.$c['id'].'/';
        }
        unset($c);

        if ($this->add_new) {
            $data = $this->extractData($term_safe);
            $result[] = array(
                'id'        => -1,
                'name'      => $term_safe,
                'login'     => null,
                'photo_url' => null,
                'label'     => '<i class="icon16 add"></i>'._w('Add new contact').': '.$term_safe,
                'data'      => $data,
                'criteria'  => array(
                    'email' => null
                )
            );
        }

        if ($this->emailcomplete && $result) {
            $ids = array();
            foreach ($result as $contacts)
            {
                $ids[] = $contacts['id'];
            }
            unset($contacts);

            $collection = new crmContactsCollection('id/'.implode(',', $ids));
            $collection = $collection->getContacts("name,email");
            foreach ($collection as $contact => $field) {
                if (!is_array($field['email']) || empty($field['email'])) {
                    unset($result[$contact]);
                } else {
                    $result[$contact]['name'] = htmlspecialchars($field['name']);
                    $result[$contact]['email'] = htmlspecialchars($field['email']['0']);
                }
            }
            unset($collection);

            return $result;
        }

        if ($this->phonecomplete && $result) {
            $ids = array();
            foreach ($result as $contacts)
            {
                $ids[] = $contacts['id'];
            }
            unset($contacts);

            $collection = new crmContactsCollection('id/'.implode(',', $ids));
            $collection = $collection->getContacts("name,phone");
            foreach ($collection as $contact => $field) {
                if (!is_array($field['phone'])) {
                    unset($result[$contact]);
                } else {
                    $result[$contact]['name'] = htmlspecialchars($field['name']);
                    $result[$contact]['phone'] = $field['phone']['0']['value'];
                }
            }
            unset($collection);

            return $result;
        }

        return array_values($result);
    }

    protected function extractData($string)
    {
        $email = $this->extractEmail($string);
        $phone = $this->extractPhone($string);
        return array(
            'id'         => '',
            'middlename' => '',
            'firstname'  => ($email || $phone) ? null : $string,
            'lastname'   => '',
            'company'    => '',
            'is_company' => '',
            'jobtitle'   => '',
            'email'      => array($email),
            'phone'      => array($phone),
        );
    }

    protected function extractEmail($string)
    {
        return strpos($string, '@') !== false
            ? $string
            : null;
    }

    protected function extractPhone($string)
    {
        $is_numeric = is_numeric(
            preg_replace('/[-\+\(\)\s]+/', '', $string)
        );
        return $is_numeric
            ? $string
            : null;
    }

    protected function prepare($str, $term_safe, $escape = true)
    {
        if (strlen($term_safe) <= 0) {
            return $escape ? $this->escapeString($str) : $str;
        }
        $pattern = '~('.preg_quote($term_safe, '~').')~ui';
        $template = '<span class="bold highlighted">\1</span>';
        $str = $escape ? $this->escapeString($str) : $str;
        return preg_replace($pattern, $template, $str);
    }

    protected function escapeString($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'utf-8');
    }

    /**
     * @return string
     */
    protected function getTerm()
    {
        return $this->term !== null ? $this->term : ($this->term = trim((string)$this->getRequest()->request('term')));
    }

    protected function getType()
    {
        if ($this->type !== null) {
            return $this->type;
        }

        $this->type = '';
        $type = $this->getRequest()->request('type');
        $types = array('user', 'company', 'person');
        if (in_array($type, $types)) {
            $this->type = $type;
        }

        return $this->type;
    }

    private function getDeal()
    {
        if ($deal_id = $this->getRequest()->request('deal_id')) {
            $dm = new crmDealModel();
            return $dm->getById($deal_id);
        }
        return array();
    }

    private function getDealRights($deal, $contact)
    {
        $r = new crmRights(array(
            'contact' => $contact['id']
        ));
        return $r->deal($deal);
    }

    private function getFunnelInfo()
    {
        $funnel_id = $this->getRequest()->request('funnel_id');
        if ($funnel_id !== null) {
            $fm = new crmFunnelModel();
            $funnel = $fm->getById($funnel_id);
            if (!$funnel) {
                return array(
                    'funnel' => array(),
                    'admin_access_only' => true
                );
            } else {
                return array(
                    'funnel' => $funnel,
                    'admin_access_only' => false
                );
            }
        }
        return array(
            'funnel' => array(),
            'admin_access_only' => true
        );
    }

    private function getFunnelRights($funnel, $contact)
    {
        $r = new crmRights(array(
            'contact' => $contact['id']
        ));
        return $r->funnel($funnel);
    }

    private function isAdmin($contact) {
        $r = new crmRights(array(
            'contact' => $contact['id']
        ));
        return $r->isAdmin();
    }

    // Contact for assignment of responsibility
    private function getResponsible()
    {
        if ($this->getRequest()->request('contact_id')) {
            $contact_id = $this->getRequest()->request('contact_id');
            return $contact_id;
        }
        return null;
    }

    // Check if the user can be responsible for the selected contact
    private function getResponsibleRights($responsible_id)
    {
        $contact = new crmContact($this->getRequest()->request('contact_id'));
        $isIncceptable = $contact->isResponsibleUserIncceptable($responsible_id);
        if (!$isIncceptable) { // If there are rights
            return 1; // We will return one
        } else {
            return null;
        }
    }

    protected function getJoinFields()
    {
        $join = $this->getRequest()->request('join', '', waRequest::TYPE_STRING_TRIM);
        if (!$join) {
            return '';
        }
        $fields = array();
        foreach (explode(',', $join) as $field) {
            $field = trim($field);
            if (strlen($field) > 0) {
                $fields[] = $field;
            }
        }
        $fields = array_unique($fields);
        if (!$fields) {
            return '';
        }
        return join(',', $fields);
    }

    protected function join($data, $fields)
    {
        if (!$data) {
            return $data;
        }
        $ids = waUtils::getFieldValues($data, 'id');
        $hash = 'id/'.join(',', $ids);
        $collection = new crmContactsCollection($hash);
        $contacts = $collection->getContacts($fields, 0, count($ids));
        foreach ($data as &$item) {
            $item['data'] = array_merge(
                (array)ifset($item['data'], array()),
                (array)ifset($contacts[$item['id']], array())
            );
        }
        unset($item);
        return $data;
    }
}
