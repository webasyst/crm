<?php

class crmContactImportUploadDataController extends crmJsonController
{
    public function execute()
    {
        if (!$this->getCrmRights()->isAdmin()) {
            $this->accessDenied();
        }

        $separator = $this->getSeparator();
        $encoding = $this->getEncoding();

        $this->getStorage()->write('import/params', array(
            'separator' => $separator,
            'encoding' => $encoding
        ));

        $csv = new waCSV(false, $separator, false);
        $csv->setEncoding($encoding);

        $type = $this->getRequest()->post('type');
        try {
            if ($type == 1) {
                $content = $this->getRequest()->post('content');
                if (!trim($content)) {
                    throw new Exception(_w("Incorrect import data format"));
                }
                $file = $csv->saveContent($content);

            } elseif ($type == 2) {
                $file = $csv->upload("csv");
            }
            // Save for the next actions
            $this->getStorage()->write('import/file', $file);
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
        }
    }

    protected function getEncoding()
    {
        $encoding = strtolower($this->getRequest()->request('encoding', 'utf-8', waRequest::TYPE_STRING_TRIM));
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
}
