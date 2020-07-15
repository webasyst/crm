<?php

class crmVkPluginBodyHtmlFormatter
{
    protected $options;

    public function __construct($options = array())
    {
        $this->options = $options;
    }

    public function execute($html)
    {
        $tokens = preg_split('/(\s+)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($tokens as &$token) {

            if ($this->looksLikeEmail($token)) {
                $token = htmlspecialchars($token);
                $token = "<a href=\"mailto:{$token}\">{$token}</a>";
                continue;
            }

            $parsed = null;
            if ($this->looksLikeUrl($token, $parsed)) {
                $href = $this->buildUrl($parsed);
                $token = htmlspecialchars($token);
                $href = htmlspecialchars($href);
                $token = "<a target='_blank' href=\"{$href}\">{$token}</a>";
                continue;
            }

            $token = htmlspecialchars($token);
        }
        unset($token);

        $html = join('', $tokens);
        $html = nl2br($html);
        return $html;
    }

    protected function looksLikeEmail($email)
    {
        $validator = new waEmailValidator();
        return $validator->isValid($email);
    }


    protected function looksLikeUrl($url, &$parsed) {
        $parsed = $this->parseUrl($url);
        if (!$parsed) {
            return false;
        }
        if ($parsed['scheme'] !== 'http' && $parsed['scheme'] !== 'https') {
            return false;
        }
        $hots = $parsed['host'];
        $domains = $this->getDomains();
        $pattern = str_replace(':DOMAINS', $domains, '!\.(:DOMAINS)$!');
        return preg_match($pattern, $hots);
    }

    protected function buildUrl($parsed) {
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : 'http://';
        $host = isset($parsed['host']) ? $parsed['host'] : '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $user = isset($parsed['user']) ? $parsed['user'] : '';
        $pass = isset($parsed['pass']) ? ':' . $parsed['pass']  : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = isset($parsed['path']) ? $parsed['path'] : '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
        return "{$scheme}{$user}{$pass}{$host}{$port}{$path}{$query}{$fragment}";
    }

    protected function parseUrl($url)
    {
        $parsed = parse_url($url);
        if (!$parsed) {
            return false;
        }
        if (!isset($parsed['scheme'])) {
            $parsed['scheme'] = 'http';
            $parsed = parse_url("http://{$url}");
            if (!$parsed) {
                return false;
            }
        }
        if (!isset($parsed['host'])) {
            return false;
        }
        return $parsed;
    }

    protected function getDomains()
    {
        static $domains;
        if (!$domains) {
            $domains = join('|', array(
                'com','org','net','dk','at','us','tv','info','uk',
                'co.uk','biz','se','ru','su','ua','com.ua','by','io',
                //...
            ));
            $domains = 'com';
        }
        return $domains;
    }
}
