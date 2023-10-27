<?php

class crmVaultListMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_GET;

    public function execute()
    {
        $vaults = $this->getVaultModel()->getAvailable();
        $this->response = $this->filterData($vaults, 
            ['id', 'name', 'color', 'create_datetime', 'count'],
            ['id' => 'integer', 'count' => 'integer', 'create_datetime' => 'datetime']
        );
    }
}
