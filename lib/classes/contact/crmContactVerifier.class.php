<?php

class crmContactVerifier
{
    const CACHE_KEY = 'message/verification/';

    public static function createVerificationHash($contact_id, $message_id)
    {
        $key = waUtils::getRandomHexString(16);
        $value = waUtils::getRandomHexString(64);

        (new crmTempModel)->save(self::getCacheKey($key, $message_id), [
            'value' => $value,
            'contact_id' => $contact_id,
        ]);

        return $key.':'.$value;
    }

    public static function verify($hash, $message_id)
    {
        $parts = explode(':', $hash);
        if (count($parts) != 2) {
            return null;
        }
        $key = $parts[0];
        $value = $parts[1];
        
        $tm = new crmTempModel();
        $hash = self::getCacheKey($key, $message_id);
        $data = $tm->getByHash($hash);

        if (ifset($data, 'data', 'value', null) === $value) {
            $tm->deleteByHash($hash);
            return ifset($data, 'data' , 'contact_id', 0);
        }
        return null;
    }

    protected static function getCacheKey($key, $message_id)
    {
        return self::CACHE_KEY . $message_id . '/' . $key;
    }
}

