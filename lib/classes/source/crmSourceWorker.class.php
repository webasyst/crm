<?php

abstract class crmSourceWorker
{
    static protected $LOG_FILE = 'crm/source_worker.log';
    static protected $PARAM_WORKER_LOCK = 'worker_lock';
    static protected $PARAM_WORKER_DATETIME = 'worker_datetime';
    static protected $LOCK_EXPIRATION = '3min';

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

    /**
     * crmSourceWorker constructor.
     * @param crmSource $source
     * @param array $options
     */
    protected function __construct(crmSource $source, array $options = array())
    {
        $this->source = $source;
        $this->options = $options;
    }

    /**
     * Interface for cli
     * @param array $options
     */
    public static function cliRun(array $options = array())
    {
        $sm = new waAppSettingsModel();

        $sm->set('crm', 'source_worker_start', date('Y-m-d H:i:s'));

        try {
            self::doAllSourcesWork($options);
        } catch (Exception $e) {
            $message = join(PHP_EOL, array(
                'Exception',
                $e->getMessage(),
                $e->getTraceAsString()
            ));
            waLog::log($message, self::$LOG_FILE);
        }

        $sm->set('crm', 'source_worker_end', date('Y-m-d H:i:s'));
        if (wa()->getEnv() === 'cli') {
            $sm->set('crm', 'source_worker_cli_done', date('Y-m-d H:i:s'));
        }

        // temp table cleaning
        $tm = new crmTempModel();
        $tm->clean();
    }

    public static function getLastCliRunDateTime()
    {
        $sm = new waAppSettingsModel();
        return $sm->get('crm', 'source_worker_cli_done');
    }

    public static function isCliOk()
    {
        return !!self::getLastCliRunDateTime();
    }

    protected static function getClassByType($type = '')
    {
        $type = strtolower((string)trim($type));
        if (strlen($type) <= 0) {
            return null;
        }
        $part_of_name = ucfirst($type);
        $class_name = "crm{$part_of_name}SourceWorker";
        if (!class_exists($class_name)) {
            return null;
        }
        return $class_name;
    }

    /**
     * @param array $options
     * @return array
     */
    protected static function doAllSourcesWork(array $options = array())
    {
        $max_execution_time = wa('crm')->getConfig()->getMaxExecutionTime();

        /**
         * @event start_do_all_sources_work
         * @param array $params
         * @param array[]array $params['options']
         */
        $params = array(
            'options' => $options
        );
        wa('crm')->event('start_do_all_sources_work', $params);

        $start_time = time();

        if (isset($options['sources']) && is_array($options['sources'])) {
            $sources = $options['sources'];
        } else {
            $sources = self::getSourceIds($options);
        }

        if (!$sources) {
            return array();
        }

        foreach ($sources as $index => &$source) {
            if (wa_is_int($source)) {
                $source = crmSource::factory($source);
            } elseif (is_array($source) && isset($source['id']) && wa_is_int($source['id'])) {
                $source = crmSource::factory($source['id']);
            }
            if (!($source instanceof crmSource) || self::isSourceLocked($source)) {
                unset($sources[$index]);
            }
        }
        unset($source);

        $result = array(
            'work_results' => array(),
            'timeout_break' => false
        );

        /**
         * @var crmSource[] $sources
         */
        foreach ($sources as $source) {

            $res = self::doSourceWork($source);
            $result['work_results'][$source->getId()] = $res;

            $elapsed_time = time() - $start_time;
            if ($max_execution_time !== null && $elapsed_time > $max_execution_time - 5) {
                // to long time work, go to break
                $result['timeout_break'] = true;
                break;
            }
        }

        return $result;
    }

    protected static function isSourceLocked(crmSource $source)
    {
        $when_expired = strtotime("-" . self::$LOCK_EXPIRATION);
        $lock = $source->getParam(self::$PARAM_WORKER_LOCK, null, true);
        return $lock && strtotime($lock) >= $when_expired;
    }

    protected static function lockSource(crmSource $source)
    {
        // Only update params, do not save the whole source
        $source_params_model = new crmSourceParamsModel();
        $source_params_model->set([$source->getId()], [
            self::$PARAM_WORKER_LOCK => date("Y-m-d H:i:s"),
            self::$PARAM_WORKER_DATETIME => date("Y-m-d H:i:s"),
        ], false);
    }

    protected static function unlockSource(crmSource $source)
    {
        // At this point we save the whole source, not only params.
        // All modifications to source data during worker process
        // should also be saved to DB.
        $source->saveParams([
            self::$PARAM_WORKER_DATETIME => date("Y-m-d H:i:s"),
            self::$PARAM_WORKER_LOCK => null,
        ], false);
    }

    /**
     * @param crmSource $source
     * @param array $process
     * @return mixed
     */
    public static function doSourceWork(crmSource $source, array $process = array())
    {
        if (self::isSourceLocked($source)) {
            return null;
        }
        self::lockSource($source);
        $result = self::doSourceWorkItself($source, $process);
        self::unlockSource($source);
        return $result;
    }

    protected static function doSourceWorkItself(crmSource $source, array $process = array())
    {
        if (!$source->canWork()) {
            return null;
        }
        $worker = self::getWorkerInstance($source);
        if (!$worker) {
            return null;
        }
        if (!$worker->isWorkToDo($process)) {
            return $process;
        }
        return $worker->doWork($process);
    }


    protected static function getWorkerInstance(crmSource $source)
    {
        self::$runtime_cache['worker_instances'] = (array)ifset(self::$runtime_cache['worker_instances']);
        $hash = spl_object_hash($source);
        if (array_key_exists($hash, self::$runtime_cache['worker_instances'])) {
            return self::$runtime_cache['worker_instances'][$hash];
        }

        $cache = &self::$runtime_cache['worker_instances'][$hash];

        $class_name = self::getClassByType($source->getType());
        if (!$class_name) {
            return $cache = null;
        }

        if (method_exists($class_name, 'factory')) {
            $object = call_user_func(array($class_name, 'factory'), $source);
        } else {
            $object = new $class_name($source);
        }
        if (!($object instanceof crmSourceWorker)) {
            return $cache = null;
        }
        return $cache = $object;
    }

    /**
     * @param array $options
     * @return array
     */
    protected static function getSourceIds(array $options = array())
    {
        $sm = new crmSourceModel();
        $spm = new crmSourceParamsModel();

        $source_table = $sm->getTableName();
        $source_params_table = $spm->getTableName();

        $when_expired = date('Y-m-d H:i:s', strtotime("-" . self::$LOCK_EXPIRATION));

        $where = "source.type NOT IN('FORM', 'SHOP') AND ";

        $source_types = ifset($options['source_types']);
        if ($source_types) {
            $source_types = crmHelper::toStrArray($source_types);
            if (!empty($source_types)) {
                $source_types = array_map('strtoupper', $source_types);
                $where = 'source.type IN (:types) AND ';
            }
        }

        $sql = "SELECT source.id
                FROM `{$source_table}` source
                LEFT JOIN `{$source_params_table}` `lock` ON source.id = `lock`.source_id AND `lock`.name = :worker_lock
                LEFT JOIN `{$source_params_table}` `datetime` ON source.id = `datetime`.source_id AND `datetime`.name = :worker_datetime
                WHERE {$where} ( (source.type = 'EMAIL' && source.disabled = '0') || source.type != 'EMAIL' ) AND (`lock`.value IS NULL OR `lock`.value < :when_expired)
                ORDER BY `datetime`.value
                LIMIT 10";

        return $sm->query($sql, array(
            'types' => $source_types,
            'worker_lock' => self::$PARAM_WORKER_LOCK,
            'worker_datetime' => self::$PARAM_WORKER_DATETIME,
            'when_expired' => $when_expired
        ))->fetchAll(null, true);
    }

    /**
     * @override
     * @param array $process
     * @return bool
     */
    public function isWorkToDo(array $process = array())
    {
        return false;
    }

    /**
     * Do work
     * @override
     * @param array $process some process params, initial or from previous step of work
     * @return array
     */
    public function doWork(array $process = array())
    {
        // override it
        return $process;
    }
}
