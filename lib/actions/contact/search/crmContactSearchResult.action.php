<?php

class crmContactSearchResultAction extends crmContactsAction
{
    public function getHash()
    {
        $hash = $this->getParameter('hash', '', waRequest::TYPE_STRING_TRIM);

        $hash = crmHelper::fixPlusSymbolAsPrefixInPhone($hash);
        $hash = crmHelper::urlDecodeSlashes($hash);

        if ($this->isAdvancedSearchHash($hash)) {
            return 'crmSearch/' . $hash;
        } else {
            return $hash;
        }
    }

    protected function isAdvancedSearchHash($hash)
    {
        $workaround_slash_symbol_replacer = uniqid('REPLACER');
        $hash = str_replace('\/', $workaround_slash_symbol_replacer, $hash);

        // has some prefix with slash, that means already it is some hash for collection (like search/ or ids/ etc)
        if (strpos($hash, '/') !== false) {
            return false;
        }

        return true;
    }
}
