<?php

class crmTelegramPluginHtmlSanitizer
{
    /**
     * @var array
     */
    protected $options;

    /**
     * @var string
     */
    protected $attr_end;

    /**
     * @var string
     */
    protected $attr_start;

    /**
     * crmTelegramPluginHtmlSanitizer constructor
     *
     * @param array $options
     */
    public function __construct($options = array())
    {
        $this->options = $options;
    }

    /**
     * @param crmTelegramPluginMessage $message
     * @param array $options
     * @return string
     */
    public static function parser($message, $options = array())
    {
        $sanitizer = new self($options);
        return $sanitizer->compile($message->getText(), $message->getEntities());
    }

    /**
     * @param crmTelegramPluginMessage $message
     * @param array $options
     * @return string
     */
    public static function parserCaption($message, $options = array())
    {
        $sanitizer = new self($options);
        return $sanitizer->compile($message->getCaption(), $message->getCaptionEntities());
    }

    public function compile($content, $entities)
    {
        if (empty($content) && $content !== '0') {
            return '';
        }
        if (empty($entities)) {
            $content = htmlentities($content, ENT_NOQUOTES, 'UTF-8');
            return nl2br(trim($content));
        }

        do {
            $tag_start = uniqid('-TS').'ST-';
            $tag_end = uniqid('-TE').'ET-';
            $nl = uniqid('NL');
        } while (strpos($content, $nl) !== false || strpos($content, $tag_start) !== false || strpos($content, $tag_end) !== false);

        $entities = array_reverse($entities);

        $content = mb_convert_encoding($content, 'UTF-16', 'UTF-8');
        $nl_16_subst = mb_convert_encoding($nl, 'UTF-16', 'UTF-8');
        $nl_16 = mb_convert_encoding("\n", 'UTF-16', 'UTF-8');

        foreach ($entities as $idx => &$ent)
        {
            switch ($ent['type']) {
                case 'bold':
                    $open_tag = 'b';
                    $close_tag = '/b';
                    break;
                case 'italic':
                    $open_tag = 'em';
                    $close_tag = '/em';
                    break;
                case 'underline':
                    $open_tag = 'u';
                    $close_tag = '/u';
                    break;
                case 'strikethrough':
                    $open_tag = 'strike';
                    $close_tag = '/strike';
                    break;
                case 'text_link':
                    $open_tag = 'a href="'.$ent['url'].'" target="_blank"';
                    $close_tag = '/a';
                    break;
                case 'mention':
                    $username = substr($content, $ent['offset']*2 + 1, $ent['length']*2);
                    $open_tag = 'a href="https://t.me/'.trim($username).'" target="_blank"';
                    $close_tag = '/a';
                    break;
                case 'email':
                    $open_tag = 'a href="mailto:'.substr($content, $ent['offset']*2, $ent['length']*2).'"';
                    $close_tag = '/a';
                    break;
                case 'code':
                    $open_tag = 'code';
                    $close_tag = '/code';
                    break;
                case 'pre':
                    $open_tag = 'pre';
                    $close_tag = '/pre';
                    break;
                case 'blockquote':
                    $open_tag = 'blockquote';
                    $close_tag = '/blockquote';
                    break;
                default:
                    $open_tag = $close_tag = '';
            }
            if (empty($open_tag) || empty($close_tag)) {
                continue;
            }
            $ent['open_tag'] = $tag_start . $open_tag . $tag_end;
            $ent['close_tag'] = $tag_start . $close_tag . $tag_end;
            $ent['orig_length'] = $ent['length'];

            for ($i = $idx - 1; $i >= 0; $i--) {
                if ($ent['offset'] + $ent['orig_length'] >= $entities[$i]['offset'] + $entities[$i]['orig_length']) {
                    $ent['length'] += mb_strlen($entities[$i]['open_tag']) + mb_strlen($entities[$i]['close_tag']);
                } else {
                    break;
                }
            }

            $open_tag = mb_convert_encoding($ent['open_tag'], 'UTF-16', 'UTF-8');
            $close_tag = mb_convert_encoding($ent['close_tag'], 'UTF-16', 'UTF-8');

            $format_str = $open_tag . substr($content, $ent['offset']*2, $ent['length']*2). $close_tag;
            if ($ent['type'] === 'pre') {
                $format_str = str_replace($nl_16, $nl_16_subst, $format_str);
            }
            $content = substr_replace($content, $format_str, $ent['offset']*2, $ent['length']*2);
        }
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16');

        $content = htmlentities($content, ENT_NOQUOTES, 'UTF-8');
        $content = str_replace($tag_start, '<', $content);
        $content = str_replace($tag_end, '>', $content);

        // Remove \r\n after </blockquote> and </pre> ending tags
        $content = preg_replace('~<(/(blockquote|pre))>\s*\r?\n~i', '<\1>', $content);
        $content = nl2br(trim($content));
        $content = str_replace($nl, "\n", $content);

        return $content;
    }

    public static function convector($content, $options = array())
    {
        $sanitizer = new self($options);
        return $sanitizer->sanitize($content);
    }

    /**
     * Sanitize content
     * @param string $content
     * @return mixed|string
     */
    public function sanitize($content)
    {
        // Remove redactor data-attribute
        $content = preg_replace('/(<[^>]+)data-redactor[^\s>]+/uis', '$1', $content);

        // Replace all &entities; with UTF8 chars, except for &, <, >.
        $content = str_replace(array('&amp;','&lt;','&gt;'), array('&amp;amp;','&amp;lt;','&amp;gt;'), $content);

        //
        // The plan is: to quote everything, then unquote parts that seem safe.
        //

        // A trick we use to make sure there are no tags inside attributes of other tags.
        do {
            $this->attr_start = $attr_start = uniqid('<ATTRSTART').'>';
            $this->attr_end = $attr_end = uniqid('<ATTREND').'>';
        } while (strpos($content, $attr_start) !== false || strpos($content, $attr_end) !== false);

        // <a href="...">
        $content = preg_replace_callback(
            '~
                &lt;
                    a
                    \s+
                    href=&quot;
                        ([^"><]+?)
                    &quot;
                    (.*?)
                &gt;
            ~iuxs',
            array($this, 'sanitizeHtmlAHref'),
            $content
        );

        // Simple tags: <b>, <i>, <pre> and closing counterparts.
        // All attributes are removed.
        $content = preg_replace(
            '~
                &lt;
                    (/?(?:a|b|strong|i|em|pre|code|s|u|strike|del))
                    ((?!&gt;)[^a-z\-\_]((?!&gt;)(\s|.))+)?
                &gt;
            ~iux',
            '<\1>',
            $content
        );

        // Remove $attr_start and $attr_end from legal attributes
        $content = preg_replace(
            '~
                '.preg_quote($attr_start).'
                ([^"><]*)
                '.preg_quote($attr_end).'
            ~ux',
            '\1',
            $content
        );

        // Remove illegal attributes, i.e. those where $attr_start and $attr_end are still present
        $content = preg_replace(
            '~
                '.preg_quote($attr_start).'
                .*
                '.preg_quote($attr_end).'
            ~uxUs',
            '',
            $content
        );
        $content = str_replace('&amp;', '&', $content);

        // Being paranoid... remove $attr_start and $attr_end if still present anywhere.
        // Should not ever happen.
        $content = str_replace(array($attr_start, $attr_end), '', $content);

        // Convert </p><p> and <br> to \n
        $content = preg_replace('~&lt;/p&gt;    &lt;p&gt;~', "\n", $content);
        $content = preg_replace('~</p>\s*<p>~', "\n", $content);

        $content = preg_replace('~</li>\s*<li>~', "\n— ", $content);

        $content = preg_replace('~<ul>\s*<li>~', "\n\n— ", $content);
        $content = preg_replace('~</li>\s*</ul>~', "\n\n", $content);

        $content = preg_replace('~<ol>\s*<li>~', "\n\n— ", $content);
        $content = preg_replace('~</li>\s*</ol>~', "\n\n", $content);

        // Replace <b><i>content</i><b> to <b>content</b> (remove italic)
        $content = preg_replace('~<(b|strong)><(i|em)>(.*)</(i|em)></(b|strong)>~', "<strong>$3</strong>", $content);
        $content = preg_replace('~<(i|em)><(b|strong)>(.*)</(b|strong)></(i|em)>~', "<strong>$3</strong>", $content);

        $content = str_replace('~\s*~', " ", $content);

        $content = preg_replace('~<br/?>~', "\n", $content);
        // Convert &nbsp; to space
        $content = str_replace("&nbsp;", " ", $content);
        // Convert &nbsp; to —
        $content = str_replace("&mdash;", "—", $content);
        // Leave only those tags from which Telegram will not go mad
        $content = strip_tags($content, '<a><b><strong><i><em><pre><code><blockquote><s><u><strike><del>');

        return $content;
    }

    // Helper for sanitizeHtml()
    protected function sanitizeHtmlAHref($m)
    {
        $url = $this->sanitizeUrl(ifset($m[1]));
        return '<a href="'.$this->attr_start.$url.$this->attr_end.'">';
    }

    protected function sanitizeUrl($url)
    {
        if (empty($url)) {
            return '';
        }
        $url_alphanumeric = preg_replace('~&amp;[^;]+;~i', '', $url);
        $url_alphanumeric = preg_replace('~[^a-z0-9:]~i', '', $url_alphanumeric);
        if (preg_match('~^(javascript|vbscript):~i', $url_alphanumeric)) {
            return '';
        }

        static $url_validator = null;
        if (!$url_validator) {
            $url_validator = new waUrlValidator();
        }

        if (!$url_validator->isValid($url)) {
            $url = 'http://'.preg_replace('~^([^:]+:)?(//|\\\\\\\\)~', '', $url);
        }

        return $url;
    }

    public function handleMarkUp($content)
    {
        do {
            $holder = uniqid('<HLDR').'>';
        } while (strpos($content, $holder) !== false);
        
        $content = preg_replace_callback('/```((?:(?!```).)+)```/s', function($m) use ($holder) {
            $s = ifset($m[1], '');
            $s = str_replace('`', $holder, $s);
            return '<pre>'.$s.'</pre>';
        }, $content);
        
        $content = preg_replace('/`([^`]+)`/', '<code>\1</code>', $content);
        $content = str_replace($holder, '`', $content);

        $content = preg_replace('/\\*\\*((?:(?!\\*\\*).)+)\\*\\*/', '<b>\1</b>', $content);
        $content = preg_replace('/~~((?:(?!~~).)+)~~/', '<s>\1</s>', $content);
        $content = preg_replace('/__((?:(?!__).)+)__/', '<i>\1</i>', $content);

        // Handle links
        $content = preg_replace('/\[([^\[\]]+)\]\((https?\:\/\/[^\s\(\)]+)\)/', '<a href="\2">\1</a>', $content);
        $content = preg_replace('/\[([^\[\]]+)\]\((www\.[^\.][^\s\(\)]+\.[^\s\(\)]+)\)/', '<a href="http://\2">\1</a>', $content);

        // Handle blockquote
        $content = preg_replace('/((?:^|\n))&gt; ?((?:(?!\r?\n).)+)/s', '\1<blockquote>\2</blockquote>', $content);
	    $content = preg_replace('~</blockquote>\s*<blockquote>~s', "\n", $content);
        return $content;
    }

}
