<?php

class crmContactOperationAddToSegmentsAction extends crmContactOperationAction
{
    /**
     * @var crmSegmentModel
     */
    protected $sm;

    public function execute()
    {
        $this->view->assign(array(
            'segments' => $this->getSegments(),
            'total_count' => $this->getContactsCollection()->count(),
            'is_assign' => $this->isAssign()
        ));
    }

    protected function isAssign()
    {
        return (int) $this->getRequest()->request('is_assign');
    }

    protected function getSegments()
    {
        $segments = $this->getSegmentModel()->getAllSegments(array(
            'type' => 'category',
            'archived' => 0
        ));
        $fa_segment_icons = crmSegmentModel::getIcons(wa()->whichUI('crm'));
        foreach ($segments as &$segment) {
            $segment['checked'] = false;
            $segment['disabled'] = !$this->getCrmRights()->canEditSegment($segment);
            if (empty($segment['icon_path']) && !in_array($segment['icon'], $fa_segment_icons)) {
                $segment['icon'] = ($segment['type'] === 'search' ? 'filter' : 'user-friends');
            }
            if (!empty($segment['icon_path'])) {
                $segment['icon'] = null;
            }
        }
        unset($segment);

        $total_count = $this->getContactsCollection()->count();
        if ($total_count == 1 && $this->isAssign()) {
            $contacts = $this->getContacts();
            $contact = reset($contacts);
            $segment_ids = array_keys($segments);
            foreach ($this->getSegmentModel()->dropNotAssigned($contact['id'], $segment_ids) as $segment_id) {
                $segments[$segment_id]['checked'] = true;
            }
        }

        $splintered = array(
            'my' => array(),
            'shared' => array(),
        );
        foreach ($segments as $segment) {
            if (!empty($segment['system_id'])) {
                continue;
            }
            if (!$segment['shared']) {
                $splintered['my'][$segment['id']] = $segment;
            } else {
                $splintered['shared'][$segment['id']] = $segment;
            }
        }

        return $splintered;
    }
}
