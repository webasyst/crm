<?php

class crmTemplates
{
    /**
     * @var array
     */
    protected static $templates_variants;

    /**
     * @return array
     */
    public static function getTemplatesVariants()
    {
        if (self::$templates_variants !== null) {
            return self::$templates_variants;
        }

        $path = dirname(__FILE__).'/../config/data';

        if (!file_exists($path.'/templates.php')) {
            return self::$templates_variants = array();
        }

        $templates = array();
        $_templates = include($path.'/templates.php');


        foreach ($_templates as $i => $t) {

            $t['content'] = '';

            if (file_exists($path.$t['path'])) {
                $t['content'] = file_get_contents($path.$t['path']);
            }

            $templates[$i] = $t;
        }

        return self::$templates_variants = $templates;
    }

    /**
     * @return array enum for crm_template_params.type
     */
    public static function getParams()
    {
        return array(
            crmTemplatesModel::PARAM_TYPE_COLOR  => array(
                'name' => _w('Color'),
            ),
            crmTemplatesModel::PARAM_TYPE_IMAGE  => array(
                'name' => _w('Image'),
            ),
            crmTemplatesModel::PARAM_TYPE_NUMBER => array(
                'name' => _w('Number'),
            ),
            crmTemplatesModel::PARAM_TYPE_STRING => array(
                'name' => _w('String'),
            ),
        );
    }

    /**
     * Get the base template depending on template id and locale.
     * @param int $template_id
     * @param string $locale        defaults to current user's locale
     * @returns string
     */
    public function getBasicTemplate($template_id, $locale = null)
    {
        $path = wa('crm')->getConfig()->getAppPath('lib/config/data');

        /**
         * Two pre-installed templates are treated in a special way.
         * they always use pre-defined base template file no matter the locale.
         */
        switch ($template_id) {
            case 1:
                $template_name = '/templates/invoice.template_a.html';
                break;
            case 2:
                $template_name = '/templates/invoice.template_b.html';
                break;
            default:
                if ($locale === null) {
                    $locale = wa()->getLocale();
                }
                if ($locale == 'ru_RU') {
                    $template_name = '/templates/invoice.template_b.html';
                } else {
                    $template_name = '/templates/invoice.template_a.html';
                }
                break;
        }

        if (file_exists($path.$template_name)) {
            return file_get_contents($path.$template_name);
        }
    }

    /**
     * Compare template content to the base one, ignoring all whitespace
     *
     * @param array $template crm_template
     * @return bool
     */
    public function isTemplateModified($template)
    {
        if (empty($template['id']) || empty($template['content'])) {
            return false;
        }
        $basic_template = preg_replace('~\s+~', '', $this->getBasicTemplate($template['id']));
        $content = preg_replace('~\s+~', '', $template['content']);
        return $content !== $basic_template;
    }
}