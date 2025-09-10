<?php

class crmTemplates
{
    /**
     * @var array
     */
    protected static $templates_variants;
    protected static $templates_variants_contents;

    /**
     * @return array
     */
    public static function getTemplatesVariants($include_content = false)
    {
        
        if (self::$templates_variants !== null) {
            return self::$templates_variants;
        }

        if (self::$templates_variants === null) {
            $path = dirname(__FILE__).'/../config/data';

            if (!file_exists($path.'/templates.php')) {
                return self::$templates_variants = [];
            }

            self::$templates_variants = include($path.'/templates.php');
            if (!$include_content) {
                return self::$templates_variants;
            }
        }

        if (self::$templates_variants_contents === null) {
            self::$templates_variants_contents = [];
            $path = dirname(__FILE__).'/../config/data';
            foreach (self::$templates_variants as $id => $t) {
                self::$templates_variants_contents[$id] = file_exists($path.$t['path']) ? file_get_contents($path.$t['path']) : '';
            }
        }

        return array_map(function ($t) {
            $t['content'] = self::$templates_variants_contents[$t['origin_id']];
            return $t;
        }, self::$templates_variants);
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
     * @deprecated
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

    public function getOriginTemplate($origin_id)
    {
        $template_path = '/templates/invoices/invoice.template_a.html';
        $path = wa('crm')->getConfig()->getAppPath('lib/config/data');
        if (!empty($origin_id) && isset(self::getTemplatesVariants()[$origin_id]) && $data = self::getTemplatesVariants()[$origin_id]) {
            $template_path = $data['path'];
        }
        if (file_exists($path.$template_path)) {
            return file_get_contents($path.$template_path);
        }
    }

    public function getOriginTemplateParams($origin_id)
    {
        $params = [];
        if (!empty($origin_id) && isset(self::getTemplatesVariants()[$origin_id]) && $data = self::getTemplatesVariants()[$origin_id]) {
            $params = $data['template_params'];
        }
        return $params;
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
        $origin_template = preg_replace('~\s+~', '', $this->getOriginTemplate($template['origin_id']));
        $content = preg_replace('~\s+~', '', $template['content']);
        return $content !== $origin_template;
    }
}