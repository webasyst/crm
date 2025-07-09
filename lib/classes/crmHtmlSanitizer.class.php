<?php

class crmHtmlSanitizer
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
     * crmHtmlSanitizer constructor
     *
     * @param array $options
     *   array $options['replace_img_src']
     *     Replace map for inline images (img with attr data-crm-file-id)
     *     Map int 'file_id' to 'src' replacing url
     */
    public function __construct($options = array())
    {
        $this->options = $options;
    }

    /**
     * Static shortcut notation
     *
     * @param string $content
     * @param array $options
     * @return mixed|string
     */
    public static function work($content, $options = array())
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
        // Make sure it's a valid UTF-8 string
        $content = preg_replace('~\\xED[\\xA0-\\xBF][\\x80-\\xBF]~', '?', mb_convert_encoding((string) $content, 'UTF-8', 'UTF-8'));

        // Remove all tags except known.
        // We don't rely on this for protection. Everything should be escaped anyway.
        // strip_tags() is here so that unknown tags do not show as escaped sequences, making the text unreadable.
        $allowable_tags = '<a><b><i><u><s><img><pre><mark><code><blockquote><p><strong><section><em><del><strike><span><ul><ol><li><div><font><br><table><thead><tbody><tfoot><tr><td><th><hr><h1><h2><h3><h4><h5><h6><style>';
        $content = strip_tags($content, $allowable_tags);

        // Strip <style>...</style>
        $content = $this->stripTagWithContent($content, 'style');

        $content = preg_replace_callback('~<(td|th)\s+([^>]*)>\s*</\1>~i',
            array($this, 'handleEmptyTd'),
            $content
        );

        // Replace all &entities; with UTF8 chars, except for &, <, >.
        $content = str_replace(array('&amp;','&lt;','&gt;'), array('&amp;amp;','&amp;lt;','&amp;gt;'), $content);
        $content = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $content);
        $content = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $content);
        $content = html_entity_decode($content, ENT_COMPAT, 'UTF-8');

        // Remove redactor data-attribute
        $content = preg_replace('/(<[^>]+)data-redactor[^\s>]+/uis', '$1', $content);

        // Encode everything that seems unsafe.
        $content = htmlentities($content, ENT_QUOTES, 'UTF-8');

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

        // <img src="...">
        $content = preg_replace_callback(
            '~
                &lt;
                    img\s+
                    .*?
                    src=&quot;
                        ([^"><]+?)
                    &quot;
                    .*?
                    /?
                &gt;
            ~iuxs',
            array($this, 'sanitizeHtmlImg'),
            $content
        );

        // Remove closing </img>
        $content = preg_replace(
            '~&lt;/img&gt;~iuxs',
            '',
            $content
        );

        // <section data-role="c-email-signature">
        $content = preg_replace_callback(
            '~
                &lt;
                    section
                    \s+
                    data-role=&quot;
                        (c-email-signature)
                    &quot;
                    (.*?)
                &gt;
            ~iuxs',
            array($this, 'sanitizeHtmlASection'),
            $content
        );

        // <td colspan="...">
        $content = preg_replace_callback(
            '~
                &lt;
                    (td|th)\s+
                    (.*?)
                &gt;
            ~iuxs',
            array($this, 'sanitizeTd'),
            $content
        );

        // Simple tags: <b>, <i>, <u>, <pre>, <blockquote> and closing counterparts.
        // All attributes are removed.
        $content2 = preg_replace(
            '~
                (?s)
                &lt;
                    (/?(?:a|b|i|u|s|pre|blockquote|p|strong|section|em|del|code|strike|span|ul|ol|li|div|font|br|table|thead|tbody|tfoot|tr|td|th|hr|h1|h2|h3|h4|h5|h6)/?)
                    ((?!&gt;)[^a-z\-\_]((?!&gt;)(\s|.))+)?
                &gt;
            ~iux',
            '<\1>',
            $content
        );

        if ($content2 === null) {
            $content2 = preg_replace(
                '~
                    (?s)
                    &lt;
                        (/?(?:a|b|i|u|s|pre|blockquote|p|strong|section|em|del|code|strike|span|ul|ol|li|div|font|br|table|thead|tbody|tfoot|tr|td|th|hr|h1|h2|h3|h4|h5|h6)/?)
                        .*?
                    &gt;
                ~iux',
                '<\1>',
                $content
            );
        }

        $content = $content2;
        
        // Replace <h*> tags with a bold paragraph
        $h_patterns = array(
            '~<h[1-6]>~iux'   => '<p class="bold">',
            '~<\/h[1-6]>~iux' => '</p>',
        );
        $content = preg_replace(array_keys($h_patterns), array_values($h_patterns), $content);

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

        $content = str_replace('<pre>', '<pre class="prettyprint">', $content);

        // Remove \n around <blockquote> startting and ending tags
        $content = preg_replace('~(?U:\n\s*){0,2}<(/?blockquote)>(?U:\s*\n){0,2}~i', '<\1>', $content);

        $content = $this->closeTags($content);

        // Convert urls to clickable links
        $content = $this->linkify($content, ['target' => '_blank', 'rel' => 'nofollow']);

        return $content;
    }

    // Helper for sanitizeHtml()
    protected function sanitizeHtmlAHref($m)
    {
        $url = $this->sanitizeUrl(ifset($m[1]));
        if (ifset($this->options['verification_key'], false) && strpos($url, '/verification/'.$this->options['verification_key'].'/') !== false) {
            return '<a href="'.$this->attr_start.'javascript:void(0);'.$this->attr_end.'" class="gray">';
        }
        return '<a href="'.$this->attr_start.$url.$this->attr_end.'" target="_blank" rel="nofollow">';
    }

    protected function handleEmptyTd($m)
    {
        $attrs = $m[2];
        $attrs = preg_replace('~width=\S+~', '', $attrs);
        $attrs .= ' width="1%"';
        return '<'.$m[1].' '.$attrs.'></'.$m[1].'>';
    }

    protected function sanitizeTd($m)
    {
        $attributes = [];
        $legal_attributes = [
            'colspan',
            'width',
            'align',
            'valign',
        ];

        foreach ($legal_attributes as $attribute) {
            preg_match(
                '~'.$attribute.'=&quot;([^"\'><]+?)&quot;~i',
                $m[2],
                $match
            );

            if ($match) {
                $attributes[$attribute] = $match[1];
            }
        }

        foreach ($attributes as $attribute => $val) {
            $attributes[$attribute] = $attribute.'="'.$this->attr_start.$val.$this->attr_end.'"';
        }
        return '<' . $m[1] . ' ' . join(' ', $attributes) . '>';
    }

    protected function sanitizeHtmlImg($m)
    {
        $url = $this->sanitizeUrl(ifset($m[1]));
        if (!$url) {
            return '';
        }

        $attributes = array(
            'src' => $url,
        );

        $legal_attributes = array(
            'data-crm-file-id',
            'width',
            'height'
        );

        foreach ($legal_attributes as $attribute) {
            preg_match(
                '~
                &lt;
                    img\s+
                    .*?
                    '.$attribute.'=&quot;([^"\'><]+?)&quot;
                    .*?
                    /?
                &gt;
            ~iuxs',
                $m[0],
                $match
            );

            if ($match) {
                $val = $match[1];

                // Additional check for positive integer attributes
                if (in_array($attribute, array('data-crm-file-id', 'width', 'height'))) {
                    $val = (int) $val;
                    if ($val <= 0) {
                        continue;
                    }
                }

                $attributes[$attribute] = $val;
            }
        }

        if (isset($attributes['data-crm-file-id'])) {
            // url doesn't matter already, we use file-id link
            $attributes['src'] = '';
            $file_id = $attributes['data-crm-file-id'];
            if (isset($this->options['replace_img_src']) && isset($this->options['replace_img_src'][$file_id])) {
                $attributes['src'] = $this->options['replace_img_src'][$file_id];
            }
        }

        foreach ($attributes as $attribute => $val) {
            $attributes[$attribute] = $attribute.'="'.$this->attr_start.$val.$this->attr_end.'"';
        }

        return '<img ' . join(' ', $attributes) . '>';
    }

    // Section
    protected function sanitizeHtmlASection()
    {
        return '<section data-role="c-email-signature" class="email-signature">';
    }

    protected function sanitizeUrl($url)
    {
        if (empty($url)) {
            return '';
        }
        if (preg_match('~^data:image/~i', $url)) {
            return $url;
        }
        $url_alphanumeric = preg_replace('~&amp;[^;]+;~i', '', $url);
        $url_alphanumeric = preg_replace('~[^a-z0-9:]~i', '', $url_alphanumeric);
        if (preg_match('~^(javascript|vbscript):~i', $url_alphanumeric)) {
            return '';
        }

        if (preg_match('~^mailto:~i', $url)) {
            static $email_validator = null;
            if (!$email_validator) {
                $email_validator = new waEmailValidator();
            }
            if ($email_validator->isValid(substr($url, 7))) {
                return $url;
            }
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

    protected function stripTagWithContent($text, $tag_name)
    {
        $opened_tag = '<(?:\s*?)' . $tag_name . '(?:\s+(?:.*?)>|>)';
        $closed_tag = '<(?:\s*?)/(?:\s*?)' . $tag_name . '(?:\s+(?:.*?)>|>)';
        $inner_content = '(.*?)';
        $pattern = '~' . $opened_tag . $inner_content . $closed_tag . '~iuxsm';
        $text = preg_replace($pattern, '', $text);
        return $text;
    }

    public function toPlainText($content)
    {
        // Make sure it's a valid UTF-8 string
        $content = preg_replace('~\\xED[\\xA0-\\xBF][\\x80-\\xBF]~', '?', mb_convert_encoding((string) $content, 'UTF-8', 'UTF-8'));

        // Strip <style>...</style>
        $content = $this->stripTagWithContent($content, 'style');
        // Strip <script>...</script>
        $content = $this->stripTagWithContent($content, 'script');

        // Remove all tags except some of them.
        $allowable_tags = '<pre><blockquote><p><section><li><div><br><tr><h1><h2><h3><h4><h5><h6>';
        $content = strip_tags($content, $allowable_tags);

        // Replace br to nl
        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('~(<[^/])~', " \r\n$1", $content);
        $content = preg_replace('/^\s+/', '', $content);

        // Strip all remaining tags
        $content = strip_tags($content, '<unexisted>');
        $content = html_entity_decode($content);
        return $content;
    }

    public function linkify($value, array $attributes = [])
    {
        // Link attributes
        $attr = '';
        foreach ($attributes as $key => $val) {
            $attr .= ' ' . $key . '="' . htmlentities($val) . '"';
        }
        
        $links = [];
        
        // Extract existing links and tags
        $value = preg_replace_callback('~(<a .*?>.*?</a>|<.*?>)~i', function ($match) use (&$links) { 
            return '<' . array_push($links, $match[1]) . '>'; 
        }, $value);
        
        $value = preg_replace_callback(
            '~(?:(https?)://([^\s<]+)|(www\.[^\s<]+?\.[^\s<]+))(?<![\.,:])~i', 
            function ($match) use (&$links, $attr) { 
                $protocol = 'http';
                if ($match[1]) {
                    $protocol = $match[1];
                }
                $link = $match[2] ?: $match[3];
                $link_string = mb_strlen($link) <= 64 ? $link : mb_substr($link, 0, 50) . '...';
                if (ifset($this->options['verification_key'], false) && strpos($link, '/verification/'.$this->options['verification_key'].'/') !== false) {
                    $link = 'javascript:void(0);';
                    $attr = '';
                } else {
                    $link = $protocol . '://' . $link;
                }
                // remove zero width spaces from url
                $link = preg_replace( '/[\x{200B}-\x{200D}]/u', '', $link);
                return '<' . array_push($links, "<a $attr href=\"$link\">$link_string</a>") . '>'; 
            }, 
            $value
        );
        
        // Insert all link
        return preg_replace_callback('/<(\d+)>/', function ($match) use (&$links) { 
            return $links[$match[1] - 1]; 
        }, $value);
    }

    protected function closeTags($content)
    {
        $content = preg_replace_callback('%(<td[^>]*><div[^>]*>.*?</td>)%uis', "crmHtmlSanitizer::fixDiv", $content);
        // Fix unclosed tags
        $patt_open = "%((?<!</)(?<=<)[\s]*[^/!>\s]+(?=>|[\s]+[^>]*[^/]>)(?!/>))%is";
        $patt_close = "%((?<=</)([^>]+)(?=>))%is";
        $c_tags = $m_open = $m_close = array();
        if (preg_match_all($patt_open, $content, $matches)) {
            $m_open = $matches[1];
            if ($m_open) {
                preg_match_all($patt_close, $content, $matches2);
                $m_close = $matches2[1];
                if (count($m_open) > count($m_close)) {
                    $m_open = array_reverse($m_open);
                    foreach ($m_close as $tag) {
                        if (isset($c_tags[$tag])) {
                            $c_tags[$tag]++;
                        } else {
                            $c_tags[$tag] = 1;
                        }
                    }
                    $close_html = "";
                    foreach ($m_open as $k => $tag) {
                        if ((!isset($c_tags[$tag]) || $c_tags[$tag]-- <= 0) && !in_array(strtolower($tag), array('br', 'img'))) {
                            $close_html = $close_html.'</'.$tag.'>';
                        }
                    }
                    $content .= $close_html;
                }
            }
        }

        return $content;
    }

    protected static function fixDiv($content)
    {
        $content = stripcslashes($content[1]);
        if (strstr($content, '</div>') === false) {
            $content = str_replace('</td>', '</div></td>', $content);
        }
        return $content;
    }
}
