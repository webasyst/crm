<?php

$m = new crmTemplatesModel();

$search = '{$invoice.tax_percent|escape}';
$replace = '{$invoice.tax_percent|floatval|wa_format_number:false}';

$templates = $m->select('id, content')->fetchAll('id');

foreach ($templates as $template) {
    if (mb_strpos($template['content'], $search) !== false) {
        $content = str_replace($search, $replace, $template['content']);
        $m->updateById($template['id'], ['content' => $content]);
    }
}
