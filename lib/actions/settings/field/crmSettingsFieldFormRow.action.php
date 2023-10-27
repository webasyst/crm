<?php

/**
 * One <tr> row with subfield data and editor.
 */
class crmSettingsFieldFormRowAction extends waViewAction
{
    public function execute()
    {
        $f = waRequest::param('f');
        $fid = waRequest::param('fid');
        $parent = waRequest::param('parent');
        $css_class = waRequest::param('css_class');

        $new_field = false;
        if (!($f instanceof waContactField)) {
            $new_field = true;
            $f = new waContactStringField($fid, '', array(
                'app_id' => 'crm',
            ));
        }

        $app_id = $f->getParameter('app_id');
        if ($app_id && wa()->appExists($app_id)) {
            $app_icon = $app_id;
            $app_name = ifset(ref(wa()->getAppInfo($app_id)), 'name', $app_id);
        } elseif ($app_id && !wa()->appExists($app_id)) {
            $app_icon = 'installer';
            $app_name = $app_id;
        } else {
            $app_icon = $app_name = null;
        }

        $prefix = 'options';
        if ($parent) {
            $prefix .= '['.$parent.'][fields]';
        }

        static $ftypes = null;
        if ($ftypes === null) {
            $ftypes = array(
                'NameSubfield'  => _w('Text (input)'),
                'Email'         => _w('Text (input)'),
                'Address'       => _w('Address'),
                'Branch'        => _w('Selectable (radio)'),
                'Text'          => _w('Text (textarea)'),
                'String'        => _w('Text (input)'),
                'Select'        => _w('Drop-down list (select)'),
                'Phone'         => _w('Text (input)'),
                'IM'            => _w('Text (input)'),
                'Url'           => _w('Text (input)'),
                'SocialNetwork' => _w('Text (input)'),
                'Date'          => _w('Date'),
                'Birthday'      => _w('Date'),
                'Composite'     => _w('Composite field group'),
                'Checkbox'      => _w('Checkbox'),
                'Number'        => _w('Number'),
                'Region'        => _w('Region'),
                'Country'       => _w('Country'),
                'Hidden'        => _w('Hidden field'),
                'Name'          => _w('Full name'),
                'Radio'         => _w('Select (radio)'),
            );
        }

        $form = waContactForm::loadConfig(array(
            '_default_value' => $f,
        ), array(
            'namespace' => "{$prefix}[{$fid}]"
        ));

        $this->view->assign('f', $f);
        $this->view->assign('fid', $fid);
        $this->view->assign('form', $form);
        $this->view->assign('default_value');
        $this->view->assign('parent', $parent);
        $this->view->assign('prefix', $prefix);
        $this->view->assign('uniqid', str_replace('.', '-', 'f'.uniqid('f', true)));
        $this->view->assign('new_field', $new_field);
        $this->view->assign('tr_classes', $css_class);
        $this->view->assign('ftypes', $ftypes);
        $this->view->assign('app_icon', $app_icon);
        $this->view->assign('app_name', $app_name);
    }
}

