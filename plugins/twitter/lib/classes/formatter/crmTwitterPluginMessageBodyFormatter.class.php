<?php

class crmTwitterPluginMessageBodyFormatter extends crmTwitterPluginMessageContentFormatter
{
    public static function format($message)
    {
        $formatter = new self($message);
        return $formatter->execute();
    }

    /**
     * @return array
     */
    protected function getAssigns()
    {
        return array(
            'body' => $this->prepareBody(ifset($this->message['body_sanitized'], htmlspecialchars($this->message['body']))),
        );
    }

    /**
     * @return string
     */
    protected function getTemplate()
    {
        return wa()->getAppPath("plugins/twitter/templates/source/message/formatted/BodyFormatted.html", 'crm');
    }

    protected function prepareBody($body)
    {
        //Convert urls to <a> links
        $body = preg_replace("/([\w]+\:\/\/[\w-?&;#~=\.\/\@]+[\w\/])/", '<a target="_blank" href="$1">$1</a>', $body);
        //Convert hashtags to twitter searches in <a> links
        $body = preg_replace("/#([A-Za-z0-9\/\.]*)/", '<a target="_blank" href="https://twitter.com/hashtag/$1?src=hash">#$1</a>', $body);
        //Convert attags to twitter profiles in &lt;a&gt; links
        $body = preg_replace("/@([A-Za-z0-9\/\.]*)/", '<a target="_blank" href="http://twitter.com/$1">@$1</a>', $body);
        return $body;
    }
}