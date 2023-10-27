<?php

class crmRegionListMethod extends crmApiAbstractMethod
{
    public function execute()
    {
        $country_iso = trim($this->get('country', true));
        $country_iso = strtolower($country_iso);

        $region_model = new waRegionModel();
        $regions = $region_model->getByCountry($country_iso);
        if (empty($regions)) {
            $regions = [];
        }
        $regions = array_map(function ($reg) {
            return [
                'code' => $reg['code'],
                'name' => $reg['name'],
                'is_favorite' => !!$reg['fav_sort']
            ];
        }, $regions);

        $this->response = array_values($regions);
    }
}
