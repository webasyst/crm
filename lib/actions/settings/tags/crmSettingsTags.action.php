<?php

class crmSettingsTagsAction extends crmSettingsViewAction
{
    private $colors = [
        '#cc5252','#cc8f52','#cccc52','#52cc52','#52cc8f','#52cccc','#528fcc','#5252cc','#8f52cc','#cc52cc','#cc528f'
    ];

    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $tags = $this->getTagModel()->getCloudFast();

        $counters = empty($tags) ? [] : $this->getContactTagsModel()->query("SELECT tag_id, CASE WHEN contact_id > 0 THEN CONCAT('contact:', tag_id) ELSE CONCAT('deal:', tag_id) END AS `type`, COUNT(*) AS `count` FROM crm_contact_tags GROUP BY tag_id, `type`")->fetchAll('type');

        $tags = array_map(function ($tag) use ($counters) {
            $tag['bg_color'] = $this->getBgTagColor($tag['color']);
            $tag['deal_count'] = isset($counters['deal:'.$tag['id']]) ? $counters['deal:'.$tag['id']]['count'] : 0;
            $tag['contact_count'] = isset($counters['contact:'.$tag['id']]) ? $counters['contact:'.$tag['id']]['count'] : 0;
            return $tag;
        }, $tags);

        $this->view->assign([
            'tags' => $tags,
            'colors' => $this->colors,
        ]);
    }

    protected function getBgTagColor($color)
    {
        if (empty($color)) {
            return null;
        }
        
        $color = is_scalar($color) ? trim(strval($color)) : '';
        if (strlen($color) <= 0 || $color[0] != '#' || strlen($color) != 7) {
            return null;
        }

        $c = substr($color, 1);
        list($r, $g, $b) = array(hexdec($c[0].$c[1]), hexdec($c[2].$c[3]), hexdec($c[4].$c[5]));
        return 'rgba(' . $r . ',' . $g .  ',' . $b . ', 0.3)';
    }
}