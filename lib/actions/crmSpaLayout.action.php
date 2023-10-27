<?php
/**
 * Spa layout for contacts section.
 */
class crmSpaLayoutAction extends crmBackendViewAction
{
    public function execute()
    {
        $this->setTemplate('templates/actions/SpaLayout.html');

        $section = $this->getSection();
        wa('crm')->getConfig()->setLastVisitedUrl($section);
    }

    private function getSection()
    {
        $key = 0;
        $current_url = trim(wa()->getConfig()->getCurrentUrl(), '/');
        $paths = explode('/', $current_url);
        $app_id = $this->getAppId();
        $backend_url = wa()->getConfig()->getBackendUrl();
        foreach ($paths as $path) {
            if (in_array($path, [$backend_url, $app_id])) {
                $key++;
            } elseif ($key === 2) {
                break;
            }
        }

        return "$path/";
    }
}
