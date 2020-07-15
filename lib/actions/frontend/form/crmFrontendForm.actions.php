<?php

class crmFrontendFormActions extends waController
{
    protected $action;
    protected $response = array();
    protected $errors = array();
    protected $assignments = array();

    /**
     * @var array
     */
    protected $params;

    public function __construct($params = array())
    {
        $this->params = $params;
    }

    protected function execute($action)
    {
        $method = $action.'Action';
        if (method_exists($this, $method)) {
            $this->$method();
        }else{
            throw new waException(sprintf("Invalid action or missed method at %s for action %s",get_class($this),$action));
        }
    }

    public function run($params = null)
    {
        $action = $params;
        if (!$action) {
            $action = 'default';
        }
        $this->action = $action;
        $this->execute($this->action);

        if (!empty($this->params['return'])) {
            return array(
                'response' => $this->response,
                'errors' => $this->errors
            );
        }

        if ($this->action == $action) {
            if (waRequest::isXMLHttpRequest()) {
                $this->getResponse()->addHeader('Content-type', 'application/json');
            }
            $this->getResponse()->sendHeaders();

            $response = array(
                'assignments' => $this->assignments
            );

            if (!$this->errors) {
                $response['status'] = 'ok';
                $response['data'] = $this->response;
            } else {
                $response['status'] = 'fail';
                $response['errors'] = $this->errors;
            }

            echo json_encode($response);
        }
    }

    public function submitAction()
    {
        $data = $this->getRequest()->post('crm_form');
        $data['!deal_attachments'] = $this->getAttachments();

        $id = (int)ifset($data['id']);
        unset($data['id']);

        $processor = new crmFormProcessor();
        $response = $processor->process($id, $data);
        if (!$response) {
            $this->notFound(_w('Form not found'));
        }
        if (!empty($response['errors'])) {
            $this->errors = $response['errors'];
            if (!empty($response['captcha_hash'])) {
                $this->assignments = array(
                    'captcha_hash' => $response['captcha_hash']
                );
            }
            return;
        }
        $this->response = $response;
    }

    public function confirmEmailAction()
    {
        $hash = wa()->getRequest()->param('hash');

        $processor = new crmFormProcessor();
        $response = $processor->processConfirmEmail($hash);
        if (!$response) {
            $this->notFound(_w('Confirmation failed'));
        } elseif (!empty($response['errors'])) {
            throw new waException($this->errorsToString($response['errors']), 500);
        }
        $this->response = $response;
    }

    /**
     * @return waRequestFile[]
     */
    protected function getAttachments()
    {
        $files = array();
        foreach (waRequest::file('crm_form_deal_attachment') as $file) {
            if ($file->error_code === UPLOAD_ERR_OK) {
                $files[] = $file;
            }
        }
        return $files;

    }

    protected function notFound($msg = null)
    {
        throw new waException($msg ? $msg : _w('Page not found'), 404);
    }

    protected function errorsToString($errors)
    {
        $msg = array();
        foreach ($errors as $error) {
            if (is_array($error)) {
                $msg[] = $this->errorsToString($error);
            } else {
                $msg[] = $error;
            }
        }
        return join("\n", $msg);
    }
}
