<?php

class crmFrontendConfirmEmailAction extends crmFrontendViewAction
{
    public function execute()
    {
        $hash = $this->getHash();
        $tm = new crmTempModel();
        $temp = $tm->getByHash($hash);

        if (!$temp) {
            $result = array(
                'text' => _w('This link is no longer valid')
            );
            //$this->notFound();
        } else {
            if (isset($temp['data']['form_id'])) {
                $result = $this->executeForm($hash);
            } else {
                $result = $this->executeEmailSource($hash);
            }
        }
        $this->setTemplate('templates/actions/Frontend.html');
        $this->view->assign('content_template', 'templates/actions/frontend/FrontendConfirmEmail.html');
        $this->view->assign($result);
    }

    protected function executeForm($hash)
    {
        $result = $this->redirectToFormConfirmation($hash);
        if (isset($result['redirect_url'])) {
            $this->redirect($result['redirect_url']);
            return array();
        }
        return $result;
    }

    protected function executeEmailSource($hash)
    {
        $result = $this->processSourceEmailAntiSpam($hash);
        if (isset($result['redirect_url'])) {
            $this->redirect($result['redirect_url']);
            return array();
        }
        return $result;
    }

    protected function getHash()
    {
        return wa()->getRequest()->param('hash');
    }

    protected function redirectToFormConfirmation()
    {
        $controller = new crmFrontendFormActions(array('return' => true));
        $result = $controller->run('confirmEmail');
        return $result['response'];
    }

    /**
     * @param string $hash
     * @return array
     */
    protected function processSourceEmailAntiSpam($hash)
    {
        $cst = new crmTempModel();
        $data = $cst->getByHash($hash);
        if (!$data) {
            $this->notFound();
        }

        $cst->deleteByHash($hash);

        /**
         * @var crmEmailSource $source
         */
        $source = crmEmailSource::factory((int)ifset($data['data']['source_id']));
        if (!($source instanceof crmEmailSource)) {
            $this->notFound();
        }

        $worker = crmEmailSourceWorker::factory($source);
        if (!($worker instanceof crmEmailSourceWorker)) {
            $this->notFound();
        }

        $result = $worker->doProcessAntiSpam(array(
            'mail' => (string)ifset($data['data']['mail'])
        ));

        if (!$result) {
            $this->notFound();
        }

        $url = null;
        $after_antispam_confirm = $source->getParam('after_antispam_confirm');
        if ($after_antispam_confirm === 'redirect') {
            $redirect_url = (string)$source->getParam('after_antispam_confirm_url');
            if (strlen($redirect_url) > 0) {
                $url = $redirect_url;
            }
        }

        if ($url) {
            $result['redirect_url'] = $url;
        } else {
            $result['text'] = (string)$source->getParam('after_antispam_confirm_text');
        }

        return $result;
    }

    protected function getRedirectUrlAfterConfirm(crmSource $source)
    {
        $url = wa()->getUrl(true);
        if ($source->getId() <= 0) {
            return $url;
        }

        $params = $source->getParams();

        if (empty($params['after_antispam_confirm']) || $params['after_antispam_confirm'] === 'customer_portal') {
            $url = wa()->getRouteUrl('site/my/') . '?emailconfirmed=1';
        } else if ($params['after_antispam_confirm'] === 'redirect') {
            $url = $params['after_antispam_confirm_url'];
        }

        return $url;
    }
}
