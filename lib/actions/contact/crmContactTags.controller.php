<?php

class crmContactTagsController extends crmJsonController
{
    public function execute()
    {
        $limit = 10;
        $term = $this->getTerm();
        $tags = $this->getTags($term, $limit);
        $this->response = array(
            'tags' => $tags
        );
    }

    public function getTerm()
    {
        return wa()->getRequest()->get('term', '', waRequest::TYPE_STRING_TRIM);
    }

    public function getTags($term, $limit)
    {
        $data = array();
        $tags = $this->getTagModel()->getByTerm($term, $limit);
        foreach ($tags as $tag) {
            $data[] = array(
                'label' => '<span class="count">'.$tag['count'].'</span>'.htmlspecialchars($tag['name']),
                'value' => $tag['name'],
                'count' => $tag['count']
            );
        }
        return $data;
    }

}
