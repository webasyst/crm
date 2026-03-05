<?php

$m = new crmTemplatesModel();

$templates = $m->getByField(['origin_id' => [2,3]], 'id');

foreach ($templates as $template) {
    if (mb_strpos($template['content'], '<td class="c-h-mobile c-text-nowrap c-align-top c-text-right c-pr-30 c-pt-12">{$_item@iteration}</td>') !== false) {
        $content = str_replace(
            '<td class="c-h-mobile c-text-nowrap c-align-top c-text-right c-pr-30 c-pt-12">{$_item@iteration}</td>', 
            '<td class="c-h-mobile c-text-nowrap c-align-top c-text-right c-pr-30 c-pt-12">{$_item.quantity|wa_format}</td>', 
            $template['content']
        );
        $m->updateById($template['id'], ['content' => $content]);
    }
}
