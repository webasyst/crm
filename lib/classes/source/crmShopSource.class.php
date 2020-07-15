<?php

class crmShopSource extends crmSource
{
    protected $type = crmSourceModel::TYPE_SHOP;

    /**
     * crmFormSource object(s) factory
     *
     * @param string[]|string $storefront
     * @param array $options
     * @return crmFormSource[]|crmFormSource
     */
    public static function factoryByStorefront($storefront, $options = array())
    {
        if ($storefront === null) {
            $storefront = '';
        }

        $storefronts = waUtils::toStrArray($storefront);
        $items = self::getSourceModel()->getSourceIdsByParam('storefront', $storefronts);

        // need "Manual order" storefront
        $need_manual = in_array('', $storefronts);

        // Id of source with "Manual order" storefront
        $manual_source_id = null;

        $sources = array_fill_keys($storefronts, '');
        foreach ($items as $item) {
            $item['storefront'] = trim($item['storefront']);
            if (!empty($item['storefront'])) {
                $sources[$item['storefront']] = $item['id'];
            } else {
                $manual_source_id = $item['id'];
            }
        }

        // find SHOP source without explicit 'storefront' param
        if ($need_manual && !$manual_source_id) {
            $sql = "SELECT s.id FROM `crm_source` s
                    LEFT JOIN `crm_source_params` sp ON sp.source_id = s.id AND sp.name = 'storefront' 
                    WHERE `type` = :type AND sp.value IS NULL";
            if ($items) {
                $sql .= " AND s.id NOT IN(:ids) ";
            }
            $sql .= " LIMIT 1";

            $manual_source_id = self::getSourceModel()->query($sql, array(
                'type' => crmSourceModel::TYPE_SHOP,
                'ids' => waUtils::getFieldValues($items, 'id')
            ))->fetchField();

        }

        if ($manual_source_id) {
            $sources[''] = $manual_source_id;
        }

        foreach ($sources as $url => &$_source) {
            $_options = $options;
            if (isset($_options[$url])) {
                $_options = $_options[$url];
            }
            $_source = new crmShopSource($_source, $_options);
        }
        unset($_source);

        if (is_array($storefront)) {
            return $sources;
        }

        return $sources[$storefront];
    }
}
