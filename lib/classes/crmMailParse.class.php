<?php

class crmMailParse
{
    protected static $sign;
    protected static $sign_info = array(
        'name'     => null,
        'company'  => null,
        'jobtitle' => null,
        'phone'    => null,
        'site'     => null,
        'skype'    => null,
        'twitter'  => null,
    );

    public static function getSignPattern()
    {
        return array_fill_keys(array_keys(self::$sign_info), null);
    }

    public static function sign($html)
    {
        self::$sign = null;
        self::$sign_info = self::getSignPattern();

        self::getSign($html);

        self::getName();
        self::getPhone();
        self::getTwitter();
        self::getSkype();
        self::getSite();
        self::getCompany();
        self::getJobtitle();

        return self::$sign_info;
    }

    protected static function getSign($html)
    {
        $html = preg_replace('/<blockquote.*?>(<blockquote.*?>(?1)*?<\/blockquote>|.)*?<\/blockquote>/is', '', $html);

        $html = preg_replace('~</(p|div)>~i', "</$1>\n", $html);
        $html = preg_replace('~<br */?>~i', "$0\n", $html);

        $text = strip_tags($html);
        // $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $text = preg_replace("/[ \t]+/", ' ', $text);

        $text = preg_replace("/\n *>/", "\n\n>", $text);
        $text = preg_replace("/\n>.*\n/", '', $text);

        $delimiters = "--|__|thanks[ ,!]?|thx[ ,!]?|regards[ ,!]?|rgs[ ,!]?|cheers[ ,!]|best[ a-z]*[ ,!]?|с уважением *[ ,!]?|спасибо *[ ,!]?";

        $parts = preg_split("~\n *(".$delimiters.") *\n~iu", $text);
        if (count($parts) == 1) {
            return null;
        }

        self::$sign = "\n".end($parts)."\n";
        return self::$sign;
    }

    protected static function getName()
    {
        if (preg_match("/\n *(([A-ZA-Я][a-zа-я\.\-]+){1,2} *){1,3} *\n/u", self::$sign, $m)) {
            self::$sign_info['name'] = trim($m[0]);
            self::$sign = str_replace($m[0], "\n", self::$sign);
        }
    }

    protected static function getCompany()
    {
        if (
            preg_match("/\n *([A-ZA-Я]\.?){3,4} +(\"|'|`«)?[A-ZA-Я][a-zа-я]*[^ \"'`»\n]+(\"|'|`»)? *\n/u", self::$sign, $m) ||
            preg_match("/\n *(\"|'|`«)?[A-ZA-Я][a-zа-я]*[^ \"'`»\n]+(\"|'|`)? +([A-ZA-Я]\.?){3,4} *\n/u", self::$sign, $m)
        ) {
            self::$sign_info['company'] = trim($m[0]);
            self::$sign = str_replace($m[0], "\n", self::$sign);
        }
    }

    protected static function getJobtitle()
    {
        if (preg_match("/\n *([a-zа-я][a-zа-я\.\, ]+) *\n/iuU", self::$sign, $m)) {
            self::$sign_info['jobtitle'] = $m[1];
            self::$sign = str_replace($m[1], "\n", self::$sign);
        }
    }

    protected static function getPhone()
    {
        if (
            preg_match("/\n *(phone|тел\.?|моб\.?)+[^\+\d\-()\n]*\+?(\d[\d\-() ]+)/", self::$sign, $m) ||
            preg_match("/((\d[\d\-() ]+))/", self::$sign, $m)
        ) {
            self::$sign_info['phone'] = preg_replace('/[^\d]/', '', $m[2]);
            self::$sign = str_replace($m[0], "\n", self::$sign);
        }
    }

    protected static function getTwitter()
    {
        if (preg_match("/\n *(twitter|твиттер) *: *(@[a-z]+)/ui", self::$sign, $m)) {
            self::$sign_info['twitter'] = trim($m[2]);
            self::$sign = str_replace($m[0], "\n", self::$sign);
        }
    }

    protected static function getSkype()
    {
        if (preg_match("/\n *(skype|скайп) *: *([a-z\d]+)/ui", self::$sign, $m)) {
            self::$sign_info['skype'] = trim($m[2]);
            self::$sign = str_replace($m[0], "\n", self::$sign);
        }
    }

    protected static function getSite()
    {
        $w = "a-zа-я0-9";
        $url_pattern = "(?:https?://)?(?:www.)?(?:(?:[$w]+(?:\.[$w\-])*)+\.[$w]{2,6})";

        if (preg_match("~\n *(site|сайт)? *\:? *($url_pattern) *\n~iuU", self::$sign, $m)) {
            self::$sign_info['site'] = trim($m[2]);
            self::$sign = str_replace($m[0], "\n", self::$sign);
        }
    }

    /*
     * @deprecated
     */
    protected static function stripBlockquote($html)
    {
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        $xpath = new DOMXpath($dom);
        $b = $xpath->query('//blockquote');
        if ($b->length) {
            $b->item(0)->parentNode->removeChild($b->item(0));
        }
        $html = $dom->saveHTML();

        $html = preg_replace("/\r?\n\s*>[^\r\n]*\r?\n/", '', $html);

        return $html;
    }
}