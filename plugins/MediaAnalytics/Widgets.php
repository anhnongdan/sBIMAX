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

namespace Piwik\Plugins\MediaAnalytics;

use Piwik\API\Request;
use Piwik\ArchiveProcessor\Rules;
use Piwik\Common;
use Piwik\Config;
use Piwik\DataTable;
use Piwik\FrontController;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable;
use Piwik\Plugins\MediaAnalytics\Dao\LogTable;
use Piwik\Plugins\MediaAnalytics\Widgets\Helper;
use Piwik\Segment;
use Piwik\SettingsPiwik;
use Piwik\Translation\Translator;
use Piwik\View;
use Piwik\ViewDataTable\Factory;

class Widgets extends \Piwik\Plugin\Widgets
{
    /**
     * @var LogTable
     */
    private $logTable;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var Translator
     */
    private $translator;

    /**
     * @var Formatter
     */
    private $formatter;

    public function __construct(LogTable $logTable, Helper $helper, Translator $translator, Formatter $formatter)
    {
        $this->logTable = $logTable;
        $this->helper = $helper;
        $this->translator = $translator;
        $this->formatter = $formatter;
    }

    protected $category = 'MediaAnalytics_Media';

    protected function init()
    {
        $this->addWidget('MediaAnalytics_WidgetTitleAudienceMap', $method = 'audienceMap');
        $this->addWidget('MediaAnalytics_WidgetTitleMediaOverview', $method = 'sparklinesSummary');
        $this->addWidget('MediaAnalytics_WidgetTitleEvolutionOverTime', $method = 'getIndexGraph', array('columns' => Metrics::METRIC_NB_PLAYS));

        $bpk = $this->category;
        $this->category = 'Live!';

        $this->addWidget('MediaAnalytics_WidgetTitleMediaPlays', $method = 'currentPlays');
        $this->addWidget('MediaAnalytics_WidgetTitleSpentTime', $method = 'currentTime');
        $this->addWidget('MediaAnalytics_WidgetTitleMostPlaysLast30', $method = 'mostPlays', array('lastMinutes' => '30'));
        $this->addWidget('MediaAnalytics_WidgetTitleMostPlaysLast60', $method = 'mostPlays', array('lastMinutes' => '60'));
        $this->addWidget('MediaAnalytics_WidgetTitleMostPlaysLast3600', $method = 'mostPlays', array('lastMinutes' => '3600'));
        $this->addWidget('MediaAnalytics_WidgetTitleRealTimeAudienceMap', $method = 'realTimeAudienceMap');

        $this->category = $bpk;
    }

    public function currentPlays()
    {
        $idSite = $this->getIdSite();
        Piwik::checkUserHasViewAccess($idSite);

        $last30 = Request::processRequest('MediaAnalytics.getCurrentNumPlays', array('idSite' => $idSite, 'lastMinutes' => 30));
        $last3600 = Request::processRequest('MediaAnalytics.getCurrentNumPlays', array('idSite' => $idSite, 'lastMinutes' => 3600));

        return $this->renderLiveMetrics('currentPlays', $last30, $last3600, 'MediaAnalytics_NbPlays');
    }

    public function currentTime()
    {
        $idSite = $this->getIdSite();
        Piwik::checkUserHasViewAccess($idSite);

        $last30 = Request::processRequest('MediaAnalytics.getCurrentSumTimeSpent', array('idSite' => $idSite, 'lastMinutes' => 30, 'format' => 'original'));
        $last3600 = Request::processRequest('MediaAnalytics.getCurrentSumTimeSpent', array('idSite' => $idSite, 'lastMinutes' => 3600));

        return $this->renderLiveMetrics('currentTime', $last30, $last3600, 'prettyTime');
    }

    public function mostPlays($time = 30)
    {
        $idSite = $this->getIdSite();
        Piwik::checkUserHasViewAccess($idSite);

        $lastMinutes = Common::getRequestVar('lastMinutes', $time, 'int');
        $filterLimit = Common::getRequestVar('filter_limit', 5, 'int');

        $view = Factory::build(HtmlTable::ID, 'MediaAnalytics.getCurrentMostPlays', 'MediaAnalytics.mostPlays', $force = true);
        $view->requestConfig->request_parameters_to_modify['filter_limit'] = $filterLimit;
        $view->requestConfig->request_parameters_to_modify['lastMinutes'] = $lastMinutes;
        $view->config->addTranslation('label', $this->translator->translate('MediaAnalytics_Media'));
        $view->config->addTranslation('value', $this->translator->translate('MediaAnalytics_ColumnPlays'));
        $view->config->custom_parameters['lastMinutes'] = $lastMinutes;
        $view->config->custom_parameters['updateInterval'] = $this->getLiveRefreshInterval();

        if ($view->isViewDataTableId(HtmlTable::ID)) {
            $view->config->disable_row_evolution = true;
        }

        $view->requestConfig->filter_sort_column = 'value';
        $view->requestConfig->filter_sort_order = 'desc';
        $view->config->columns_to_display = array('label', 'value');
        $view->config->filters[] = function () use ($view) {
            $view->config->columns_to_display = array('label', 'value');
        };
        $view->config->datatable_js_type = 'LiveMediaDataTable';
        $view->config->show_tag_cloud = false;
        $view->config->show_insights = false;
        $view->config->show_table_all_columns = false;
        $view->config->show_exclude_low_population = false;
        $view->config->show_search = false;
        $view->config->show_pagination_control = false;
        $view->config->show_offset_information = false;
        $view->config->enable_sort = false;

        return $view->render();
    }
    
    public function sparklinesSummary()
    {
        $idSite = $this->getIdSite();
        Piwik::checkUserHasViewAccess($idSite);

        $view = new View('@MediaAnalytics/sparklines');

        $currentPeriod = Common::getRequestVar('period', '', 'string');
        $displayUniqueVisitors = Archiver::isUniqueVisitorsEnabled($currentPeriod);

        $view->displayUniqueVisitors = $displayUniqueVisitors;

        if ($displayUniqueVisitors) {
            $view->urlPlays = $this->helper->getUrlSparkline('getEvolutionGraph', array('columns' => array(Metrics::METRIC_NB_PLAYS, Metrics::METRIC_NB_PLAYS_BY_UNIQUE_VISITORS)));
        } else {
            $view->urlPlays = $this->helper->getUrlSparkline('getEvolutionGraph', array('columns' => array(Metrics::METRIC_NB_PLAYS)));
        }

        $view->urlVideoPlays = $this->helper->getUrlSparkline('getEvolutionGraph', array('columns' => array(Metrics::METRIC_TOTAL_VIDEO_PLAYS, Metrics::METRIC_TOTAL_AUDIO_PLAYS)));

        if ($displayUniqueVisitors) {
            $view->urlImpressions = $this->helper->getUrlSparkline('getEvolutionGraph', array('columns' => array(Metrics::METRIC_NB_IMPRESSIONS, Metrics::METRIC_NB_IMPRESSIONS_BY_UNIQUE_VISITORS)));
        } else {
            $view->urlImpressions = $this->helper->getUrlSparkline('getEvolutionGraph', array('columns' => array(Metrics::METRIC_NB_IMPRESSIONS)));
        }
        
        $view->urlVideoImpressions = $this->helper->getUrlSparkline('getEvolutionGraph', array('columns' => array(Metrics::METRIC_TOTAL_VIDEO_IMPRESSIONS, Metrics::METRIC_TOTAL_AUDIO_IMPRESSIONS)));

        $view->urlFinishes = $this->helper->getUrlSparkline('getEvolutionGraph', array('columns' => array(Metrics::METRIC_NB_FINISHES)));
        $view->urlPlayRate = $this->helper->getUrlSparkline('getEvolutionGraph', array('columns' => array(Metrics::METRIC_PLAY_RATE)));
        $view->urlFinishRate = $this->helper->getUrlSparkline('getEvolutionGraph', array('columns' => array(Metrics::METRIC_FINISH_RATE)));
        $view->urlTotalTimeWatched = $this->helper->getUrlSparkline('getEvolutionGraph', array('columns' => array(Metrics::METRIC_TOTAL_TIME_WATCHED)));

        $table = Request::processRequest('MediaAnalytics.get', array('columns' => false, 'format_metrics' => 1));
        if (empty($table)) {
            $table = new DataTable();
        }

        if ($table->getRowsCount() === 0) {
            $row = new DataTable\Row();
        } else {
            $row = $table->getFirstRow();
        }

        $view->nbPlays = (int) $row->getColumn(Metrics::METRIC_NB_PLAYS);
        $view->nbUniquePlays = (int) $row->getColumn(Metrics::METRIC_NB_PLAYS_BY_UNIQUE_VISITORS);
        $view->nbVideoPlays = (int) $row->getColumn(Metrics::METRIC_TOTAL_VIDEO_PLAYS);
        $view->nbAudioPlays = (int) $row->getColumn(Metrics::METRIC_TOTAL_AUDIO_PLAYS);

        $view->nbImpressions = (int) $row->getColumn(Metrics::METRIC_NB_IMPRESSIONS);
        $view->nbUniqueImpressions = (int) $row->getColumn(Metrics::METRIC_NB_IMPRESSIONS_BY_UNIQUE_VISITORS);
        $view->nbVideoImpressions = (int) $row->getColumn(Metrics::METRIC_TOTAL_VIDEO_IMPRESSIONS);
        $view->nbAudioImpressions = (int) $row->getColumn(Metrics::METRIC_TOTAL_AUDIO_IMPRESSIONS);

        $view->nbFinishes = (int) $row->getColumn(Metrics::METRIC_NB_FINISHES);
        $view->nbPlayRate = $row->getColumn(Metrics::METRIC_PLAY_RATE);
        $view->nbFinishRate = $row->getColumn(Metrics::METRIC_FINISH_RATE);
        $view->nbTotalTimeWatched = (int) $row->getColumn(Metrics::METRIC_TOTAL_TIME_WATCHED);

        $displayImpressionRate = SettingsPiwik::isUniqueVisitorsEnabled($currentPeriod) && Archiver::isUniqueVisitorsEnabled($currentPeriod);
        $view->displayImpressionRate = $displayImpressionRate;

        if ($displayImpressionRate) {
            $view->urlImpressionRate = $this->helper->getUrlSparkline('getEvolutionGraph', array('columns' => array(Metrics::METRIC_IMPRESSION_RATE)));
            $view->impressionRate = $row->getColumn(Metrics::METRIC_IMPRESSION_RATE);
        }

        return $view->render();
    }

    public function realTimeAudienceMap()
    {
        $idSite = $this->getIdSite();
        Piwik::checkUserHasViewAccess($idSite);

        $params = array($standalone = false, $fetch = false, Helper::getMediaSegment());
        return FrontController::getInstance()->dispatch('UserCountryMap', 'realtimeMap', $params);
    }

    public function audienceMap()
    {
        $idSite = $this->getIdSite();
        Piwik::checkUserHasViewAccess($idSite);

        $segmentString = Helper::getMediaSegment();

        $segment = new Segment($segmentString, array($idSite));

        $params = array($fetch = false, $segmentString);

        $content = FrontController::getInstance()->dispatch('UserCountryMap', 'visitorMap', $params);

        $period = Common::getRequestVar('period', null, 'string');

        if (Helper::isUsingDefaultSegment()
            && !Rules::isRequestAuthorizedToArchive()
            && !Rules::isBrowserArchivingAvailableForSegments()
            && !Rules::shouldProcessReportsAllPlugins(array($idSite), $segment, $period)) {

            $summary = Request::processRequest('VisitsSummary.get', array(
                'idSite' => $idSite,
                'period' => $period,
                'date' => Common::getRequestVar('date', null, 'string'),
                'segment' => $segmentString,
                'filter_limit' => '-1',
                'format' => 'PHP'
            ));

            $hasNoData = empty($summary['nb_visits']);

            if ($hasNoData) {
                $canAdd = Request::processRequest('SegmentEditor.isUserCanAddNewSegment', array('idSite' => $idSite));

                $view = new View('@MediaAnalytics/archiveDisabled.twig');
                $view->canAddSegment = $canAdd;
                $content .= $view->render();
            }
        }

        return $content;
    }

    private function renderLiveMetrics($action, $last30, $last3600, $translationKey)
    {
        $view = new View('@MediaAnalytics/liveMetrics');

        if (empty($last30)) {
            $last30 = 0;
        }
        if (empty($last3600)) {
            $last3600 = 0;
        }

        if ($translationKey === 'prettyTime') {
            $view->last30 = $this->formatter->getPrettyTimeFromSeconds($last30, $sentence = true);
            $view->last3600 = $this->formatter->getPrettyTimeFromSeconds($last3600, $sentence = true);
        } else {
            $view->last30 = $this->translator->translate($translationKey, $last30);
            $view->last3600 = $this->translator->translate($translationKey, $last3600);
        }

        $view->is_auto_refresh = Common::getRequestVar('is_auto_refresh', 0, 'int');
        $view->action = $action;
        $view->liveRefreshAfterMs = $this->getLiveRefreshInterval();

        return $view->render();
    }

    private function getLiveRefreshInterval()
    {
        return (int) Config::getInstance()->General['live_widget_refresh_after_seconds'] * 1000;
    }

    private function getIdSite()
    {
        return Common::getRequestVar('idSite', null, 'int');
    }

}
