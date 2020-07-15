<?php

class crmContactSaveController extends crmJsonController
{
    protected $params;

    public function __construct($params = array())
    {
        $this->params = (array) $params;
    }

    public function execute()
    {
        $data = $this->getData();

        $this->errors = $this->validate($data);
        if ($this->errors) {
            return;
        }
        $data['company_contact_id'] = (int) ifset($data['company_contact_id']);
        $data['company'] = trim(ifset($data['company']));

        $id = $this->getId();
        $contact = new waContact($id > 0 ? $id : null);
        if ($id > 0) {
            // check access rights
            if (!$this->getCrmRights()->contactEditable($contact)) {
                $this->accessDenied();
            }
            // Make sure not to set company_contact_id on a company
            if (ifset($data, 'is_company', $contact['is_company'])) {
                $data['company'] = ifset($data, 'name', $contact['name']);
                $data['company_contact_id'] = 0;
                $data['is_company'] = 1;
            }
        }

        if (empty($data['is_company'])) {
            if ($data['company_contact_id'] <= 0 && strlen($data['company']) > 0) {
                $company = $this->findCompany($data['company']);
                if (!$company) {
                    $company = $this->createCompany($data['company']);
                }
                if ($company && $company['id'] != $id) {
                    $data['company_contact_id'] = $company['id'];
                    $data['company'] = $company['company'];
                }
            } elseif ($data['company_contact_id'] && !$data['company']) {
                $c = new waContact($data['company_contact_id']);
                $data['company'] = $c->getName();
            }
        }

        $errors = $contact->save($data, true);
        if ($errors) {
            $this->errors = $this->formatErrors($errors);
            return;
        }

        $this->params['contact']['id'] = $contact->getId();

        // For now work only add
        if ($id <= 0) {
            $this->logAction('contact_add', null, $contact->getId());
        }
        if ($id <= 0 && $contact->getId() > 0) {
            $filepath = $this->getGravatar((array) ifset($data['email']));
            if ($filepath) {
                $contact->setPhoto($filepath);
            }
        }
        $this->response = array(
            'contact' => array(
                'id' => $contact->getId()
            )
        );
    }

    public function getExecuteResult()
    {
        return array('errors' => $this->errors, 'response' => $this->response);
    }

    protected function formatErrors($all_errors)
    {
        $result = array();
        $fields = waContactFields::getAll('all');
        foreach ($all_errors as $field_id => $field_errors) {

            $is_multi = false;
            $is_composite = false;

            if (isset($fields[$field_id])) {
                $field = $fields[$field_id];
                $is_multi = $field->isMulti();
                $is_composite = $field instanceof waContactCompositeField;
            }

            if ($is_multi) {
                foreach ($field_errors as $index => $errors) {
                    if ($is_composite) {
                        foreach ($errors as $subfield_id => $subfield_errors) {
                            $error_str = $this->join(', ', $subfield_errors);
                            $result[] = array(
                                'name' => "contact[{$field_id}][{$index}][{$subfield_id}]",
                                'value' => $error_str
                            );
                        }
                    } else {
                        $error_str = $this->join(', ', $errors);
                        $result[] = array(
                            'name' => "contact[{$field_id}][{$index}][value]",
                            'value' => $error_str
                        );
                    }
                }
            } else {
                $error_str = $this->join(', ', $field_errors);
                $result[] = array(
                    'name' => "contact[{$field_id}]",
                    'value' => $error_str
                );
            }
        }

        return $result;
    }

    protected function join($sep, $values)
    {
        $values = waUtils::toStrArray($values);
        return join($sep, $values);
    }

    protected function validate($data)
    {
        $errors = array();

        foreach ((array)ifset($data['email']) as $index => $email) {
            $email_val = (string)ifset($email['value']);
            $email_validator = new waEmailValidator();
            if ($email_val && !$email_validator->isValid($email_val)) {
                $errors[] = array(
                    'name' => 'contact[email]['.$index.'][value]',
                    'value' => join(', ', $email_validator->getErrors())
                );
            }
        }

        $is_company = ifset($data['is_company']) ? 1 : 0;
        if (!$is_company) {
            if (empty($data['firstname']) && empty($data['middlename']) && empty($data['lastname']) && empty($data['name'])) {
                $e = _w('At least one of name field must be filled');
                $errors[] = array('name' => 'contact[name]', 'value' => $e);
                $errors[] = array('name' => 'contact[firstname]', 'value' => $e);
            }
        } else {
            if (empty($data['company'])) {
                $e = _w('Company name is must be filled');
                $errors[] = array('name' => 'contact[company]', 'value' => $e);
            }
        }

        return $errors;
    }

    protected function getData()
    {
        $data = (array)$this->getParameter('contact');
        if ($this->getId() <= 0) {
            $data['create_method'] = 'add';
            $data['crm_user_id'] = $this->autoResponsible();
        }
        return $data;
    }

    protected function getId()
    {
        $data = (array) $this->getParameter('contact');
        return (int) ifset($data['id']);
    }

    protected function getGravatar($emails)
    {
        $image = null;
        foreach ($emails as $email) {
            $value = trim((string) ifset($email['value']));
            $res = $this->downloadGravatar($value);
            if ($res && $res['image']) {
                $image = $res['image'];
                break;
            }
        }
        if (!$image) {
            return null;
        }
        $headers = (array) (!empty($http_response_header) ? $http_response_header : null);

        $ext = 'jpeg';
        foreach ($headers as $header) {
            $header = strtolower($header);
            if (substr($header, 0, 13) === 'content-type:') {
                $content_type = substr(trim($header), 13);
                $ext = str_replace('image/', '', $content_type);
                break;
            }
        }
        $filepath = tempnam(sys_get_temp_dir(), __METHOD__) . '.' . $ext;
        file_put_contents($filepath, $image);

        return $filepath;
    }

    protected function downloadGravatar($email)
    {
        $net = new waNet();
        $url = sprintf('http://www.gravatar.com/avatar/%s?d=404&size=144', md5(strtolower($email)));
        try {
            $file = $net->query($url);
            $headers = $net->getResponseHeader();
            return array(
                'image' => $file,
                'headers' => $headers
            );
        } catch (waException $ex){
        }
        return null;
    }

    protected function findCompany($name)
    {
        $m = new waContactModel();
        $count = $m->countByField(array(
            'company' => $name,
            'is_company' => 1
        ));
        if ($count == 1) {
            return $m->getByField(array(
                'company' => $name,
                'is_company' => 1
            ));
        }
        return null;
    }

    protected function createCompany($name)
    {
        $company = new waContact();
        $company->save(array(
            'company'       => $name,
            'is_company'    => 1,
            'create_method' => 'add',
            'crm_user_id'   => $this->autoResponsible(),
        ));
        return $company;
    }

    public function getParam($key)
    {
        return ifset($this->params[$key]);
    }

    /**
     * Does the current user have the option to automatically assign responsibility for the contacts / companies that are being created?
     * If so, the method will return the current user ID
     */
    protected function autoResponsible()
    {
        if (!wa()->getUser()->getSettings('crm', 'contact_create_not_responsible')) {
            return wa()->getUser()->getId();
        }
        return null;
    }

}
