<?php

/**
 * Returns HTML for reminders and messages to notify user on any backend page.
 */
class crmPopupController extends crmJsonController
{
    public $funnels = null;

    public function execute()
    {
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();
        $this->funnels = $fsm->withStages($fm->getAllFunnels(true));

        $this->response = array_merge($this->getMessages(), $this->getReminders());
    }

    protected function getReminders()
    {
        $reminder_model = new crmReminderModel();
        $reminders = $reminder_model->getUpcoming();

        $response = array();

        $pop_up_disabled = wa()->getUser()->getSettings('crm', 'reminder_pop_up_disabled');
        /*
        *  Values for $pop_up_disabled:
        *  0 = enabled pop_ups (default);
        *  1 = disabled pop_ups.
        */
        if ($pop_up_disabled != 1) {
            foreach ($reminders as $reminder) {

                switch ($reminder['type']) {
                    case 'CALL':
                        $icon = 'icon16 phone';
                        $action = '<span class="notifi-type">'._w('Call').'</span>';
                        break;
                    case 'MESSAGE':
                        $icon = 'icon16 email';
                        $action = '<span class="notifi-type">'._w('Message').'</span>';
                        break;
                    case 'MEETING':
                        $icon = 'icon16 cup';
                        $action = '<span class="notifi-type">'._w('Meeting').'</span>';
                        break;
                    default:
                        $icon = '';
                        $action = '';
                        break;
                }

                $url = wa()->getAppUrl('crm').'reminder/show/'.$reminder['id'];
                $reminder_id = $reminder['id'];
                $content = $reminder['content'];
                $time_left = $this->timeLeft($reminder['due_datetime']);

                $contact_id = $reminder['contact_id'];
                // Related contact
                if ($contact_id > 0) {
                    $contact = new waContact($contact_id);
                    $contact_show = '<i class="icon16 userpic20" style="background-image: url('.$contact->getPhoto('20').');"></i> <a href="'.wa()->getAppUrl('crm').'contact/'.$contact['id'].'" class="notifi-link">'.htmlspecialchars($contact['name']).'</a>';
                } // Related Deal
                elseif ($contact_id < 0) {
                    $dm = new crmDealModel();
                    $deal = $dm->getDeal(abs($contact_id));
                    try {
                        $contact = new crmContact($deal['contact_id']);

                        $funnel = $this->funnels[$deal['funnel_id']];
                        $stage = $this->funnels[$deal['funnel_id']]['stages'][$deal['stage_id']];

                        $funnel_name = $funnel['name'];
                        $stage_name = $stage['name'];
                        $color = $stage['color'];
                        $contact_show = '
                        <i class="icon16 userpic20" title="'.htmlspecialchars($contact['name']).'" style="background-image: url('.$contact->getPhoto('20').');"></i>
                        '.$this->getPoligonSvg($funnel_name, $stage_name, $color).'
                        <a href="'.wa()->getAppUrl('crm').'deal/'.$deal['id'].'" class="notifi-link">'.htmlspecialchars($deal['name']).'</a>';
                    } catch (Exception $e) {
                        $contact_show = null;
                    }
                } else {
                    $contact_show = null;
                }

                $tmz = wa()->getUser()->get('timezone');
                $tmz = (empty($tmz) ? waRequest::cookie('tz') : $tmz);
                $reminder_time = waDateTime::date('H:i', $reminder['due_datetime'], $tmz);

                $body = '<div class="c-notifi-body">
                            '.$contact_show.'
                            <div class="c-notifi-content">
                            <i class="'.$icon.'"></i>
                            <span class="c-notifi-action">'.$action.'</span>
                                <a href="'.$url.'" class="c-notifi-link">'.htmlspecialchars($content).'</a>
                            </div>
                            <div class="c-notifi-timeLeft">'.$time_left.'</div>
                          </div>';

                $html = '<div class="crm-notification-popup" data-item="reminder-'.$reminder_id.'">
                            <div class="c-notifi-head">
                                <i class="icon16 clock"></i>
                                <span class="c-notifi-title">'._w('Reminder').' '.$reminder_time.'</span>
                                <i class="icon16 c-notify-close" title="'._w('Close').'"></i>
                            </div>
                            <div class="c-notifi-link">
                                '.$body.'
                            </div>
                         </div>';

                $response[] = array(
                    'type' => 'reminder',
                    'id'   => $reminder['id'],
                    'html' => $html,
                );
            }
        }

        return $response;
    }

    protected function getMessages()
    {
        $messages = $this->getMessageModel()->getNew();
        $m_ids = $messages_params = $attachment_ids = $attachments = $contact_ids = $contacts = array();
        foreach ($messages as &$m) {
            $m['params'] = $m['contact'] = $m['attachments'] = array();
            $m_ids[] = $m['id'];
            $contact_ids[] = $m['contact_id'];
        }
        unset($m);

        $messages_params = $this->getMessageParamsModel()->getParamsByMessage($m_ids);

        // Get files ids for all messages, by crmMessageAttachmentsModel
        $attachment_ids = $this->getMessageAttachmentsModel()->getByField('message_id', $m_ids, 'file_id');
        // Get all files for all messages
        $files = $this->getFileModel()->getById(array_keys($attachment_ids));

        $collection = new crmContactsCollection('/id/'.join(',', $contact_ids));
        $contacts = $collection->getContacts('name,photo_url_20');

        $response = array();
        foreach ($messages as $id => $message) {
            $message['params'] = $messages_params[$id];

            $contact = ifset($contacts[$message['contact_id']]);
            $message['contact'] = $contact;

            // Add attachments for this message
            foreach ($attachment_ids as $a) {
                if ($a['message_id'] == $message['id'] && isset($files[$a['file_id']])) {
                    $file = $files[$a['file_id']];
                    $message['attachments'][$file['id']] = $file;
                }
            }

            $deal = $poligon_svg = null;
            if ($message['deal_id']) {
                $deal = $this->getDealModel()->getDeal($message['deal_id']);
                if ($deal) {
                    $funnel = $this->funnels[$deal['funnel_id']];
                    $stage = $this->funnels[$deal['funnel_id']]['stages'][$deal['stage_id']];

                    $funnel_name = $funnel['name'];
                    $stage_name = $stage['name'];
                    $color = $stage['color'];

                    $poligon_svg = $this->getPoligonSvg($funnel_name, $stage_name, $color);
                }
            }

            $message['icon_url'] = null;
            $message['icon'] = 'exclamation';
            $message['transport_name'] = _w('Unknown');

            if ($message['transport'] == crmMessageModel::TRANSPORT_EMAIL) {
                $message['icon'] = 'email';
                $message['transport_name'] = 'Email';
            } elseif ($message['transport'] == crmMessageModel::TRANSPORT_SMS) {
                $message['icon'] = 'mobile';
                $message['transport_name'] = 'SMS';
            }

            if ($message['source_id']) {
                $source_helper = crmSourceHelper::factory(crmSource::factory($message['source_id']));
                $res = $source_helper->workupMessagePopupItem($message);
                $message = $res ? $res : $message;
            }

            $template = wa()->getAppPath("templates/actions-legacy/popup/PopupMessage.html", 'crm');
            $assign = array(
                'message'     => $message,
                'contact'     => $contact,
                'deal'        => $deal,
                'poligon_svg' => $poligon_svg,
            );
            $html = $this->renderTemplate($template, $assign);

            $response[] = array(
                'type' => 'message',
                'id'   => $message['id'],
                'html' => $html,
            );
        }

        return $response;
    }

    protected function timeLeft($date_time)
    {
        $a = strtotime($date_time);
        $b = time();
        $min = round(($a - $b) / 60);

        if ($min < 1) {
            return _w('Due now');
        } else {
            return _w('in %d minute', 'in %d minutes', $min);
        }
    }

    protected function getPoligonSvg($funnel_name, $stage_name, $color)
    {
        list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
        $rgb = "$r, $g, $b";

        $template = wa()->getAppPath("templates/actions/popup/PoligonSvg.html", 'crm');
        $assign = array(
            'funnel_name' => $funnel_name,
            'stage_name'  => $stage_name,
            'rgb'         => $rgb,
        );
        return $this->renderTemplate($template, $assign);
    }
}
