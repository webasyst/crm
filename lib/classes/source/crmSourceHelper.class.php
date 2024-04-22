<?php

abstract class crmSourceHelper
{
    /**
     * @var array
     */
    protected static $runtime_cache = array();

    /**
     * @var array
     */
    protected $cache = array();

    /**
     * @var crmSource
     */
    protected $source;

    /**
     * @var array
     */
    protected $options;

    public function __construct(crmSource $source, array $options = array())
    {
        $this->source = $source;
        $this->options = $options;
    }

    /**
     * @param crmSource $source
     * @param array $options
     * @return crmSourceHelper|null
     */
    public static function factory(crmSource $source, array $options = array())
    {
        return self::getHelperInstance($source, $options);
    }

    /**
     * @override
     * @param $message
     * @return array
     */
    public function workupMessageInList($message)
    {
        // override it
        return $message;
    }

    /**
     * @override
     * @param array $message
     * @param array $log_item
     * @return array
     */
    public function workupMessageLogItemHeader($message, $log_item)
    {
        // override it
        return $log_item;
    }

    /**
     * @override
     * @param array $message
     * @return array
     */
    public function workupMessageLogItemBody($message)
    {
        // override it
        return $message;
    }

    /**
     * @override
     * @param array $message
     * @return array
     */
    public function workupMessagePopupItem($message)
    {
        // override it
        return $message;
    }

    /**
     * @override
     * @param $conversation
     * @return mixed
     */
    public function workupConversation($conversation)
    {
        // override it
        return $this->workupConversationInList($conversation);
    }

    public function workupConversationInList($conversation)
    {
        $transport_name = $this->source->getName();
        if (!empty($transport_name)) {
            $conversation['transport_name'] = $transport_name;
        }
        $conversation['icon_url'] = $this->source->getIcon();
        $fa_icon = $this->source->getFontAwesomeBrandIcon();
        if (ifset($fa_icon['icon_fab'])) {
            $conversation['icon_fab'] = $fa_icon['icon_fab'];
            $conversation['icon_color'] = $fa_icon['icon_color'];
        }
        return $conversation;
    }

    public function getUI20ConversationAuxItems($conversation)
    {
        return [
            'reply_form_dropdown_items' => [],
        ];
    }

    /**
     * @override
     * @param $conversation
     * @param $messages
     * @return mixed
     */
    public function workupMessagesInConversation($conversation, $messages)
    {
        // override it
        return $messages;
    }

    public function normalazeMessagesExtras($messages)
    {
        // override it
        return $messages;
    }

    public function getFeatures()
    {
        if (!empty($this->source) && $this->source->getType() == crmSourceModel::TYPE_EMAIL) {
            return [
                'html' => true,
                'attachments' => true,
                'images' => false,
            ];
        }
        return [
            'html' => false,
            'attachments' => false,
            'images' => false,
        ];
    }

    protected function addExtra($message, $extra_type, $value)
    {
        if (!isset($message['extras'])) {
            $message['extras'] = [];
        }
        if (!isset($message['extras'][$extra_type])) {
            $message['extras'][$extra_type] = [];
        }
        $message['extras'][$extra_type][] = $value;
        return $message;
    }

    protected function renderTemplate($template, $assign = array())
    {
        $view = wa()->getView();
        $old_vars = $view->getVars();
        $view->clearAllAssign();
        $view->assign($assign);
        $html = $view->fetch($template);
        $view->clearAllAssign();
        $view->assign($old_vars);
        return $html;
    }

    protected static function getHelperInstance(crmSource $source, $options = array())
    {
        self::$runtime_cache['page_instances'] = (array)ifset(self::$runtime_cache['page_instances']);
        $hash = spl_object_hash($source);
        if (array_key_exists($hash, self::$runtime_cache['page_instances'])) {
            return self::$runtime_cache['page_instances'][$hash];
        }

        $cache = &self::$runtime_cache['page_instances'][$hash];

        if (($source instanceof crmNullEmailSource) || ($source instanceof crmNullSource)) {
            return $cache = new crmNullSourceHelper($source);
        }

        $class_name = get_class($source);
        $class_name .= 'Helper';
        if (!class_exists($class_name)) {
            return $cache = new crmNullSourceHelper($source);
        }

        $object = new $class_name($source, $options);

        if (!($object instanceof crmSourceHelper)) {
            return $cache = new crmNullSourceHelper($source);
        }

        return $cache = $object;
    }
}
