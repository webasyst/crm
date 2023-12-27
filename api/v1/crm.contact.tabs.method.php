<?php

class crmContactTabsMethod extends crmApiAbstractMethod
{
    private $url_validator;
    private $scheme;
    private $domain;
    private $backend_url;
    protected $method = self::METHOD_GET;

    public function __construct()
    {
        $this->url_validator = new waUrlValidator();
        $this->scheme = (waRequest::isHttps() ? 'https://' : 'http://');
        $this->domain = wa()->getRouting()->getDomain();
        $this->backend_url = wa()->getConfig()->getBackendUrl();

        parent::__construct();
    }

    public function execute()
    {
        $contact_id = $this->get('id', true);
        if (!is_numeric($contact_id) || $contact_id < 1) {
            throw new waAPIException('invalid_param', _w('Invalid contact ID.'), 400);
        }
        $contact = new waContact($contact_id);
        if (!$contact->exists()) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        }
        if (!$this->getCrmRights()->contact($contact)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }
        $view = waSystem::getInstance()->getView();
        $tabs = $view->getHelper()->getContactTabs($contact_id);

        // exclude team & crm tabs
        $tabs = array_filter($tabs, function ($el) {
            return !in_array($el['app_id'], ['team', 'crm']);
        });

        foreach ($tabs as &$_tab) {
            $_tab['url']  = $this->getUrl($_tab['app_id'], $_tab['url']);
            $_tab['html'] = (empty($_tab['html']) ? null : (string) $_tab['html']);
            if (is_string($_tab['count'])) {
                $_tab['count'] = (empty($_tab['count']) ? null : (int) $_tab['count']);
            }
            $_tab['title'] = strip_tags($_tab['title']);
            $_tab['title'] = preg_replace('/\s*\([\d\/\\\\]+\)/', '', $_tab['title']);
        }

        $this->response = $this->filterData($tabs, ['title', 'html', 'count', 'url', 'id', 'app_id']);
    }

    private function getUrl($app, $url)
    {
        $_url = null;
        if (empty($url)) {
            return $_url;
        }

        if ($this->url_validator->isValid($url)) {
            $_url = $url;
        } else if ($parse_url = $this->parseUrl($url)) {
            /** scheme */
            if (empty($parse_url['scheme'])) {
                $_url = $this->scheme;
            } else {
                $_url = $parse_url['scheme'].'://';
            }

            /** host */
            $_url .= (empty($parse_url['host']) ? $this->domain : $parse_url['host']);

            /** port */
            $_url .= (empty($parse_url['port']) ? '' : ':'.$parse_url['port']);

            /** path */
            $_url .= (empty($parse_url['path']) ? '' : $parse_url['path']);

            /** query */
            $_url .= (empty($parse_url['query']) ? '' : '?'.$parse_url['query']);

            /** fragment */
            $_url .= (empty($parse_url['fragment']) ? '' : '#'.$parse_url['fragment']);
        } else {
            $url = ltrim($url, '/');
            $pos = strpos($url, $this->backend_url);
            if ($app == 'pro-plugin') {
                $app = 'contacts';
            }
            if ($pos === false || !in_array($pos, [0, 1])) {
                $url = $this->backend_url.'/'.$app.'/'.$url;
            }
            $_url = $this->scheme.$this->domain.'/'.$url;
        }

        return $_url;
    }

    private function parseUrl($url)
    {
        preg_match('#
            ^(?:(?<scheme>\w+)://)?
            (?<host>(?:[.\w]+\.)?\w+\.\w+)
            (?::(?<port>\d+))?
            (?<path>[/\w]*/(?:\w+(?:\.\w+)?)?)?
            (?:\?(?<query>[=&\w]+))?
            (?:\#(?<fragment>\w+))?
        #x', $url, $out);

        return $out;
    }
}
