<?php

class crmSettingsSourceTestConnectionController extends crmJsonController
{
    public function execute()
    {
        $this->accessDeniedForNotAdmin();
        wa()->getStorage()->close();

        $source = $this->getSource();
        $errors = $source->testConnection();
        if ($errors) {
            $this->setErrors($errors);
        } elseif ($source->getId() > 0) {
            $source->commit();
        }
    }

    protected function setErrors($errors)
    {
        foreach ($errors as $error_key => $error_text) {
            if ($error_key) {
                $this->errors['params'][$error_key] = $error_text;
            } else {
                $this->errors[$error_key] = $error_text;
            }
        }
    }

    /**
     * @return crmSource
     */
    protected function getSource()
    {
        $params = $this->getRequest()->post();
        $id = ifset($params['id']);
        unset($params['id']);

        $source = crmSource::factory($id);
        if ($id > 0 && isset($params['password']) && strlen($params['password']) <= 0) {
            unset($params['password']);
        }
        
        $source->setConnectionParams($params);
        return $source;
    }
}
