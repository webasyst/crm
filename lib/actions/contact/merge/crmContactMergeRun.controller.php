<?php

class crmContactMergeRunController extends waLongActionController
{
    protected $log_filename = 'crm/merge_contacts/controller.log';

    protected $max_fails_count = 5;

    /**
     * @var crmContactsMerger
     */
    protected $merger;

    public function __construct()
    {
        if (!wa()->getUser()->getRights('crm', 'edit')) {
            throw new waRightsException(_w('Access denied'));
        }
    }

    /**
     * @return int
     * @throws waException
     */
    protected function getMasterId()
    {
        if (!isset($this->data['master_id'])) {
            $this->data['master_id'] = (int)$this->getRequest()->post('master_id');
            if (!$this->data['master_id']) {
                throw new waException('No contact to merge into.');
            }
        }
        return $this->data['master_id'];
    }

    /**
     * @return int[]
     * @throws waException
     */
    protected function getSlaveIds()
    {
        if (!isset($this->data['slave_ids'])) {
            $this->data['slave_ids'] = crmHelper::toIntArray($this->getRequest()->post('slave_ids'));
            $this->data['slave_ids'] = crmHelper::dropNotPositive($this->data['slave_ids']);
            $master_id = $this->getMasterId();
            $this->data['slave_ids'] = array_diff($this->data['slave_ids'], array($master_id));
            if (!$this->data['slave_ids']) {
                throw new waException('No contacts to merge.');
            }
        }
        return $this->data['slave_ids'];
    }

    /**
     * @param null|string $hash
     * @return crmContactsMerger
     */
    protected function getMerger($hash = null)
    {
        if ($this->merger !== null) {
            return $this->merger;
        }
        $options = array(
            'process_id' => $this->processId,
        );
        if ($hash !== null) {
            $options['hash'] = $hash;
            $options['master_id'] = $this->getMasterId();
        }
        return $this->merger = new crmContactsMerger($options);
    }

    /**
     * @return string
     */
    protected function getHash()
    {
        return 'id/' . join(',', $this->getSlaveIds());
    }

    /**
     * Initializes new process.
     * Runs inside a transaction ($this->data and $this->fd are accessible).
     */
    protected function init()
    {
        // first init merger
        $this->getMerger($this->getHash());
    }

    /**
     * Checks if there is any more work for $this->step() to do.
     * Runs inside a transaction ($this->data and $this->fd are accessible).
     *
     * $this->getStorage() session is already closed.
     *
     * @return boolean whether all the work is done
     */
    protected function isDone()
    {
        if (isset($this->data['fails_count']) && wa_is_int($this->data['fails_count']) && $this->data['fails_count'] > $this->max_fails_count) {
            return true;
        }
        return $this->getMerger()->isMergeDone();
    }

    /**
     * Performs a small piece of work.
     * Runs inside a transaction ($this->data and $this->fd are accessible).
     * Should never take longer than 3-5 seconds (10-15% of max_execution_time).
     * It is safe to make very short steps: they are batched into longer packs between saves.
     *
     * $this->getStorage() session is already closed.
     * @return boolean false to end this Runner and call info(); true to continue.
     * @throws waException
     */
    protected function step()
    {
        if (!$this->isDone()) {
            try {
                $this->getMerger()->mergeChunk(100);
            } catch (waException $e) {
                $this->logError($e);
                $this->logError(array(
                    '$this->data' => $this->data
                ));
                $this->data['fails_count'] = isset($this->data['fails_count']) ? (int)$this->data['fails_count'] : 0;
                $this->data['fails_count']++;
            }
        }
    }

    /**
     * Called when $this->isDone() is true
     * $this->data is read-only, $this->fd is not available.
     *
     * $this->getStorage() session is already closed.
     *
     * @param $filename string full path to resulting file
     * @return boolean true to delete all process files; false to be able to access process again.
     */
    protected function finish($filename)
    {
        $result = $this->getMerger()->getMergeResult();

        // Prepare UI message
        $total_count = $result['total_count'];
        $merged_count = $result['merged_count'];

        if ($merged_count > 0) {
            // plus one, means plus master contact
            $message = sprintf(_w("%s of %s contacts have been merged"), $merged_count + 1, $total_count + 1);
            $this->logAction("contact_merge", $merged_count + 1);
        } else {
            $message = _w("No contacts were merged");
        }

        $rest_count = $total_count - $merged_count;
        if ($rest_count > 0) {
            $message .= '<br />' . _w(
                "%d contact was skipped because they have user accounts",
                "%d contacts were skipped because they have user accounts",
                    $rest_count
            );
        }

        $master_id = $this->getMasterId();
        $master = new crmContact($master_id);

        $this->response(array(
            'processId' => $this->processId,
            'result' => $result,
            'master' => array(
                'id' => $master->getId(),
                'name' => $master->getName()
            ),
            'message' => $message,
            'ready' => true,
        ));

        return false;
    }

    /** Called by a Messenger when the Runner is still alive, or when a Runner
     * exited voluntarily, but isDone() is still false.
     *
     * This function must send $this->processId to browser to allow user to continue.
     *
     * $this->data is read-only. $this->fd is not available.
     */
    protected function info()
    {
        $this->response(array(
            'processId' => $this->processId,
            'ready' => false,
        ));
    }

    protected function response($response)
    {
        $this->getResponse()->addHeader('Content-Type', 'application/json');
        $this->getResponse()->sendHeaders();
        echo json_encode($response);
    }

    protected function logError($error)
    {
        if (is_array($error)) {
            $error = var_export($error, true);
        } elseif ($error instanceof Exception) {
            $error = join(PHP_EOL, array(
                $error->getMessage(),
                $error->getTraceAsString()
            ));
        } elseif (!is_scalar($error)) {
            $error = 'Unknown error of type: ' . gettype($error);
        }
        waLog::log($error, $this->log_filename);
    }
}
