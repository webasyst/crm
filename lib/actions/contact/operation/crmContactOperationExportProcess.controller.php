<?php

class crmContactOperationExportProcessController extends waLongActionController
{
    /**
     * @var crmContactsExporter
     */
    protected $exporter;

    /** Called only once when a new export is requested */
    protected function init() {
        if (!$this->getUser()->getRights('crm', 'export')) {
            throw new waRightsException();
        }
        
        $this->data = array(
            // Separator between fields
            'separator' => $this->getSeparator(),

            // encoding for output
            'outputEncoding' => $this->getEncoding(),

            // need include fields names
            'export_fields_name' => $this->getRequest()->request('export_fields_name'),

            // not export empty columns flag
            'not_export_empty_columns' => $this->getRequest()->request('not_export_empty_columns'),

            'filename' => wa()->getTempPath('contact/'.$this->processId.'.csv', 'crm')
        );

        $this->exporter = $this->getExporter($this->getHash());
    }

    /**
     * @param $hash
     * @return crmContactsExporter
     */
    protected function getExporter($hash = null)
    {
        if ($this->exporter) {
            return $this->exporter;
        }
        $options = array(
            'process_id' => $this->processId,
            'not_export_empty_columns' => $this->data['not_export_empty_columns'],
            'not_export_column_names' => !$this->data['export_fields_name'],
        );
        if ($hash !== null) {
            $options['hash'] = $hash;
        }
        return $this->exporter = new crmContactsExporter($options);
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
        $outputEncoding = $this->data['outputEncoding'];
        if ($outputEncoding == 'utf-8') {
            return $string;
        }
        return @iconv('utf-8', $outputEncoding . '//IGNORE', $string);
    }

    protected function isDone() {
        return $this->getExporter()->isExportDone() &&
            $this->getExporter()->isExportResultGettingDone();
    }

    protected function step() {

        if ($this->isDone()) {
            return false;
        }

        $exporter = $this->getExporter();

        $chunk_size = wa('crm')->getConfig()->getContactsExportChunkSize();

        if (!$exporter->isExportDone()) {
            $exporter->exportChunk($chunk_size);
        }

        if (!$exporter->isExportResultGettingDone()) {
            foreach ($exporter->getExportResultChunk($chunk_size) as $line) {
                foreach ($line as &$value) {
                    $value = $this->encodeString($value);
                }
                unset($value);
                fputcsv($this->fd, $line, $this->data['separator']);
            }
        }

        return false;
    }

    /** Return some info from $this->data to user. Other class variables are not available. */
    protected function info() {
        echo json_encode(array(
            'processId' => $this->processId,
            'ready' => false,
            'progress' => $this->isDone() ? 100 : $this->getExporter()->getCurrentProgress()
        ));
    }

    /** Return file to browser */
    protected function finish($filename)
    {
        if (!$this->getRequest()->get('file') && !$this->getRequest()->post('file')) {
            // lost messenger
            echo json_encode(array(
                'processId' => $this->processId,
                'ready' => true,
                'progress' => $this->isDone() ? 100 : $this->getExporter()->getCurrentProgress()
            ));
            return false;
        } elseif (wa()->whichUI('crm') == '1.3') {
            waFiles::readfile($filename, 'exported_contacts.csv', false);
        } else {
            waFiles::copy($filename, $this->data['filename']);
            echo json_encode([
                'processId' => $this->processId,
                'ready' => true,
                'progress' => $this->isDone() ? 100 : $this->getExporter()->getCurrentProgress(),
                'file' => basename($this->data['filename'])
            ]);
        }

        return true;
    }

    /**
     * @return string
     */
    protected function getHash()
    {
        if (!$this->isCheckedAll()) {
            $contact_ids = crmHelper::toIntArray($this->getRequest()->request('contact_ids'));
            $contact_ids = crmHelper::dropNotPositive($contact_ids);
            return 'id/' . join(',', $contact_ids);
        }

        $hash = trim((string)$this->getRequest()->request('hash'));
        $split = explode('/', $hash);
        if (isset($split[0], $split[1]) && $split[0] === 'responsible') {
            if ($split[1] === 'me') {
                $hash = 'search/crm_user_id='.wa()->getUser()->getId();
            } elseif ($split[1] === 'no') {
                $hash = 'search/crm_user_id?=NULL';
            } else {
                $hash = 'search/crm_user_id='.intval($split[1]);
            }
        }

        return $hash;
    }

    /**
     * @return bool
     */
    private function isCheckedAll()
    {
        return $this->getRequest()->request('is_checked_all') ? true : false;
    }
}
