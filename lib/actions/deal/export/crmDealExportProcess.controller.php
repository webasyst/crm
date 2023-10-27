<?php

class crmDealExportProcessController extends waLongActionController
{
    /**
     * @var crmDealsExporter
     */
    protected $deals_exporter;

    /**
     * @var crmContactsExporter
     */
    protected $contacts_exporter;

    /** Called only once when a new export is requested */
    protected function init()
    {
        $this->data = array(
            // Separator between fields
            'separator' => $this->getSeparator(),

            // encoding for output
            'encoding' => $this->getEncoding(),

            'export_fields_name' => $this->getRequest()->request('export_fields_name'),

            'not_export_empty_columns' => $this->getRequest()->request('not_export_empty_columns'),

            'deals_export_result' => array(),

            'contacts_export_result' => array(),

            'filename' => wa()->getTempPath('deal/'.$this->processId.'.csv', 'crm')
        );

        $ids = $this->getIds();

        $this->deals_exporter = $this->getDealsExporter($this->getIds());

        $dm = new crmDealModel();
        $contact_ids = $dm->select('DISTINCT contact_id')->where('id IN (:ids)', array('ids' => $ids))->fetchAll(null, true);

        $this->contacts_exporter = $this->getContactsExporter($contact_ids);

    }

    /**
     * @param $ids
     * @return crmDealsExporter
     */
    protected function getDealsExporter($ids = null)
    {
        if ($this->deals_exporter) {
            return $this->deals_exporter;
        }
        $options = array(
            'process_id' => $this->processId,
            'not_export_empty_columns' => $this->data['not_export_empty_columns'],
            'not_export_column_names' => !$this->data['export_fields_name'],
        );
        if ($ids !== null) {
            $options['ids'] = $ids;
        }
        return $this->deals_exporter = new crmDealsExporter($options);
    }

    /**
     * @param $ids
     * @return crmContactsExporter
     */
    protected function getContactsExporter($ids = null)
    {
        if ($this->contacts_exporter) {
            return $this->contacts_exporter;
        }
        $options = array(
            'process_id' => $this->processId,
            'not_export_empty_columns' => $this->data['not_export_empty_columns'],
            'not_export_column_names' => !$this->data['export_fields_name'],
        );
        if ($ids !== null) {
            $options['hash'] = 'id/' . join(',', $ids);
        }
        return $this->contacts_exporter = new crmContactsExporter($options);
    }


    protected function getIds()
    {
        $ids = $this->getRequest()->request('ids');
        $ids = crmHelper::toIntArray($ids);
        $ids = array_unique($ids);
        $ids = crmHelper::dropNotPositive($ids);

        $crm_rights = new crmRights();
        $ids = $crm_rights->dropUnallowedDeals($ids, [
            'level' => crmRightConfig::RIGHT_DEAL_ALL
        ]);

        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    protected function getEncoding()
    {
        $encoding = strtolower($this->getRequest()->post('encoding', 'utf-8', waRequest::TYPE_STRING_TRIM));
        $encodings = crmHelper::getImportExportEncodings();
        if (!isset($encodings[$encoding])) {
            $encoding = 'utf-8';
        }
        return $encoding;
    }

    protected function getSeparator()
    {
        $sep = $this->getRequest()->request('separator', waRequest::TYPE_STRING_TRIM, ';');
        return crmHelper::chooseLegalCsvSeparator($sep);
    }

    protected function encodeString($string)
    {
        $outputEncoding = $this->data['encoding'];
        if ($outputEncoding == 'utf-8') {
            return $string;
        }
        return @iconv('utf-8', $outputEncoding . '//IGNORE', $string);
    }

    protected function isDone()
    {
        return ifset($this->data['done']);
    }

    protected function step()
    {
        if ($this->isDone()) {
            return false;
        }
        if (!$this->isDealsExported()) {
            $this->exportDeals();
            return false;
        }
        if (!$this->isContactsExported()) {
            $this->exportContacts();
            return false;
        }

        $contacts_line_len = $this->data['contacts_export_line_length'];

        $line_i = 0;
        $chunk_size = 100;
        foreach ($this->data['deals_export_result'] as $key => $deal_line) {
            if ($key === 'fields') {
                $contact_line = $this->data['contacts_export_result']['fields'];
            } else {
                $contact_id = $deal_line['contact_id'];
                if (!isset($this->data['contacts_export_result'][$contact_id])) {
                    $contact_line = str_pad('', $contacts_line_len);
                } else {
                    $contact_line = $this->data['contacts_export_result'][$contact_id];
                }
            }

            unset($deal_line['contact_id']);
            $line = array_merge(array_values($deal_line), array_values((array) $contact_line));
            fputcsv($this->fd, $line, $this->data['separator']);

            unset($this->data['deals_export_result'][$key]);

            $line_i += 1;
            if ($line_i >= $chunk_size) {
                break;
            }
        }

        if (empty($this->data['deals_export_result'])) {
            $this->data['done'] = true;
        }

        return false;
    }

    protected function exportDeals()
    {
        $exporter = $this->getDealsExporter();
        if (!$exporter->isExportDone()) {
            $exporter->exportChunk(10);
            return false;
        }

        if (!$exporter->isExportResultGettingDone()) {
            foreach ($exporter->getExportResultChunk() as $key => $line) {
                foreach ($line as &$value) {
                    $value = $this->encodeString($value);
                }
                unset($value);
                $this->data['deals_export_result'][$key] = $line;
            }
        }
        return false;
    }

    protected function exportContacts()
    {
        $exporter = $this->getContactsExporter();
        if (!$exporter->isExportDone()) {
            $exporter->exportChunk(10);
            return false;
        }
        if (!$exporter->isExportResultGettingDone()) {
            foreach ($exporter->getExportResultChunk() as $key => $line) {
                foreach ($line as &$value) {
                    $value = $this->encodeString($value);
                }
                unset($value);
                $this->data['contacts_export_result'][$key] = $line;
                if (!isset($this->data['contacts_export_line_length'])) {
                    $this->data['contacts_export_line_length'] = count($line);
                }
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    protected function isDealsExported()
    {
        $exporter = $this->getDealsExporter();
        return $exporter->isExportDone() && $exporter->isExportResultGettingDone();
    }

    /**
     * @return bool
     */
    protected function isContactsExported()
    {
        $exporter = $this->getContactsExporter();
        return $exporter->isExportDone() && $exporter->isExportResultGettingDone();
    }

    private function getProgress()
    {
        if ($this->isDone()) {
            $progress = 100;
        } else {
            $progress_contacts = $this->getContactsExporter()->getCurrentProgress();
            $progress_deals = $this->getDealsExporter()->getCurrentProgress();
            $progress = ($progress_contacts + $progress_deals) / 2;
            $progress = min(round($progress), 100);
        }

        return $progress;
    }

    protected function info()
    {
        echo json_encode(array(
            'processId' => $this->processId,
            'ready' => false,
            'progress' => $this->getProgress()
        ));
    }


    /** Return file to browser */
    protected function finish($filename)
    {
        if (!$this->getRequest()->get('file') && !$this->getRequest()->post('file')) {
            // lost messenger
            echo json_encode(array(
                'processId' => $this->processId,
                'ready'     => true,
                'progress'  => $this->getProgress()
            ));
            return false;
        } elseif (wa()->whichUI('crm') == '1.3') {
            waFiles::readfile($filename, 'exported_deals.csv', false);
        } else {
            waFiles::copy($filename, $this->data['filename']);
            echo json_encode([
                'processId' => $this->processId,
                'ready'     => true,
                'progress'  => $this->getProgress(),
                'file'      => basename($this->data['filename'])
            ]);
        }

        return true;
    }
}
