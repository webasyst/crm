<?php

class crmFormFieldRenderer
{
    protected $field;
    protected $params;

    public function __construct($field, $params = array())
    {
        $this->field = $field;
        $this->params = $params;
    }

    public function render()
    {
        $params = $this->params;

        $attrs = '';
        if (isset($params['attrs']) && is_string($params['attrs'])) {
            $attrs = $params['attrs'];
        }
        if (!empty($params['funnel_parameters']['required'])) {
            $attrs .= " required";
        }

        if (isset($params['template_path'])) {
            $template_path = $params['template_path'];
            unset($params['template_path']);
        } elseif (isset($params['template'])) {
            $template = $params['template'];
            unset($params['template']);
            if (substr($template, -5) !== '.html') {
                $template .= '.html';
            }
            $template_path = wa()->getAppPath('templates/form/fields/field.' . $template, 'crm');
        } else {
            $template_path = null;
        }

        if ($template_path) {
            $assign = array(
                'field' => $this->field,
                'params' => $this->params
            );

            if ($this->field instanceof waContactField || $this->field instanceof crmDealField) {
                $assign['input_name'] = $this->getInputName($params);
            }

            return $this->renderTemplate($template_path, $assign);
        }

        if ($this->field instanceof waContactField || $this->field instanceof crmDealField) {
            $html = $this->field->getHTML($params, $attrs);
            return $this->field instanceof waContactBirthdayField ? '<span class="nowrap">' . $html . '</span>' : $html;
        }

        return '';
    }

    protected function getInputName($params)
    {
        $prefix = $suffix = '';
        if (isset($params['namespace'])) {
            $prefix .= $params['namespace'].'[';
            $suffix .= ']';
        }
        if (isset($params['parent'])) {
            if ($prefix) {
                $prefix .= $params['parent'].'][';
            } else {
                $prefix .= $params['parent'].'[';
                $suffix .= ']';
            }
        }

        if (isset($params['multi_index'])) {
            if (isset($params['parent'])) {
                // For composite multi-fields multi_index goes before field id:
                // namespace[parent_name][i][field_id]
                $prefix .= $params['multi_index'].'][';
            } else {
                // For non-composite multi-fields multi_index goes after field id:
                // namespace[field_id][i]
                $suffix = ']['.$params['multi_index'].$suffix;
            }
        }
        $name = isset($params['id']) ? $params['id'] : $this->field->getId();

        return $prefix.$name.$suffix;
    }


    protected function renderTemplate($path, $assign)
    {
        $view = wa()->getView();
        $old_vars = $view->getVars();
        $view->clearAllAssign();
        $view->assign($assign);
        $html = $view->fetch($path);
        $view->clearAllAssign();
        $view->assign($old_vars);
        return $html;
    }

}
