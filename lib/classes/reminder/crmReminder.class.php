<?php

class crmReminder
{
    public static function sendNotification($reminder, $to, $template, $subject_prefix = '')
    {
        if (waConfig::get('is_template')) {
            return;
        }
        $res = true;

        $old_locale = $last_locale = wa()->getLocale();

        foreach ($to as $contact_id) {

            $c = new waContact($contact_id);
            if (!$c->get('email', 'default')) {
                continue;
            }

            $locale = $c->getLocale() == 'ru_RU' ? 'ru_RU' : 'en_US';
            if ($locale != $last_locale) {
                $last_locale = $locale;
                wa()->setLocale($locale);
            }
            $path = wa('crm')->getConfig()->getAppPath('lib/config/data/templates/'.$template.'.'.$locale.'.html');

            if (file_exists($path)) {
                $notification = file_get_contents($path);

                $link = wa()->getUrl(true);
                $link_name = '';
                if (!empty($reminder['contact_id'])) {
                    if ($reminder['contact_id'] > 0) {
                        $link .= 'contact/'.$reminder['contact_id'].'/';
                        $client = new waContact($reminder['contact_id']);
                        $link_name = htmlspecialchars($client->getName());
                    } else {
                        $link .= 'deal/'.abs($reminder['contact_id']).'/';
                        $dm = new crmDealModel();
                        if ($deal = $dm->getById(abs($reminder['contact_id']))) {
                            $link_name = htmlspecialchars($deal['name']);
                        }
                    }
                } else {
                    $link .= 'reminder/show/'.$reminder['id'].'/';
                }
                $date = $reminder['due_datetime']
                    ? wa_date('datetime', $reminder['due_datetime'])
                    : wa_date('date', $reminder['due_date']);
                $vars = array(
                    '{CONTACT_NAME}'      => htmlspecialchars($c->getName()),
                    '{USER_NAME}'         => htmlspecialchars(wa()->getUser()->getName()),
                    '{LINK}'              => $link,
                    '{LINK_NAME}'         => $link_name ? $link_name : $link,
                    '{REMINDER_CONTENT}'  => nl2br(htmlspecialchars((string) $reminder['content'])),
                    '{REMINDER_DUE_DATE}' => $date,
                    '{ACCOUNT_NAME}'      => wa()->accountName(),
                );
                $content = str_replace(array_keys($vars), array_values($vars), $notification);

                $subject = $subject_prefix.self::cutString(strip_tags((string) $reminder['content']));

                // Send message
                try {
                    $mailer = new waMailMessage($subject, $content, 'text/html');
                    $mailer->setTo($c->get('email', 'default'));
                    if (!$mailer->send()) {
                        $res = false;
                    }
                } catch (waException $e) {
                    $res = false;
                }
            }
        }
        wa()->setLocale($old_locale);
        return $res;
    }

    protected static function cutString($str, $length = 64)
    {
        if (mb_strlen($str, 'UTF-8') <= $length) {
            return $str;
        }
        $tmp = mb_substr($str, 0, $length, 'UTF-8');
        return mb_substr($tmp, 0, mb_strripos($tmp, ' ', 0, 'UTF-8'), 'UTF-8').'...';
    }
}
