<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\VisitTime;

use Piwik\DataArray;
use Piwik\DataTable;
use Piwik\Date;
use Piwik\Log;

class Archiver extends \Piwik\Plugin\Archiver
{
    const SERVER_TIME_RECORD_NAME = 'VisitTime_serverTime';
    const LOCAL_TIME_RECORD_NAME = 'VisitTime_localTime';

    public function aggregateDayReport()
    {
        $this->aggregateByLocalTime();
        $this->aggregateByServerTime();
    }

    public function aggregateMultipleReports()
    {
        $dataTableRecords = array(
            self::LOCAL_TIME_RECORD_NAME,
            self::SERVER_TIME_RECORD_NAME,
        );
        $columnsAggregationOperation = null;
        $this->getProcessor()->aggregateDataTableRecords(
            $dataTableRecords,
            $maximumRowsInDataTableLevelZero = null,
            $maximumRowsInSubDataTable = null,
            $columnToSortByBeforeTruncation = null,
            $columnsAggregationOperation,
            $columnsToRenameAfterAggregation = null,
            $countRowsRecursive = array());
    }

    protected function aggregateByServerTime()
    {
        $dataArray = $this->getLogAggregator()->getMetricsFromVisitByDimension(array("label" => "HOUR(log_visit.visit_last_action_time)"));
        $query = $this->getLogAggregator()->queryConversionsByDimension(array("label" => "HOUR(log_conversion.server_time)"));
        if ($query === false) {
            return;
        }

        while ($conversionRow = $query->fetch()) {
            $dataArray->sumMetricsGoals($conversionRow['label'], $conversionRow);
        }
        $dataArray->enrichMetricsWithConversions();
        $dataArray = $this->convertTimeToLocalTimezone($dataArray);
        $this->ensureAllHoursAreSet($dataArray);
        $report = $dataArray->asDataTable()->getSerialized();
        $this->getProcessor()->insertBlobRecord(self::SERVER_TIME_RECORD_NAME, $report);
    }

    protected function aggregateByLocalTime()
    {
        $array = $this->getLogAggregator()->getMetricsFromVisitByDimension("HOUR(log_visit.visitor_localtime)");
        $this->ensureAllHoursAreSet($array);
        $report = $array->asDataTable()->getSerialized();
        $this->getProcessor()->insertBlobRecord(self::LOCAL_TIME_RECORD_NAME, $report);
    }

    protected function convertTimeToLocalTimezone(DataArray &$array)
    {
        $date = Date::factory($this->getProcessor()->getParams()->getDateStart()->getDateStartUTC())->toString("Y-m-d");
        $timezone = $this->getProcessor()->getParams()->getSite()->getTimezone();
        
        /**
         * [Thangnt 2016-12-27] Fix the problem with datetime string,
         * only take the date part for VisitTime plugin.
         * This is a work-around rather than solution. 
         * 
         * This date is just used to append to hour to convert time by standard functions
         * of Date class, the date doesn't affect the result. 
         */
        //if(strlen($date) > 10){
        //    $date = substr($date, 0,10);
        //}
        
        Log::debug("VisitTime converts server time to local timezone: date= $date .");
            
        $converted = array();
        foreach ($array->getDataArray() as $hour => $stats) {
            Log::debug("hour: $hour");
            $datetime = $date . ' ' . $hour . ':00:00';
            $hourInTz = (int)Date::factory($datetime, $timezone)->toString('H');
            Log::debug("After converted: datetime = $hourInTz");
            $converted[$hourInTz] = $stats;
        }
        return new DataArray($converted);
    }

    private function ensureAllHoursAreSet(DataArray &$array)
    {
        $data = $array->getDataArray();
        for ($i = 0; $i <= 23; $i++) {
            if (empty($data[$i])) {
                $array->sumMetricsVisits($i, DataArray::makeEmptyRow());
            }
        }
    }

}
