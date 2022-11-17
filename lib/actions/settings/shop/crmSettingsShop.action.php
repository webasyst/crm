<?php

class crmSettingsShopAction extends crmSettingsViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $this->checkShopExists();

        $this->view->assign(array(
            'storefronts' => $this->getStorefronts(),
            'supported'   => crmConfig::isShopSupported(),
        ));
    }

    protected function checkShopExists()
    {
        if (!wa()->appExists('shop')) {
            $this->notFound();
        }
    }

    protected function getStorefronts()
    {
        wa('shop');
        if (!method_exists('shopHelper', 'getStorefronts')) {
            return null;
        }

        waLocale::loadByDomain('shop');

        $storefronts = array(
            array(
                'url' => '',
                'url_decoded' => '',
                'name' => _wd('shop', 'Manual order'),
            )
        );

        $storefronts = array_merge($storefronts, array_slice(shopHelper::getStorefronts(true), 0, 500));

        $urls = waUtils::getFieldValues($storefronts, 'url');
        $sources = crmShopSource::factoryByStorefront($urls);

        foreach ($storefronts as &$storefront) {

            if (!isset($storefront['name'])) {
                if (isset($storefront['url_decoded'])) {
                    $storefront['name'] = $storefront['url_decoded'];
                } else {
                    $storefront['name'] = $storefront['url'];
                }
            }

            $url = $storefront['url'];
            $source = $sources[$url];
            $storefront['source'] = $source;

            $storefront['checked'] = false;
            if ($source->getId() > 0 && $source->isEnabled()) {
                $storefront['checked'] = true;
            }
            $storefront['create_deal_trigger'] = $source->getParam('create_deal_trigger');
            $storefront['deal_block'] = $this->getCreateDealBlock($source, $url)->render();

        }
        unset($storefront);

        return $storefronts;
    }

    protected function getCreateDealBlock(crmSource $source, $url)
    {
        $id = 'create_deal_with_responsible';
        $url_str = $url ? $url : 'NULL';
        return new crmSourceSettingsCreateDealViewBlock($id, $source, array(
            'namespace' => "storefront[{$url_str}][source]"
        ));
    }
}
