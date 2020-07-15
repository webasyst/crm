<?php

wa('shop');

class crmContactsSearchShopProductValues
{
    protected $options = array();
    protected $limit = 10;

    public function __construct($options = array()) {
        $this->options = $options;
    }

    public function getValues($options)
    {
        if (!empty($options['autocomplete']['term'])) {
            $term = $options['autocomplete']['term'];
            $m = new waModel();
            $term = $m->escape($term, 'like');
            $products = $m->query("SELECT DISTINCT p.id AS value, p.name FROM `shop_product` p
                    JOIN `shop_order_items` oi ON p.id = oi.product_id AND oi.type = 'product'
                WHERE p.name LIKE '{$term}%' LIMIT {$this->limit}")->fetchAll();
            $count = count($products);
            if ($count < $this->limit) {
                $limit = $this->limit - $count;

                $like = array();
                foreach (explode(' ', $term) as $t) {
                    $t = trim($t);
                    if (strlen($t) >= 3) {
                        $like[] = "p.name LIKE '{$t}%' OR ps.name LIKE '%{$t}%'";
                    }
                }
                if ($like) {
                    $like = implode(' OR ', $like);
                } else {
                    $like = '1';
                }

                $skus = $m->query("SELECT DISTINCT ps.id, CONCAT(p.name, ' (', ps.name, ')') AS name FROM `shop_product` p
                        JOIN `shop_product_skus` ps ON ps.product_id = p.id
                        JOIN `shop_order_items` oi ON ps.id = oi.sku_id AND oi.type = 'product'
                    WHERE {$like} LIMIT {$limit}")->fetchAll();
                foreach ($skus as $sku) {
                    $sku['value'] = 'sku_id:' . $sku['id'];
                    unset($sku['id']);
                    $products[] = $sku;
                }
            }
            return $products;

        }
        return array();
    }

    public function where($val_item)
    {
        $val_item = $this->parseValItem($val_item);
        $m = new waModel();
        $like_val = $m->escape($val_item['val'], 'like');
        if ($val_item['type'] === 'sku') {
            if ($val_item['op'] === '=') {
                return ":table.sku_id = '{$val_item['val']}'";
            } else if ($val_item['op'] === '*=') {
                return ":table.name LIKE '%{$like_val}%'";
            }
        } else if ($val_item['type'] === 'product') {
            if ($val_item['op'] === '=') {
                return ":table.product_id = '{$val_item['val']}'";
            } else if ($val_item['op'] === '*=') {
                return ":table.name LIKE '%{$like_val}%'";
            }
        } else {
            return ":table.name LIKE '%{$like_val}%'";
        }
    }

    public function join()
    {
        return array(
            'table' => 'shop_order_items',
            'on' => ":tbl_order.id = :table.order_id AND :table.type='product'"
        );
    }

    protected function getConds()
    {
        return ifset($this->options['conds'], array());
    }

    protected function getValItem()
    {
        $conds = $this->getConds();
        if (isset($conds['shop']['purchased_product']['product'])) {
            $val_item = $conds['shop']['purchased_product']['product'];
            $val_item['val'] = ifset($val_item['val'], '');
            $val_item['op'] = ifset($val_item['op'], '=');
            return $val_item;
        }
        return array(
            'val' => '', 'op' => '='
        );
    }

    protected function parseValItem($val_item)
    {
        $val = ifset($val_item['val'], '');
        if (substr($val, 0, 7) === 'sku_id:') {
            $val_item['type'] = 'sku';
            $val_item['val'] = (int) substr($val, 7);
        } else if (is_numeric($val)) {
            $val_item['type'] = 'product';
            $val_item['val'] = (int) $val;
        } else {
            $val_item['type'] = 'sku|product';
        }
        return $val_item;
    }

    public function extra($val = '')
    {
        $val_item = $this->parseValItem(array('val' => $val, 'op' => '='));
        $val = $val_item['val'];
        if ($val_item['type'] === 'sku') {
            $sku_id = $val;
            $psm = new shopProductSkusModel();
            $sku = $psm->getById($sku_id);
            if (!$sku) {
                return array('name' => $val);
            }
            $pm = new shopProductModel();
            $product = $pm->getById($sku['product_id']);
            if ($product) {
                return array(
                    'name' => $sku['name']
                        ? htmlspecialchars($product['name']) . ' (' . htmlspecialchars($sku['name']) . ')'
                        : htmlspecialchars($product['name'])
                );
            }
            return array('name' => htmlspecialchars($sku['name']));
        } else if ($val_item['type'] === 'product') {
            $product_id = (int) $val;
            $pm = new shopProductModel();
            $product = $pm->getById($product_id);
            if ($product) {
                return array('name' => $product['name']);
            }
        } else {
            return array('name' => $val);
        }
    }
}
