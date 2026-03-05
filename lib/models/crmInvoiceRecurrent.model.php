<?php

class crmInvoiceRecurrentModel extends crmModel
{
    const UNIT_DAY = 'DAY';
    const UNIT_WEEK = 'WEEK';
    const UNIT_MONTH = 'MONTH';
    const UNIT_YEAR = 'YEAR';

    protected $table = 'crm_invoice_recurrent';

    public function getIssueList()
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE end_datetime IS NULL 
                    AND next_date <= :today 
                    AND (stop_on_non_payment = 0 OR non_paid_count < stop_on_non_payment)
                ORDER BY next_date ASC";
        return $this->query($sql, ['today' => date('Y-m-d')])->fetchAll('id');
    }

    public static function getDescription($record)
    {
        if (empty($record)) {
            return '';
        }
        
        if ($record['interval_value'] == 1) {
            switch ($record['interval_unit']) {
                case self::UNIT_DAY:
                    return _w('Every day');
                case self::UNIT_WEEK:
                    return _w('Every week');
                case self::UNIT_MONTH:
                    return _w('Monthly');
                case self::UNIT_YEAR:
                    return _w('Once a year');
                default:
                    return '';
            }
        }

        switch ($record['interval_unit']) {
            case self::UNIT_DAY:
                return _w('Every %d day', 'Every %d days', $record['interval_value']);
            case self::UNIT_WEEK:
                return _w('Every %d week', 'Every %d weeks', $record['interval_value']);
            case self::UNIT_MONTH:
                return _w('Every %d month', 'Every %d months', $record['interval_value']);
            case self::UNIT_YEAR:
                return _w('Every %d year', 'Every %d years', $record['interval_value']);
            default:
                return '';
        }
    }
}
