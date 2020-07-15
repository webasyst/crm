<?php

class crmSourceSettingsWithContactViewBlock extends crmSourceSettingsViewBlock
{
    public function getAssigns()
    {
        $sm = new crmSegmentModel();;
        return array(
            'segments' => $sm->getMergedSegments($this->source),
            'locales' => waLocale::getAll('all'),
            'locale' => $this->source->getParam('locale')
        );
    }
}
