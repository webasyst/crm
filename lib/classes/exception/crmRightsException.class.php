<?php

class crmRightsException extends waRightsException
{
    public function __toString()
    {
        $t = "_ws";
        $html = parent::__toString();
        $wa_url = wa()->getRootUrl();
        $version = wa()->getVersion('webasyst');
        $iframe = !!waRequest::request('iframe', 0);

        if (wa()->whichUI() !== '1.3') {
            $content = <<<HTML
<link href="{$wa_url}wa-content/css/wa/wa-2.0.css?v{$version}" rel="stylesheet" type="text/css">
<h1>{$t("Error")} #403</h1>
<div class="alert warning">
    <p><strong>{$t("You have no permission to access this page.")}</strong></p>
    <p>{$t("Please refer to your system administrator.")}</p>
</div>
HTML;
        } else {
            $content = <<<HTML
<h1>{$t("Error")} #403</h1>
<div style="border:1px solid #EAEAEA;padding:1.5em 1.5em 0 1.5em;margin:12px 0">
<p style="color:red; font-weight: bold">{$t("You have no permission to access this page.")}</p>

<p>{$t("Please refer to your system administrator.")}</p>
</div>
HTML;
        }

        if ($iframe) {
            $html = <<<HTML
<div id="wa-app" class="content blank" style="height: calc(100vh - 4rem);">
    <div class="article">
        <div class="article-body">
            {$content}
        </div>
    </div>
</div>
HTML;
        }

        return $html;
    }
}
