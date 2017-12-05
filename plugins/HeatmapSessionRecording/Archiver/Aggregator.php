<?php
/**
 * Copyright (C) InnoCraft Ltd - All rights reserved.
 *
 * NOTICE:  All information contained herein is, and remains the property of InnoCraft Ltd.
 * The intellectual and technical concepts contained herein are protected by trade secret or copyright law.
 * Redistribution of this information or reproduction of this material is strictly forbidden
 * unless prior written permission is obtained from InnoCraft Ltd.
 *
 * You shall use this code only in accordance with the license agreement obtained from InnoCraft Ltd.
 *
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */
namespace Piwik\Plugins\HeatmapSessionRecording\Archiver;

use Piwik\Common;
use Piwik\DataAccess\LogAggregator;
use Piwik\Db;
use Piwik\Period;
use Piwik\Plugins\HeatmapSessionRecording\Dao\SiteHsrDao;
use Piwik\Plugins\HeatmapSessionRecording\Tracker\RequestProcessor;
use Piwik\Segment;
use Piwik\ArchiveProcessor;
use Piwik\Site;

class Aggregator
{
    public function findRecording($idVisit)
    {
        $query = sprintf('SELECT hsrsite.idsitehsr,
                   min(hsr.idloghsr) as idloghsr
                   FROM %s hsr 
                   LEFT JOIN %s hsrsite ON hsr.idloghsr = hsrsite.idloghsr 
                   LEFT JOIN %s hsrevent ON hsrevent.idloghsr = hsr.idloghsr and hsrevent.event_type = %s
                   LEFT JOIN %s sitehsr ON hsrsite.idsitehsr = sitehsr.idsitehsr
                   WHERE hsr.idvisit = ? and sitehsr.record_type = ? and hsrevent.idhsrblob is not null and hsrsite.idsitehsr is not null
                   GROUP BY hsrsite.idsitehsr 
                  LIMIT 1',
            Common::prefixTable('log_hsr'), Common::prefixTable('log_hsr_site'), Common::prefixTable('log_hsr_event'), RequestProcessor::EVENT_TYPE_INITIAL_DOM, Common::prefixTable('site_hsr'));

        return Db::fetchRow($query, array($idVisit, SiteHsrDao::RECORD_TYPE_SESSION));
    }

    public function getEmbedSessionInfo($idSite, $idSiteHsr, $idLogHsr)
    {
        $logHsr = Common::prefixTable('log_hsr');
        $logHsrSite = Common::prefixTable('log_hsr_site');
        $logAction = Common::prefixTable('log_action');
        $logEvent = Common::prefixTable('log_hsr_event');
        $logBlob = Common::prefixTable('log_hsr_blob');

        $query = sprintf('SELECT laction.name as base_url, hsrblob.`value` as initial_mutation, hsrblob.compressed
                          FROM %s hsr 
                          LEFT JOIN %s laction ON laction.idaction = hsr.idaction_url
                          LEFT JOIN %s hsr_site ON hsr_site.idloghsr = hsr.idloghsr
                          LEFT JOIN %s hsrevent ON hsrevent.idloghsr = hsr.idloghsr and hsrevent.event_type = %s
                          LEFT JOIN %s hsrblob ON hsrevent.idhsrblob = hsrblob.idhsrblob
                          WHERE hsr.idloghsr = ? and hsr.idsite = ? and hsr_site.idsitehsr = ? 
                                and hsrevent.idhsrblob is not null and `hsrblob`.`value` is not null
                          LIMIT 1',
                          $logHsr, $logAction, $logHsrSite, $logEvent,
                          RequestProcessor::EVENT_TYPE_INITIAL_DOM, $logBlob
        );

        $row = Db::fetchRow($query, array($idLogHsr, $idSite, $idSiteHsr));

        if (!empty($row['compressed'])) {
            $row['initial_mutation'] = gzuncompress($row['initial_mutation']);
        }

        return $row;
    }

    public function getRecordedSession($idLogHsr)
    {
        $select = 'log_action.name as url,
                   log_visit.idvisit,
                   log_visit.idvisitor,
                   log_hsr.idsite,
                   log_visit.location_country,
                   log_visit.location_region,
                   log_visit.location_city,
                   log_visit.config_os,
                   log_visit.config_device_type,
                   log_visit.config_device_model,
                   log_visit.config_browser_name,
                   log_hsr.time_on_page,
                   log_hsr.server_time,
                   log_hsr.viewport_w_px,
                   log_hsr.viewport_h_px,
                   log_hsr.scroll_y_max_relative,
                   log_hsr.fold_y_relative';

        $logHsr = Common::prefixTable('log_hsr');
        $logVisit = Common::prefixTable('log_visit');
        $logAction = Common::prefixTable('log_action');

        $query = sprintf('SELECT %s 
                          FROM %s log_hsr 
                          LEFT JOIN %s log_visit ON log_hsr.idvisit = log_visit.idvisit
                          LEFT JOIN %s log_action ON log_action.idaction = log_hsr.idaction_url
                          WHERE log_hsr.idloghsr = ?', $select, $logHsr, $logVisit, $logAction);

        return Db::fetchRow($query, array($idLogHsr));
    }

    public function getRecordedSessions($idSite, $idSiteHsr, $period, $date, $segment)
    {
        $period = Period\Factory::build($period, $date);
        $segment = new Segment($segment, array($idSite));
        $site = new Site($idSite);

        $from = array(
            'log_hsr',
            array(
                'table' => 'log_hsr_site',
                'joinOn' => 'log_hsr_site.idloghsr = log_hsr.idloghsr'
            ),
            array(
                'table' => 'log_visit',
                'joinOn' => 'log_visit.idvisit = log_hsr.idvisit'
            ),
            array(
                'table' => 'log_action',
                'joinOn' => 'log_action.idaction = log_hsr.idaction_url'
            ),
            array(
                'table' => 'log_hsr_event',
                'joinOn' => 'log_hsr_event.idloghsr = log_hsr.idloghsr and log_hsr_event.event_type = ' . RequestProcessor::EVENT_TYPE_INITIAL_DOM
            )
        );

        // we need to make sure to show only sessions that have an initial mutation with time_since_load = 0, otherwise
        // the recording won't work.

        $select = 'log_hsr.idvisit as label,
                   count(*) as nb_pageviews,
                   log_hsr.idvisit,
                   SUBSTRING_INDEX(GROUP_CONCAT(CAST(log_action.name AS CHAR) ORDER BY log_hsr.server_time ASC SEPARATOR \'##\'), \'##\', 1) as first_url,
                   SUBSTRING_INDEX(GROUP_CONCAT(CAST(log_action.name AS CHAR) ORDER BY log_hsr.server_time DESC SEPARATOR \'##\'), \'##\', 1) as last_url,
                   sum(log_hsr.time_on_page) as time_on_site,
                   min(log_hsr_site.idloghsr) as idloghsr,
                   log_visit.idvisitor,
                   log_visit.location_country,
                   log_visit.location_region,
                   log_visit.location_city,
                   log_visit.config_os,
                   log_visit.config_device_type,
                   log_visit.config_device_model,
                   log_visit.config_browser_name,
                   min(log_hsr.server_time) as server_time';

        $params = new ArchiveProcessor\Parameters($site, $period, $segment);
        $logAggregator = new LogAggregator($params);

        $where = $logAggregator->getWhereStatement('log_hsr', 'server_time');
        $where .= sprintf(" and log_hsr_site.idsitehsr = %d and log_hsr_event.idhsrblob is not null", (int) $idSiteHsr);
        $groupBy = 'log_hsr.idvisit';
        $orderBy = 'log_hsr.server_time DESC';

        $query = $logAggregator->generateQuery($select, $from, $where, $groupBy, $orderBy);

        return Db::fetchAll($query['sql'], $query['bind']);
    }

    public function getRecordedPageViewsInSession($idSite, $idSiteHsr, $idVisit, $period, $date, $segment)
    {
        $period = Period\Factory::build($period, $date);
        $segment = new Segment($segment, array($idSite));
        $site = new Site($idSite);

        $from = array(
            'log_hsr',
            array(
                'table' => 'log_hsr_site',
                'joinOn' => 'log_hsr_site.idloghsr = log_hsr.idloghsr'
            ),
            array(
                'table' => 'log_visit',
                'joinOn' => 'log_visit.idvisit = log_hsr.idvisit'
            ),
            array(
                'table' => 'log_action',
                'joinOn' => 'log_action.idaction = log_hsr.idaction_url'
            ),
            array(
                'table' => 'log_hsr_event',
                'joinOn' => 'log_hsr_event.idloghsr = log_hsr.idloghsr and log_hsr_event.event_type = ' . RequestProcessor::EVENT_TYPE_INITIAL_DOM
            )
        );

        // we need to make sure to show only sessions that have an initial mutation with time_since_load = 0, otherwise
        // the recording won't work. If this happens often, we might "end / finish" a configured session recording
        // earlier since we have eg recorded 1000 sessions, but user sees only 950 which will be confusing but we can
        // for now not take this into consideration during tracking when we get number of available samples only using
        // log_hsr_site to detect if the number of configured sessions have been reached. ideally we would at some point
        // also make sure to include this check there but will be slower.

        $select = 'log_action.name as label,
                   log_visit.idvisitor,
                   log_hsr_site.idloghsr,
                   log_hsr.time_on_page as time_on_page,
                   CONCAT(log_hsr.viewport_w_px, "x", log_hsr.viewport_h_px) as resolution,
                   log_hsr.server_time,
                   log_hsr.scroll_y_max_relative,
                   log_hsr.fold_y_relative';

        $params = new ArchiveProcessor\Parameters($site, $period, $segment);
        $logAggregator = new LogAggregator($params);

        $where = $logAggregator->getWhereStatement('log_hsr', 'server_time');
        $where .= sprintf(" and log_hsr_site.idsitehsr = %d and log_hsr.idvisit = %d and log_hsr_event.idhsrblob is not null ", (int) $idSiteHsr, (int) $idVisit);
        $groupBy = '';
        $orderBy = 'log_hsr.server_time ASC';

        $query = $logAggregator->generateQuery($select, $from, $where, $groupBy, $orderBy);

        return Db::fetchAll($query['sql'], $query['bind']);
    }

    public function aggregateHeatmap($idSiteHsr, $heatmapType, $deviceType, $idSite, $period, $date, $segment)
    {
        $heatmapTypeWhere = '';
        if ($heatmapType == RequestProcessor::EVENT_TYPE_CLICK) {
            $heatmapTypeWhere .= 'log_hsr_event.event_type = ' . (int) $heatmapType;
        } elseif ($heatmapType == RequestProcessor::EVENT_TYPE_MOVEMENT) {
            $heatmapTypeWhere .= 'log_hsr_event.event_type IN(' . (int) RequestProcessor::EVENT_TYPE_MOVEMENT . ',' . (int) RequestProcessor::EVENT_TYPE_CLICK . ')';
        } else {
            throw new \Exception('Heatmap type not supported');
        }

        $period = Period\Factory::build($period, $date);
        $segment = new Segment($segment, array($idSite));
        $site = new Site($idSite);

        $from = array(
            'log_hsr',
            array(
                'table' => 'log_hsr_site',
                'joinOn' => 'log_hsr_site.idloghsr = log_hsr.idloghsr'
            ),
            array(
                'table' => 'log_hsr_event',
                'joinOn' => 'log_hsr_site.idloghsr = log_hsr_event.idloghsr'
            ),
            array(
                'table' => 'log_action',
                'joinOn' => 'log_action.idaction = log_hsr_event.idselector'
            )
        );

        $select = 'log_action.name as selector, 
                   log_hsr_event.x as offset_x,
                   log_hsr_event.y as offset_y,
                   count(*) as value';

        $params = new ArchiveProcessor\Parameters($site, $period, $segment);
        $logAggregator = new LogAggregator($params);

        $where = $logAggregator->getWhereStatement('log_hsr', 'server_time');
        $where .= ' and log_hsr_site.idsitehsr = ' . (int) $idSiteHsr . ' and log_hsr_event.idselector is not null and ' . $heatmapTypeWhere;
        $where .= ' and log_hsr.device_type = ' . (int) $deviceType;

        $groupBy = 'log_hsr_event.idselector, log_hsr_event.x, log_hsr_event.y';
        $orderBy = '';

        $query = $logAggregator->generateQuery($select, $from, $where, $groupBy, $orderBy);

        return Db::fetchAll($query['sql'], $query['bind']);
    }

    public function getRecordedHeatmapMetadata($idSiteHsr, $idSite, $period, $date, $segment)
    {
        $period = Period\Factory::build($period, $date);
        $segment = new Segment($segment, array($idSite));
        $site = new Site($idSite);

        $from = array(
            'log_hsr',
            array(
                'table' => 'log_hsr_site',
                'joinOn' => 'log_hsr_site.idloghsr = log_hsr.idloghsr'
            )
        );

        $select = 'log_hsr.device_type, count(*) as value, avg(log_hsr.fold_y_relative) as avg_fold';

        $params = new ArchiveProcessor\Parameters($site, $period, $segment);
        $logAggregator = new LogAggregator($params);

        $where = $logAggregator->getWhereStatement('log_hsr', 'server_time');
        $where .= ' and log_hsr_site.idsitehsr = ' . (int) $idSiteHsr;
        $groupBy = 'log_hsr.device_type';
        $orderBy = '';

        $query = $logAggregator->generateQuery($select, $from, $where, $groupBy, $orderBy);

        return Db::fetchAll($query['sql'], $query['bind']);
    }

    public function aggregateScrollHeatmap($idSiteHsr, $deviceType, $idSite, $period, $date, $segment)
    {
        $period = Period\Factory::build($period, $date);
        $segment = new Segment($segment, array($idSite));
        $site = new Site($idSite);

        $from = array('log_hsr',
            array(
                'table' => 'log_hsr_site',
                'joinOn' => 'log_hsr_site.idloghsr = log_hsr.idloghsr'
            ),
        );

        $select = 'log_hsr.scroll_y_max_relative as label,
                   count(*) as value';

        $params = new ArchiveProcessor\Parameters($site, $period, $segment);
        $logAggregator = new LogAggregator($params);
        $where = $logAggregator->getWhereStatement('log_hsr', 'server_time');
        $where .= ' and log_hsr_site.idsitehsr = ' . (int) $idSiteHsr;
        $where .= ' and log_hsr.device_type = ' . (int) $deviceType;

        $groupBy = 'log_hsr.scroll_y_max_relative';
        $orderBy = '';

        $query = $logAggregator->generateQuery($select, $from, $where, $groupBy, $orderBy);

        return Db::fetchAll($query['sql'], $query['bind']);
    }
}

