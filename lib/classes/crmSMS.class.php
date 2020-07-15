<?php

class crmSMS extends waSMS
{
    /**
     * @var crmConfig
     */
    protected static $app_config;

    /**
     * @inheritDoc
     */
    public function send($to, $text, $from = null)
    {
        if (!is_scalar($to)) {
            return false;
        }
        $to = trim(strval($to));

        // phone variants to send sms, if send failed to other variant
        $phone_variants = [];

        $result_phone = $this->transformPhonePrefix($to);
        if ($result_phone) {
            $phone_variants[] = $result_phone;
        }
        $phone_variants[] = $to;

        // try sending, on first successful sent stops loop
        $sent = false;
        foreach ($phone_variants as $variant) {
            $sent = parent::send($variant, $text, $from);
            if ($sent) {
                break;
            }
        }
        return $sent;
    }

    /**
     * @param $to
     * @return string $phone - if phone successfully transformed (changed) return not empty phone, otherwise empty string
     * @throws waException
     */
    protected function transformPhonePrefix($to)
    {
        $prefix_setting = $this->getAppConfig()->getPhoneTransformPrefix();
        if ($prefix_setting['input_code'] && $prefix_setting['output_code']) {
            $len = strlen($prefix_setting['input_code']);
            if (substr($to, 0, $len) === $prefix_setting['input_code']) {
                $result_phone = '+' . $prefix_setting['output_code'] . substr($to, $len);
                if ($result_phone !== $to) {
                    return $result_phone;
                }
            }
        }
        return '';
    }

    /**
     * @return crmConfig
     * @throws waException
     */
    protected function getAppConfig()
    {
        if (self::$app_config instanceof crmConfig) {
            return self::$app_config;
        }
        self::$app_config = wa('crm')->getConfig();
        return self::$app_config;
    }
}
