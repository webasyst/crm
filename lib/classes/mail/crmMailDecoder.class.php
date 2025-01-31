<?php

class crmMailDecoder extends waMailDecode
{
    protected function cleanHTML($html_orig)
    {
        // clean broken html possible case like <table ... role=""presentation" ...>
        $html = preg_replace("~(<[^><]+\s\w+=\")\"(\w*\"[^><]*>)~is", '\1\2', $html_orig);
        return parent::cleanHTML($html);
    }
    
    protected function decodePart($part)
    {
        if ($part['type'] === self::TYPE_ATTACH) {
            $this->decodeAttachPart($part);
        } else {
            parent::decodePart($part);
        }
    }

    protected function decodeAttachPart($part)
    {
        if ($this->options['max_attachments'] >= 0 && count($this->attachments) >= $this->options['max_attachments']) {
            $this->state = self::STATE_END;
            return;
        }
        $boundary = "\n--".$part['boundary'];
        if (!file_exists($this->options['attach_path'])) {
            waFiles::create($this->options['attach_path']);
        }
        $path = $this->options['attach_path'].(count($this->attachments) + 1);
        if (isset($this->part['params']['name'])) {
            if (($i = strrpos($this->part['params']['name'], '.')) !== false) {
                $path .= substr($this->part['params']['name'], $i);
            }
        } elseif ($this->part['type'] == 'image' && in_array($this->part['subtype'], array('gif', 'jpg', 'png'))) {
            $path .= '.'.$this->part['subtype'];
        }
        $attach = array(
            'file' => basename($path)
        );
        if (isset($this->part['params']['name'])) {
            $attach['name'] = $this->part['params']['name'];
        }
        $attach['type'] = $this->part['type'];
        if (isset($this->part['subtype']) && $this->part['subtype']) {
            $attach['type'] .= '/'.$this->part['subtype'];
        }
        if (isset($this->part['headers']['content-id'])) {
            $attach['content-id'] = $this->part['headers']['content-id'];
            if (substr($attach['content-id'], 0, 1) == '<') {
                $attach['content-id'] = substr($attach['content-id'], 1);
            }
            if (substr($attach['content-id'], -1) == '>') {
                $attach['content-id'] = substr($attach['content-id'], 0, -1);
            }
        }
        if (isset($this->part['headers']['content-disposition'])) {
            $attach['content-disposition'] = $this->part['headers']['content-disposition'];
        }
        $this->attachments[] = $attach;
        unset($attach);
        $fp = fopen($path, "w+");
        if (isset($this->part['headers']['content-transfer-encoding'])) {
            if ($this->part['headers']['content-transfer-encoding'] == 'base64') {
                stream_filter_append($fp, "convert.base64-decode", STREAM_FILTER_WRITE);
            } elseif ($this->part['headers']['content-transfer-encoding'] == 'quoted-printable') {
                stream_filter_append($fp, "convert.quoted-printable-decode", STREAM_FILTER_WRITE);
            }
        }
        while (($i = strpos($this->buffer, $boundary, $this->buffer_offset)) === false && !$this->is_last) {
            fwrite($fp, $this->buffer_offset ? substr($this->buffer, $this->buffer_offset) : $this->buffer);
            $this->buffer = '';
            $this->buffer_offset = 0;
            $this->read();
        }
        // if last part
        if ($i === false) {
            // try find incorrect boundary end
            if (substr(rtrim($this->buffer, "\r\n"), -2) == '--') {
                $j = strrpos(rtrim($this->buffer, "\r\n"), "\n");
                $this->buffer = rtrim(substr($this->buffer, 0, $j), "\r\n");
            }
            // write part to attach file
            fwrite($fp, substr($this->buffer, $this->buffer_offset));
            $this->buffer = '';
            $this->buffer_offset = 0;
            $this->state = self::STATE_END;
        } else {
            fwrite($fp, substr($this->buffer, $this->buffer_offset, $i - $this->buffer_offset));
            $this->buffer_offset = $i;
            $this->state = self::STATE_PART;
        }
        fclose($fp);
        if (!isset($this->part['headers']['content-disposition'])) {
            $this->body[$this->part['type']."/".$this->part['subtype']] = file_get_contents($path);
        }
        if (isset($this->part['parent'])) {
            $this->part_index = $this->part['parent'];
            $this->part = &$this->parts[$this->part['parent']];
        }
    }
}
