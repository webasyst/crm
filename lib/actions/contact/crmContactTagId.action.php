<?php

class crmContactTagIdAction extends crmContactsAction
{
    protected $id;

    protected function afterExecute()
    {
        $info = $this->getCollection()->getInfo();
        $tag = ifset($info['tag']);
        if (!$tag) {
            $title = _w('Tag not found');
        } else {
            $title = $info['tag']['name'] ? $info['tag']['name'] : sprintf(_w('Tag #%s'), $info['tag']['id']);
        }
        $this->view->assign(array(
            'tag' => $tag,
            'title' => $title
        ));
    }

    protected function getHash()
    {
        $tag_id = $this->getTagId();
        return "tag/{$tag_id}";
    }

    protected function getTagId()
    {
        if ($this->id !== null) {
            return $this->id;
        }
        $this->id = (int) $this->getParameter('id');
        if ($this->id <= 0) {
            $this->notFound();
        }
        return $this->id;
    }
}
