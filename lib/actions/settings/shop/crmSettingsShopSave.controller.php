<?php

class crmSettingsShopSaveController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        foreach ($this->getData() as $url => $params) {
            $source = crmShopSource::factoryByStorefront($url);
            if ($params['checked']) {
                $source->save($params['source']);
            } else {
                $source->delete();
            }
        }
    }

    protected function getData()
    {
        waLocale::loadByDomain('shop');

        $data = array();
        foreach ((array) $this->getParameter('storefront') as $url => $params) {

            $name = $url;

            // NULL - special meanings - manual order storefront
            if ($url == 'NULL') {
                $url = '';
                $name = _wd('shop', 'Manual order');
            }

            $params = (array)$params;
            $params['source'] = (array)ifset($params['source']);
            $params['source']['name'] = $name;
            $params['source']['params'] = (array)ifset($params['source']['params']);
            $params['source']['params']['storefront'] = $url;
            $params['source']['params']['create_deal_trigger'] = (string)ifset($params['create_deal_trigger']);
            $data[$url] = array(
                'source' => $params['source'],
                'checked' => isset($params['checked']),
            );
        }

        return $data;
    }
}
