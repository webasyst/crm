<?php

class crmSegmentCountModel extends crmModel
{
    protected $table = 'crm_segment_count';
    protected $id = array('segment_id', 'contact_id');

    /**
     * @param int|int[] $segment_id
     * @param int|int[] $contact_id
     * @param mixed $not_counter_value
     * @return mixed
     */
    public function getCounters($segment_id, $contact_id, $not_counter_value = null)
    {
        $segment_id_int = (int)$segment_id;
        $contact_id_int = (int)$contact_id;

        $segment_ids = crmHelper::toIntArray($segment_id);
        $segment_ids = crmHelper::dropNotPositive($segment_ids);
        $contact_ids = crmHelper::toIntArray($contact_id);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);

        $counters = $this->getByField(array(
            'segment_id' => $segment_ids,
            'contact_id' => $contact_ids),
            true);

        $res = array();
        foreach ($segment_ids as $_segment_id) {
            $res[$_segment_id] = array_fill_keys($contact_ids, $not_counter_value);
        }
        foreach ($counters as $counter) {
            $res[$counter['segment_id']][$counter['contact_id']] = (int)$counter['count'];
        }

        if (wa_is_int($segment_id) && wa_is_int($contact_id)) {
            $res = (array)ifset($res[$segment_id_int]);
            $res = isset($res[$contact_id_int]) ? $res[$contact_id_int] : $not_counter_value;
        } elseif (wa_is_int($segment_id)) {
            $res = (array)ifset($res[$segment_id_int]);
        } elseif (wa_is_int($contact_id)) {
            foreach ($res as $segment_id => &$counters) {
                $counters = isset($counters[$contact_id_int]) ? $counters[$contact_id_int] : $not_counter_value;
            }
            unset($counters);
        }

        return $res;
    }

    public function updateCount($segment_id, $contact_id, $count)
    {
        $segment_id = (int)$segment_id;
        $contact_id = (int)$contact_id;
        $count = (int)$count;
        if ($segment_id <= 0 || $contact_id <= 0 || $count < 0) {
            return;
        }

        $item = $this->getById(array($segment_id, $contact_id));
        if (!$item) {
            $item = array('count' => 0);
        }

        $old_count = (int)$item['count'];

        if ($count === $old_count) {
            return;
        }

        if ($count == 0) {
            $this->deleteById(array($segment_id, $contact_id));
            return;
        }

        $this->insert(array(
            'segment_id' => $segment_id,
            'contact_id' => $contact_id,
            'count' => $count
        ), 1);
    }
}
