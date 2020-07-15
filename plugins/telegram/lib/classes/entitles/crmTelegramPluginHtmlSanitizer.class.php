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
        if (!$content) {
            return '';
        }
        if (!$entities) {
            return nl2br(trim($content));
        }

        $entities = array_reverse($entities);

        foreach ($entities as $ent)
        {
            switch ($ent['type']) {
                case 'bold':
                    $open_tag = '<b>';
                    $close_tag = '</b>';
                    break;
                case 'italic':
                    $open_tag = '<em>';
                    $close_tag = '</em>';
                    break;
                case 'text_link':
                    $open_tag = '<a href="'.$ent['url'].'" target="_blank">';
                    $close_tag = '</a>';
                    break;
                case 'mention':
                    $username = mb_substr($content, $ent['offset'] + 1, $ent['length']);
                    $open_tag = '<a href="https://t.me/'.trim($username).'" target="_blank">';
                    $close_tag = '</a>';
                    break;
                case 'email':
                    $open_tag = '<a href="mailto:'.mb_substr($content, $ent['offset'], $ent['length']).'">';
                    $close_tag = '</a>';
                    break;
                case 'code':
                    $open_tag = '<blockquote><pre>';
                    $close_tag = '</pre></blockquote>';
                    break;
                case 'pre':
                    $open_tag = '<blockquote>';
                    $close_tag = '</blockquote>';
                    break;
                default:
                    $open_tag = $close_tag = '';
            }
            $format_str = $open_tag . mb_substr($content, $ent['offset'], $ent['length']). $close_tag;
            $content = $this->mb_substr_replace($content, $format_str, $ent['offset'], $ent['length']);
        }

        return nl2br(trim($content));
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
                    (/?(?:a|b|strong|i|em|pre|code))
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
        $content = strip_tags($content, '<a><b><strong><i><em><pre><code>');

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

    protected function mb_substr_replace($string, $replacement, $start, $length = null)
    {
        if (is_array($string)) {
            $num = count($string);
            // $replacement
            $replacement = is_array($replacement) ? array_slice($replacement, 0, $num) : array_pad(array($replacement), $num, $replacement);
            // $start
            if (is_array($start)) {
                $start = array_slice($start, 0, $num);
                foreach ($start as $key => $value) {
                    $start[$key] = is_int($value) ? $value : 0;
                }
            } else {
                $start = array_pad(array($start), $num, $start);
            }
            // $length
            if (!isset($length)) {
                $length = array_fill(0, $num, 0);
            } elseif (is_array($length)) {
                $length = array_slice($length, 0, $num);
                foreach ($length as $key => $value) {
                    $length[$key] = isset($value) ? (is_int($value) ? $value : $num) : 0;
                }
            } else {
                $length = array_pad(array($length), $num, $length);
            }
            // Recursive call
            return array_map(__FUNCTION__, $string, $replacement, $start, $length);
        }
        preg_match_all('/./us', (string)$string, $smatches);
        preg_match_all('/./us', (string)$replacement, $rmatches);
        if ($length === null) {
            $length = mb_strlen($string);
        }
        array_splice($smatches[0], $start, $length, $rmatches[0]);
        return join($smatches[0]);
    }
}
