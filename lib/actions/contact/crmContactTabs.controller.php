<?php

class crmContactTabsController extends crmJsonController
{
    const APPS_EXCLUDE = [
        'team',
        'crm'
    ];

    private $url_validator;
    private $scheme;
    private $domain;
    private $backend_url;

    public function __construct()
    {
        $this->url_validator = new waUrlValidator();
        $this->scheme = (waRequest::isHttps() ? 'https://' : 'http://');
        $this->domain = wa()->getRouting()->getDomain();
        $this->backend_url = wa()->getConfig()->getBackendUrl();
    }

    public function execute()
    {
        $contact_id = waRequest::get('id', 0, waRequest::TYPE_INT);
        if ($contact_id < 1) {
            throw new waAPIException('invalid_param', _w('Invalid contact ID.'), 400);
        } elseif (null === $this->getContactModel()->getById($contact_id)) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        }

        waRequest::setParam('profile_tab_counter_inside', false);
        $tabs = waSystem::getInstance()->getView()->getHelper()->getContactTabs($contact_id);

        // exclude team & crm tabs
        $tabs = array_filter($tabs, function ($el) {
            return !in_array($el['app_id'], self::APPS_EXCLUDE);
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

        $this->response['tabs'] = array_values($tabs);

        $deal_ids = array_keys($this->getDealParticipantsModel()->getByField([
            'contact_id' => $contact_id, 
            'role_id' => crmDealParticipantsModel::ROLE_CLIENT
        ], 'deal_id'));
        $condition_ids = array_map(function($deal_id) {
            return $deal_id * -1;
        }, $deal_ids);
        $condition_ids[] = $contact_id;

        $this->response['counters'] = [
            [
                'name' => 'deals',
                'value' => count($deal_ids),
            ],
            [
                'name' => 'reminders',
                'value' => (int) $this->getReminderModel()->countByField([
                    'contact_id' => $contact_id,
                    'complete_datetime' => null,
                ]),
            ],
            [
                'name' => 'invoices',
                'value' => (int) $this->getInvoiceModel()->countByField('contact_id', $contact_id),
            ],
            [
                'name' => 'calls',
                'value' => (int) $this->getCallModel()->countByField('client_contact_id', $contact_id),
            ],
            [
                'name' => 'notes',
                'value' => (int) $this->getNoteModel()->countByField('contact_id', $contact_id),
            ],
            [
                'name' => 'files',
                'value' => (int) $this->getFileModel()->countByField('contact_id', $condition_ids),
            ],
        ];
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
