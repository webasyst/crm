<?php

class crmContactImportUploadAction extends crmBackendViewAction
{
    public function execute()
    {
        if (!$this->getCrmRights()->isAdmin()) {
            $this->accessDenied();
        }

        $file = $this->getStorage()->read('import/file');

        $separator = $this->getSeparator();
        $encoding = $this->getEncoding();

        $csv = new waCSV(true, $separator, false, $file);
        $csv->setEncoding($encoding);

        $error = '';
        $info = null;
        try {
            $info = $csv->getInfo();
        } catch (Exception $e) {
            // file doesn't exist
            $error = $e->getMessage();
        }

        $fields = waContactFields::getInfo('enabled');
        unset($fields['name']);

        $this->view->assign(array(
            'csv' => $info,
            'error' => $error,
            'group_id' => $this->getRequest()->get('group_id'),
            'fieldInfo' => $fields,
            'fields' => $this->getImportExportFields($fields)
        ));
        $this->view->assign($this->getFunnelsAndStages());
    }

    protected function getEncoding()
    {
        $params = $this->getStorage()->read('import/params');
        $encoding = strtolower((string) ifset($params['encoding']));
        $encodings = crmHelper::getImportExportEncodings();
        if (!isset($encodings[$encoding])) {
            $encoding = 'utf-8';
        }
        return $encoding;
    }

    protected function getSeparator()
    {
        $params = $this->getStorage()->read('import/params');
        $sep = ifset($params['separator']);
        return crmHelper::chooseLegalCsvSeparator($sep);
    }

    protected function getFunnelsAndStages()
    {
        $fm = new crmFunnelModel();
        $funnels = $fm->getAllFunnels();
        if (!$funnels) {
            return array('funnels' => array(), 'stages' => array());
        }
        $fsm = new crmFunnelStageModel();
        $funnel = reset($funnels);
        $stages = $fsm->getStagesByFunnel($funnel['id']);
        return array('funnels' => $funnels, 'stages' => $stages);
    }

    protected function getImportExportFields($fields = null)
    {
        if ($fields === null) {
            $fields = waContactFields::getInfo('enabled');
            unset($fields['name']);
        }
        $data = array();
        foreach($fields as $fieldId => $fieldInfo) {

            if ($fieldInfo['type'] === 'Hidden') {
                continue;
            }

            // Helper array to fill in first
            $opts = array(/*
                ...,
                ext (may be single '') => array(
                    ..., subfieldId (may be single ''), ...
                ),
                ...
            */);

            // add extensions
            $opts[''] = array();
            if(isset($fieldInfo['ext']) && $fieldInfo['ext']) {
                foreach($fieldInfo['ext'] as $k => $v) {
                    $opts[$k] = array();
                }
            }

            // add subfields (or a single '', if no subfields)
            foreach($opts as &$o) {
                if (isset($fieldInfo['fields']) && $fieldInfo['fields']) {
                    foreach($fieldInfo['fields'] as $k => $v) {
                        if ($v['type'] === 'Hidden') {
                            continue;
                        }
                        $o[] = $k;
                    }
                } else {
                    $o[] = '';
                }
            }
            unset($o);

            // Fill $fieldInfo['options'] using $opts
            $fieldInfo['options'] = array(/* value => human-readable name */);
            foreach($opts as $ext => $o) {
                foreach($o as $subfield) {
                    $value = $fieldId;
                    $name = $fieldInfo['name'];
                    if ($subfield) {
                        $value .= ':'.$subfield;
                    }
                    if ($ext) {
                        $value .= '.'.$ext;
                        $name .= ' - '.$fieldInfo['ext'][$ext];
                    }
                    if ($subfield) {
                        $name .= ': '.$fieldInfo['fields'][$subfield]['name'];
                    }

                    $fieldInfo['options'][$value] = $name;
                }
            }
            $data[$fieldId] = $fieldInfo;
        }
        return $data;
    }
}
