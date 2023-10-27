<?php
/**
 * Any plugin implementing telephony for CRM
 * must have descendant of this class defined with name
 * `crm<Pluginid>PluginTelephony`
 */
abstract class crmPluginTelephony
{
    protected $plugin_config;

    public function __construct($plugin_config)
    {
        $this->plugin_config = $plugin_config;
    }

    /**
     * List all possible numbers that can appear as crm_call.plugin_user_number
     * This is used in Settings -> PBX screen to assign numbers to backend users.
     *
     * It is generally OK for this method to be slow (e.g. make an API call).
     *
     * @return array id => human-readable representation
     */
    public function getNumbers()
    {
        return array();
    }

    /**
     * Called when a suspiciously long call is detected.
     * Plugin should determine valid status of the call,
     * and return array of fields of `crm_call` table to update.
     *
     * Return null to ignore and do nothing with it.
     * Throwing exception here will result in marking call as finished.
     */
    public function checkZombieCall($call)
    {
        // override
    }

    /**
     * Search contacts by `crm_call.plugin_client_number`
     *
     * Default implementation looks contacts by phone number, which is good in most cases.
     * If plugin identifies clients differently (e.g. by a custom instant messenger account name),
     * this should be overriden.
     *
     * Plugin must not perform any access rights checking here.
     *
     * @param string $plugin_client_number  Whatever plugin wrote to `crm_call.plugin_client_number`
     * @param int    $limit                 Do not return more than this number of records
     * @return array                        id => contact data as returned by crmContactsCollection
     */
    public function findClients($plugin_client_number, $limit=5)
    {
        $plugin_client_number = str_replace(array('-', '(', ')', ' ', '+'), '', $plugin_client_number);
        if(strlen($plugin_client_number) == 11 && in_array($plugin_client_number[0], array('8', '7'))) {
            $condition = '$='.substr($plugin_client_number, 1);
        } else {
            $condition = '='.$plugin_client_number;
        }

        $collection = new crmContactsCollection('search/phone'.$condition);
        return $collection->getContacts(null, 0, $limit);
    }

    /**
     * Format plugin_client_number into human-readable representation.
     * @param $plugin_number string
     * @return string
     */
    public function formatClientNumber($plugin_number)
    {
        class_exists('waContactPhoneField');
        $formatter = new waContactPhoneFormatter();
        $plugin_number = str_replace(str_split("+-() \n\t"), '', $plugin_number);
        return $formatter->format($plugin_number);
    }

    /**
     * Format plugin_user_number into human-readable representation.
     * @param $plugin_number string
     * @return string
     */
    public function formatUserNumber($plugin_number)
    {
        return $this->formatClientNumber($plugin_number);
    }

    /**
     * Returns href attribute value for link to download call record.
     * $call is a DB row from `crm_call` with non-empty `plugin_record_id`.
     *
     * Alternatively, may return an array of attributes for `<a>` element.
     *
     * This function is called in a loop, it must be fast and not call any API.
     * This can return 'javascript:' URLs to do necessary processing in JS,
     * with ajax calls if needed.
     *
     * (Use `backend_assets` event to add JS files to backend pages.)
     */
    public function getRecordHref($call)
    {
        return '';
    }

    /**
     * Returns the possibility of redirecting a call to another user or to another number
     *
     * Should work for calls with the status of «Pending» and «Connected»
     *
     * This function is called in a loop, it should be fast and do not call any API.
     *
     * @param $call
     * @return bool
     */
    public function isRedirectAllowed($call)
    {
        return false;
    }

    /**
     * Returns candidates to redirect the call.
     *
     * Can use API.
     *
     * The current user should be excluded from the result.
     * @param $call
     * @return array
     */
    public function getRedirectCandidates($call)
    {
        return array();
    }

    /**
     * @param $call
     * @param string $number - Number to which the call will be redirected.
     */
    public function redirect($call, $number)
    {
        // override
    }

    /**
     * Returns a flag: can the plugin create a new outgoing call via api
     *
     * This function is called in a loop, it should be fast and must not call any API.
     *
     * @return bool
     */
    public function isInitCallAllowed()
    {
        return false;
    }

    /**
     * Method of initializing outgoing calls via api
     * @param string $number_from - internal number of the employee
     * @param string $number_to   - number of the client to which the call is made
     * @param array $call
     */
    public function initCall($number_from, $number_to, $call)
    {
        // override
    }

    public function getId()
    {
        return $this->plugin_config['id'];
    }

    public function getName()
    {
        return ifset($this->plugin_config['telephony_name'], $this->plugin_config['name']);
    }

    public function getIcon()
    {
        return wa()->getConfig()->getRootUrl().$this->plugin_config['img'];
    }

    protected function getPbxModel()
    {
        $pm = new crmPbxModel();
        return $pm;
    }

    protected function getPbxUsersModel()
    {
        $pum = new crmPbxUsersModel();
        return $pum;
    }

    protected function getPbxParamsModel()
    {
        $ppm = new crmPbxParamsModel();
        return $ppm;
    }

    /**
     * @param $plugin_call_id
     * @param $plugin_record_id
     * @return null
     */
    public function getRecordUrl($plugin_call_id, $plugin_record_id)
    {
        return null;
    }
}
