<?php

class crmResponsibleListMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_GET;

    public function execute()
    {
        $responsibles = [];
        $scope = waRequest::get('scope', 'contact', waRequest::TYPE_STRING_TRIM);
        $userpic_size = abs(waRequest::get('userpic_size', self::USERPIC_SIZE, waRequest::TYPE_INT));

        if (!in_array($scope, ['contact', 'deal', 'conversation'])) {
            throw new waAPIException('invalid_param', sprintf_wp('Invalid “%s” value.', 'scope'), 400);
        }

        $available_responsibles = $this->getResponsibleModel()->getAvailableResponsibles($scope);
        $counts = array_combine(array_keys($available_responsibles), array_column($available_responsibles, 'count'));
        $contacts = $this->prepareContactsList($available_responsibles, ['id', 'name', 'userpic'], $userpic_size);
        foreach ($contacts as $_contact) {
            $responsibles[] = [
                'count'       => (int) ifempty($counts, $_contact['id'], 0),
                'responsible' => $_contact
            ];
        }

        $this->response = $responsibles;
    }
}
