<?php

class crmCompanyModel extends crmModel
{
    /**
     * @var string
     */
    protected $table = 'crm_company';

    /**
     * Refactor wa_model->getAll(). Sorting added.
     * @param null $key
     * @param bool $normalize
     * @return array
     */
    public function getAll($key = null, $normalize = false)
    {
        $sql = "SELECT * FROM ".$this->table." ORDER BY `sort`, `id`";
        return $this->query($sql)->fetchAll($key, $normalize);
    }
}
