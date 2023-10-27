<?php

class crmTagListMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_GET;

    public function execute()
    {
        $this->response = $this->filterData(
            $this->getTagModel()->getCloud(),
            ['id', 'name', 'count', 'size', 'opacity'],
            ['id' => 'integer', 'count' => 'integer', 'size' => 'integer', 'opacity' => 'float']
        );
    }

}