<?php

class crmContactMergeDuplicatesAction extends crmContactsAction
{
    public function preExecute() {
        if (wa()->whichUI() === '1.3') {
            parent::preExecute();
        }
    }

    public function execute()
    {
        parent::execute();

        $iframe = waRequest::get('iframe', 0, waRequest::TYPE_INT);
        if (!empty($iframe)) {
            $this->setLayout();
        }
        $this->view->assign([
            'iframe' => $iframe
        ]);
    }

    public function afterExecute()
    {
        $this->accessDeniedForNotEditRights();
        $this->view->assign(array(
            'field' => $this->getField(),
            'page' => $this->getPage(),
            'limit' => $this->getLimit(),
            'duplicates_data' => $this->search($this->getField(), $this->getOffset(), $this->getLimit())
        ));
    }

    protected function search($field, $offset = 0, $limit = 30)
    {
        $duplicates = array();
        $groups_count = 0;
        $contacts_count = 0;
        $m = new waModel();
        switch ($field) {
            case 'name':
                $sql = "SELECT name, name value, COUNT(DISTINCT id) count, GROUP_CONCAT(DISTINCT id SEPARATOR ',') ids FROM `wa_contact`
                    GROUP BY name
                    HAVING count > 1
                    ORDER BY name
                    LIMIT {$offset}, {$limit}";
                $duplicates = $m->query($sql)->fetchAll();

                $sql_t = "SELECT COUNT(DISTINCT id) count FROM `wa_contact`
                    GROUP BY name
                    HAVING count > 1";
                $contacts_count = $m->query("SELECT SUM(count) FROM ($sql_t) r")->fetchField();

                $sql = "SELECT COUNT(*) FROM ($sql_t) r";
                $groups_count = $m->query($sql)->fetchField();


                break;
            case 'email':
                $sql = "SELECT ce.email name, ce.email value, COUNT(DISTINCT ce.contact_id) count, GROUP_CONCAT(DISTINCT ce.contact_id SEPARATOR ',') ids FROM `wa_contact_emails` ce
                    JOIN `wa_contact` c ON c.id = ce.contact_id
                    GROUP BY ce.email
                    HAVING count > 1
                    ORDER BY ce.email
                    LIMIT {$offset}, {$limit}";

                $duplicates = $m->query($sql)->fetchAll();

                $sql_t = "SELECT COUNT(DISTINCT ce.contact_id) count FROM `wa_contact_emails` ce
                            JOIN `wa_contact` c ON c.id = ce.contact_id
                            GROUP BY ce.email
                            HAVING count > 1";
                $contacts_count = $m->query("SELECT SUM(count) FROM ($sql_t) r")->fetchField();

                $sql = "SELECT COUNT(*) FROM ($sql_t) r";
                $groups_count = $m->query($sql)->fetchField();
                break;
            case 'phone':
                $sql = "SELECT cd.value name, cd.value value, COUNT(DISTINCT cd.contact_id) count, GROUP_CONCAT(DISTINCT cd.contact_id SEPARATOR ',') ids FROM `wa_contact_data` cd
                    JOIN `wa_contact` c ON c.id = cd.contact_id AND cd.field = 'phone'
                    GROUP BY cd.value
                    HAVING count > 1
                    ORDER BY cd.value
                    LIMIT {$offset}, {$limit}";
                $duplicates = $m->query($sql)->fetchAll();
                $f = new waContactPhoneField('phone', 'Phone');
                foreach ($duplicates as &$d) {
                    $d['name'] = $f->format($d['name'], 'html');
                }
                unset($d);

                $sql_t = "SELECT COUNT(DISTINCT cd.contact_id) count FROM `wa_contact_data` cd
                        JOIN `wa_contact` c ON c.id = cd.contact_id AND cd.field = 'phone'
                        GROUP BY cd.value
                        HAVING count > 1";
                $contacts_count = $m->query("SELECT SUM(count) FROM ($sql_t) r")->fetchField();

                $sql = "SELECT COUNT(*) FROM ($sql_t) r";
                $groups_count = $m->query($sql)->fetchField();
                break;
            default:
                break;
        }

        return array(
            'items' => $duplicates,
            'groups_count' => $groups_count,
            'contacts_count' => $contacts_count
        );
    }

    protected function getPage()
    {
        $page = (int) $this->getParameter('page');
        return $page >= 0 ? $page : 0;
    }

    protected function getOffset()
    {
        $offset = $this->getParameter('offset');
        if ($offset === null) {
            $page = $this->getPage();
            $limit = $this->getLimit();
            $offset = $page ? ($limit * ($page - 1)) : 0;
        }
        return $offset;
    }


    protected function getLimit()
    {
        $limit = $this->getParameter('limit');
        return $limit === null ? crmConfig::ROWS_PER_PAGE : (int) $limit;
    }

    protected function getField()
    {
        return $this->getParameter('field');
    }
}
