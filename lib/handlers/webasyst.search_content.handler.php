<?php

class crmWebasystSearch_contentHandler extends waEventHandler
{
    protected $m;
    public function execute(&$params)
    {
        $links_by_handle = [];
        foreach ($params['links'] as &$link) {
            if (!empty($link['result']) || !wa()->appExists($link['settlement']['app'])) {
                continue; // already handled by another app or plugin
            }
            $links_by_handle[$link['handle']][] = &$link;
        }
        unset($link);

        $this->m = new waModel();
        if (!empty($links_by_handle['blog/frontend/post'])) {
            try {
                $this->processBlogPost($links_by_handle['blog/frontend/post']);
            } catch (Throwable $e) {
            }
        }
        if (!empty($links_by_handle['hub/frontend/topic'])) {
            try {
                $this->processHubTopic($links_by_handle['hub/frontend/topic']);
            } catch (Throwable $e) {
            }
        }
        if (!empty($links_by_handle['site/frontend/'])) {
            try {
                $this->processSitePage($links_by_handle['site/frontend/']);
            } catch (Throwable $e) {
            }
        }
        if (!empty($links_by_handle['shop/frontend/page'])) {
            try {
                $this->processShopPage($links_by_handle['shop/frontend/page']);
            } catch (Throwable $e) {
            }
        }
        if (!empty($links_by_handle['shop/frontend/product'])) {
            try {
                $this->processShopProduct($links_by_handle['shop/frontend/product']);
            } catch (Throwable $e) {
            }
        }
    }

    public function processBlogPost(&$links) {

        $urls = [];
        $links_by_url = [];
        foreach ($links as &$link) {
            $post_url = ifset($link['url_params'], 'post_url', null);
            $blog_url = ifset($link['url_params'], 'blog_url', '');
            if ($post_url) {
                $urls[] = $post_url;
                $links_by_url[$blog_url][$post_url] =& $link;
            }
        }
        unset($link);
        if (!$urls) {
            return;
        }

        $rows = $this->m->query("
            SELECT p.`title`, p.`text`, p.`url`, b.`url` AS blog_url 
            FROM `blog_post` AS p
                JOIN `blog_blog` AS b
                    ON b.id=p.blog_id
            WHERE p.`status`='published' 
                AND p.`url` IN (?)
        ", [$urls]);

        foreach ($rows as $row) {
            if (isset($links_by_url[$row['blog_url']][$row['url']])) {
                $link =& $links_by_url[$row['blog_url']][$row['url']];
            } else if (isset($links_by_url[''][$row['url']])) {
                $link =& $links_by_url[''][$row['url']];
            } else {
                continue; // ignore duplicate post found by URL that is in the wrong blog
            }
            
            $link['result'] = "<h1>".$row['title']."</h1>\n".$row['text'];
        }
    }

    public function processHubTopic(&$links) {
        $links_by_topic_id = [];
        foreach ($links as &$link) {
            $topic_id = ifset($link['url_params'], 'id', null);
            if ($topic_id) {
                $links_by_topic_id[$topic_id] =& $link;
            }
        }
        unset($link);
        if (!$links_by_topic_id) {
            return;
        }

        $rows = $this->m->query("
            SELECT `id`, `content`, title
            FROM hub_topic AS t
            WHERE t.id IN (?)
        ", [array_keys($links_by_topic_id)]);

        try {
            wa('hub');
            $comment_model = new hubCommentModel();
        } catch (waException $e) {
        }

        foreach ($rows as $topic) {
            $result = [
                '<h1>'.((string)$topic['title']).'</h1>',
                (string) $topic['content'],
            ];
            try {
                if ($comment_model) {
                    $comments = $comment_model->getFullTree($topic_id, 'id,parent_id,datetime,ip,text', 'datetime', true);
                    $comments = join("\n\n", array_column($comments, 'text'));
                    $comments = mb_substr($comments, 0, 3500);
                    $result[] = "<h2>Комментарии к статье</h2>\n\n".$comments;
                }
            } catch (waException $e) {
            }
            $links_by_topic_id[$topic['id']]['result'] = join("\n\n", $result);
        }
    }

    public static function canonicDomain($d) {
        return str_replace('www.', '', $d);
    }

    public function processSitePage(&$links) {
        $urls = [];
        $domains = [];
        $link_by_domain_url = [];
        foreach ($links as &$link) {
            $page_url = ifset($link['url_params'], 'url', null);
            $domain = ifset($link['settlement'], '_domain', null);
            if ($page_url !== null && $domain) {
                $domain = self::canonicDomain($domain);
                $link_by_domain_url[$domain][$page_url] =& $link;
                $domains['www.'.$domain] = true;
                $domains[$domain] = true;
                $urls[$page_url] = true;
            }
        }
        unset($link);
        if (!$link_by_domain_url) {
            return;
        }
        $rows = $this->m->query("
                SELECT p.full_url, d.name AS domain_name, p.content
                FROM site_page AS p
                    JOIN site_domain AS d
                        ON p.domain_id=d.id
                WHERE p.full_url IN (?)
                    AND d.name IN (?)
            ", [
                array_keys($urls),
                array_keys($domains),
            ]
        );
        foreach ($rows as $row) {
            $domain = self::canonicDomain($row['domain_name']);
            if (isset($link_by_domain_url[$domain][$row['full_url']])) {
                $link_by_domain_url[$domain][$row['full_url']]['result'] = $row['content'];
            }
        }
    }

    public function processShopPage(&$links) {
        $urls = [];
        $domains = [];
        $link_by_domain_url = [];
        foreach ($links as &$link) {
            $page_url = ifset($link['app_route'], 'url', null);
            $domain = ifset($link['settlement'], '_domain', null);
            if ($page_url !== null && $domain) {
                $domain = self::canonicDomain($domain);
                $link_by_domain_url[$domain][$page_url] =& $link;
                $domains['www.'.$domain] = true;
                $domains[$domain] = true;
                $urls[$page_url] = true;
            }
        }
        unset($link);
        if (!$link_by_domain_url) {
            return;
        }
        $rows = $this->m->query("
                SELECT p.full_url, domain AS domain_name, p.content
                FROM shop_page AS p
                WHERE p.full_url IN (?)
                    AND p.domain IN (?)
            ", [
                array_keys($urls),
                array_keys($domains),
            ]
        );
        foreach ($rows as $row) {
            $domain = self::canonicDomain($row['domain_name']);
            if (isset($link_by_domain_url[$domain][$row['full_url']])) {
                $link_by_domain_url[$domain][$row['full_url']]['result'] = $row['content'];
            }
        }
    }

    public function processShopProduct(&$links) {
        try {
            wa('shop');
            $product_model = new shopProductModel();
        } catch (Throwable $e) {
            return;
        }

        $link_by_url = [];
        foreach ($links as &$link) {
            $url = ifset($link['url_params'], 'product_url', null);
            if ($url) {
                $link_by_url[$url] =& $link;
            }
        }
        unset($link);
        if (!$link_by_url) {
            return;
        }

        // Product feature settings and which product types they are assigned to
        $features = (new shopFeatureModel())->getAll('id');
        foreach ((new shopTypeFeaturesModel)->getAll() as $row) {
            if (isset($features[$row['feature_id']])) {
                $features[$row['feature_id']]['types'][$row['type_id']] = $row['type_id'];
            }
        }
        $features = array_filter($features, function($f) {
            return !empty($f['types']);
        });
        $features = array_column($features, null, 'code');

        $default_currency = wa('shop')->getConfig()->getCurrency();
        $products = $product_model->getByField('url', array_keys($link_by_url), 'url');
        foreach ($products as $p) {
            $result = [];
            $link =& $link_by_url[$p['url']];
            unset($link_by_url[$p['url']]);

            if ($p['status'] < 0) {
                continue; // product is hidden and not available
            }

            $product = new shopProduct($p);
            $currency = ifset($link, 'settlement', 'currency', $default_currency);

            $result = ['Product name: '.$p['name']];

            foreach ($product->skus as $sku) {
                if ($sku['primary_price'] > 0 && $sku['available'] > 0 && $sku['status'] > 0) {
                    $result[] = $sku['name'].': price '.shop_currency($sku['primary_price'], null, $currency, true);
                }
            }

            $result[] = $p['description'];
            $result[] = $this->formatProductFeatures($features, $product);
            $result[] = 'Categories: '.join(', ', array_column($product['categories'], 'name'));
            $result[] = 'Tags: '.join(', ', $product['tags']);
            // TODO: could add lit of services and user reviews
            $link['result'] = join("\n\n", $result);
        }
        unset($link);

        // rest of the $links left unprocessed are product pages
        if (!$link_by_url) {
            return;
        }

        $page_urls = [];
        $product_urls = [];
        $link_by_product_page_urls = [];
        foreach ($link_by_url as &$link) {
            if (empty($link['url_params']['category_url'])) {
                continue;
            }
            $page_url = $link['url_params']['product_url'];
            $product_url = end(ref(explode('/', $link['url_params']['category_url'])));
            $link_by_product_page_urls["{$product_url}/{$page_url}"] =& $link;
            $product_urls[] = $product_url;
            $page_urls[] = $page_url;
        }
        unset($link);
        if (!$page_urls) {
            return;
        }

        $sql = "SELECT pp.url AS page_url, p.url AS product_url, pp.name AS page_name, pp.content AS content, p.name AS product_name
                FROM shop_product_pages AS pp
                    JOIN shop_product AS p
                        ON pp.product_id=p.id
                WHERE pp.url IN (?)
                    AND p.url IN (?)";
        $rows = $product_model->query($sql, [$page_urls, $product_urls]);
        foreach ($rows as $row) {
            if (!isset($link_by_product_page_urls["{$row['product_url']}/{$row['page_url']}"])) {
                continue;
            }
            $link =& $link_by_product_page_urls["{$row['product_url']}/{$row['page_url']}"];
            $link['result'] = "<h1>{$row['product_name']}: {$row['page_name']}</h1>\n{$row['content']}";
        }
        unset($link);
    }

    protected function formatProductFeatures($all_features, $product)
    {
        $all_features  = array_filter($all_features, function($f) use ($product) {
            return in_array($product->type_id, $f['types']) && $f['status'] !== 'private';
        });
        $res = [];
        foreach ($product->features as $code => $values) {
            if (!isset($all_features[$code])) {
                continue;
            }
            if (is_array($values)) {
                $values = join(', ', array_map('strval', $values));
            }
            $res[] = $all_features[$code]['name'].": ".strval($values);
        }
        return join("\n", $res);
    }
}
