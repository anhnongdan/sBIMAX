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

namespace Piwik\Plugins\HeatmapSessionRecording;

use Piwik\Category\Subcategory;
use Piwik\Common;
use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\DataTable\Row;
use Piwik\Piwik;
use Piwik\Plugins\CoreHome\SystemSummary;
use Piwik\Plugins\HeatmapSessionRecording\Archiver\Aggregator;
use Piwik\Plugins\HeatmapSessionRecording\Dao\LogHsrEvent;
use Piwik\Plugins\HeatmapSessionRecording\Dao\LogHsr;
use Piwik\Plugins\HeatmapSessionRecording\Dao\LogHsrSite;
use Piwik\Plugins\HeatmapSessionRecording\Dao\LogHsrBlob;
use Piwik\Plugins\HeatmapSessionRecording\Dao\SiteHsrDao;
use Piwik\Plugins\HeatmapSessionRecording\Install\HtAccess;
use Piwik\Widget\WidgetConfig;
use Piwik\Plugin;

class HeatmapSessionRecording extends \Piwik\Plugin
{
    CONST ULR_PARAM_FORCE_SAMPLE = 'pk_hsr_forcesample';
    CONST ULR_PARAM_FORCE_CAPTURE_SCREEN = 'pk_hsr_capturescreen';

    public function registerEvents()
    {
        return array(
            'Db.getActionReferenceColumnsByTable' => 'addActionReferenceColumnsByTable',
            'Tracker.Cache.getSiteAttributes'  => 'addSiteTrackerCache',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'Category.addSubcategories' => 'addSubcategories',
            'SitesManager.deleteSite.end' => 'onDeleteSite',
            'Tracker.PageUrl.getQueryParametersToExclude' => 'getQueryParametersToExclude',
            'Widget.addWidgetConfigs' => 'addWidgetConfigs',
            'Live.visitorLogViewBeforeActionsInfo' => 'visitorLogViewBeforeActionsInfo',
            'System.addSystemSummaryItems' => 'addSystemSummaryItems',
            'API.HeatmapSessionRecording.addHeatmap.end' => 'updatePiwikTracker',
            'API.HeatmapSessionRecording.addSessionRecording.end' => 'updatePiwikTracker',
            'CustomPiwikJs.shouldAddTrackerFile' => 'shouldAddTrackerFile',
            'Updater.componentUpdated' => 'installHtAccess',
        );
    }

    public function shouldAddTrackerFile(&$shouldAdd, $pluginName)
    {
        if ($pluginName === 'HeatmapSessionRecording') {

            $config = new Configuration();

            $siteHsrDao = $this->getSiteHsrDao();
            if ($config->shouldOptimizeTrackingCode() && !$siteHsrDao->hasActiveRecordsAcrossSites()) {
                // saves requests to configs.php while no heatmap or session recording configured.
                $shouldAdd = false;
            }
        }
    }

    public function updatePiwikTracker()
    {
        if (Plugin\Manager::getInstance()->isPluginActivated('CustomPiwikJs')) {
            $trackerUpdater = StaticContainer::get('Piwik\Plugins\CustomPiwikJs\TrackerUpdater');
            if (!empty($trackerUpdater)) {
                $trackerUpdater->update();
            }
        }
    }

    public function addSystemSummaryItems(&$systemSummary)
    {
        $dao = $this->getSiteHsrDao();
        $numHeatmaps = $dao->getNumRecordsTotal(SiteHsrDao::RECORD_TYPE_HEATMAP);
        $numSessions = $dao->getNumRecordsTotal(SiteHsrDao::RECORD_TYPE_SESSION);

        $systemSummary[] = new SystemSummary\Item($key = 'heatmaps', Piwik::translate('HeatmapSessionRecording_NHeatmaps', $numHeatmaps), $value = null, array('module' => 'HeatmapSessionRecording', 'action' => 'manageHeatmap'), $icon = 'icon-drop', $order = 6);
        $systemSummary[] = new SystemSummary\Item($key = 'sessionrecordings', Piwik::translate('HeatmapSessionRecording_NSessionRecordings', $numSessions), $value = null, array('module' => 'HeatmapSessionRecording', 'action' => 'manageSessions'), $icon = 'icon-play', $order = 7);
    }

    /**
     * @param string $out
     * @param Row $visitor
     */
    public function visitorLogViewBeforeActionsInfo(&$out, $visitor)
    {
        $idVisit = $visitor->getColumn('idVisit');
        $idSite = (int) $visitor->getColumn('idSite');

        if (empty($idSite) || empty($idVisit) || Piwik::isUserIsAnonymous()) {
            return;
        }

        $aggregator = new Aggregator();
        $recording = $aggregator->findRecording($idVisit);

        if (!empty($recording['idsitehsr'])) {
            $title = Piwik::translate('HeatmapSessionRecording_ReplayRecordedSession');
            $out .= '<a class="visitorLogReplaySession" href="?module=HeatmapSessionRecording&action=replayRecording&idSite=' . $idSite . '&idLogHsr=' . (int)$recording['idloghsr']. '&idSiteHsr=' . (int) $recording['idsitehsr'] . '" target="_blank" rel="noreferrer noopener"><span class="icon-play"></span> ' . $title . '</a><br />';
        }
    }

    public function getQueryParametersToExclude(&$parametersToExclude)
    {
        // these are used by the tracker
        $parametersToExclude[] = self::ULR_PARAM_FORCE_CAPTURE_SCREEN;
        $parametersToExclude[] = self::ULR_PARAM_FORCE_SAMPLE;
    }

    public function onDeleteSite($idSite)
    {
        $model = $this->getSiteHsrModel();
        $model->deactivateRecordsForSite($idSite);
    }

    private function getSiteHsrModel()
    {
        return StaticContainer::get('Piwik\Plugins\HeatmapSessionRecording\Model\SiteHsrModel');
    }

    private function getValidator()
    {
        return StaticContainer::get('Piwik\Plugins\HeatmapSessionRecording\Input\Validator');
    }

    public function addWidgetConfigs(&$configs)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (!$this->getValidator()->canViewHeatmapReport($idSite)) {
            return;
        }

        $model = $this->getSiteHsrModel();
        $heatmaps = $model->getHeatmaps($idSite);

        foreach ($heatmaps as $heatmap) {
            $widget = new WidgetConfig();
            $widget->setCategoryId('HeatmapSessionRecording_Heatmaps');
            $widget->setSubcategoryId($heatmap['idsitehsr']);
            $widget->setModule('HeatmapSessionRecording');
            $widget->setAction('showHeatmap');
            $widget->setParameters(array('idSiteHsr' => $heatmap['idsitehsr']));
            $widget->setIsNotWidgetizable();
            $configs[] = $widget;
        }
    }

    public function addSubcategories(&$subcategories)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (empty($idSite)) {
            // fallback for eg API.getReportMetadata which uses idSites
            $idSite = Common::getRequestVar('idSites', 0, 'int');

            if (empty($idSite)) {
                return;
            }
        }

        $model = $this->getSiteHsrModel();

        if ($this->getValidator()->canViewHeatmapReport($idSite)) {
            $heatmaps = $model->getHeatmaps($idSite);

            // we list recently created heatmaps first
            $order = 20;
            foreach ($heatmaps as $heatmap) {
                $subcategory = new Subcategory();
                $subcategory->setName($heatmap['name']);
                $subcategory->setCategoryId('HeatmapSessionRecording_Heatmaps');
                $subcategory->setId($heatmap['idsitehsr']);
                $subcategory->setOrder($order++);
                $subcategories[] = $subcategory;
            }
        }

        if ($this->getValidator()->canViewSessionReport($idSite)) {
            $recordings = $model->getSessionRecordings($idSite);

            // we list recently created recordings first
            $order = 20;
            foreach ($recordings as $recording) {
                $subcategory = new Subcategory();
                $subcategory->setName($recording['name']);
                $subcategory->setCategoryId('HeatmapSessionRecording_SessionRecordings');
                $subcategory->setId($recording['idsitehsr']);
                $subcategory->setOrder($order++);
                $subcategories[] = $subcategory;
            }
        }
    }

    public function getClientSideTranslationKeys(&$result)
    {
        $result[] = 'General_Save';
        $result[] = 'General_Done';
        $result[] = 'General_Actions';
        $result[] = 'General_Yes';
        $result[] = 'General_No';
        $result[] = 'General_Add';
        $result[] = 'General_Remove';
        $result[] = 'General_Id';
        $result[] = 'General_Ok';
        $result[] = 'General_Cancel';
        $result[] = 'General_Name';
        $result[] = 'General_Loading';
        $result[] = 'General_LoadingData';
        $result[] = 'General_Mobile';
        $result[] = 'General_All';
        $result[] = 'CorePluginsAdmin_Status';
        $result[] = 'DevicesDetection_Tablet';
        $result[] = 'CoreUpdater_UpdateTitle';
        $result[] = 'DevicesDetection_Device';
        $result[] = 'Installation_Legend';
        $result[] = 'HeatmapSessionRecording_XSamples';
        $result[] = 'HeatmapSessionRecording_StatusActive';
        $result[] = 'HeatmapSessionRecording_StatusEnded';
        $result[] = 'HeatmapSessionRecording_RequiresActivity';
        $result[] = 'HeatmapSessionRecording_RequiresActivityHelp';
        $result[] = 'HeatmapSessionRecording_CaptureKeystrokes';
        $result[] = 'HeatmapSessionRecording_CaptureKeystrokesHelp';
        $result[] = 'HeatmapSessionRecording_SessionRecording';
        $result[] = 'HeatmapSessionRecording_Heatmap';
        $result[] = 'HeatmapSessionRecording_ActivityClick';
        $result[] = 'HeatmapSessionRecording_ActivityMove';
        $result[] = 'HeatmapSessionRecording_ActivityScroll';
        $result[] = 'HeatmapSessionRecording_ActivityResize';
        $result[] = 'HeatmapSessionRecording_ActivityFormChange';
        $result[] = 'HeatmapSessionRecording_ActivityPageChange';
        $result[] = 'HeatmapSessionRecording_HeatmapWidth';
        $result[] = 'HeatmapSessionRecording_PlayerDurationXofY';
        $result[] = 'HeatmapSessionRecording_PlayerPlay';
        $result[] = 'HeatmapSessionRecording_PlayerPause';
        $result[] = 'HeatmapSessionRecording_PlayerRewindFast';
        $result[] = 'HeatmapSessionRecording_PlayerForwardFast';
        $result[] = 'HeatmapSessionRecording_PlayerReplay';
        $result[] = 'HeatmapSessionRecording_PlayerPageViewPrevious';
        $result[] = 'HeatmapSessionRecording_PlayerPageViewNext';
        $result[] = 'HeatmapSessionRecording_SessionRecordingsUsageBenefits';
        $result[] = 'HeatmapSessionRecording_ManageSessionRecordings';
        $result[] = 'HeatmapSessionRecording_ManageHeatmaps';
        $result[] = 'HeatmapSessionRecording_NoSessionRecordingsFound';
        $result[] = 'HeatmapSessionRecording_FieldIncludedTargetsHelpSessions';
        $result[] = 'HeatmapSessionRecording_NoHeatmapsFound';
        $result[] = 'HeatmapSessionRecording_AvgAboveFoldTitle';
        $result[] = 'HeatmapSessionRecording_AvgAboveFoldDescription';
        $result[] = 'HeatmapSessionRecording_TargetPage';
        $result[] = 'HeatmapSessionRecording_TargetPages';
        $result[] = 'HeatmapSessionRecording_ViewReport';
        $result[] = 'HeatmapSessionRecording_SampleLimit';
        $result[] = 'HeatmapSessionRecording_SessionNameHelp';
        $result[] = 'HeatmapSessionRecording_HeatmapSampleLimit';
        $result[] = 'HeatmapSessionRecording_SessionSampleLimit';
        $result[] = 'HeatmapSessionRecording_HeatmapSampleLimitHelp';
        $result[] = 'HeatmapSessionRecording_SessionSampleLimitHelp';
        $result[] = 'HeatmapSessionRecording_MinSessionTime';
        $result[] = 'HeatmapSessionRecording_MinSessionTimeHelp';
        $result[] = 'HeatmapSessionRecording_EditX';
        $result[] = 'HeatmapSessionRecording_StopX';
        $result[] = 'HeatmapSessionRecording_HeatmapUsageBenefits';
        $result[] = 'HeatmapSessionRecording_AdvancedOptions';
        $result[] = 'HeatmapSessionRecording_SampleRate';
        $result[] = 'HeatmapSessionRecording_HeatmapSampleRateHelp';
        $result[] = 'HeatmapSessionRecording_SessionSampleRateHelp';
        $result[] = 'HeatmapSessionRecording_ExcludedElements';
        $result[] = 'HeatmapSessionRecording_ExcludedElementsHelp';
        $result[] = 'HeatmapSessionRecording_ScreenshotUrl';
        $result[] = 'HeatmapSessionRecording_ScreenshotUrlHelp';
        $result[] = 'HeatmapSessionRecording_BreakpointX';
        $result[] = 'HeatmapSessionRecording_BreakpointGeneralHelp';
        $result[] = 'HeatmapSessionRecording_Rule';
        $result[] = 'HeatmapSessionRecording_UrlParameterValueToMatchPlaceholder';
        $result[] = 'HeatmapSessionRecording_EditHeatmapX';
        $result[] = 'HeatmapSessionRecording_TargetTypeIsAny';
        $result[] = 'HeatmapSessionRecording_TargetTypeIsNot';
        $result[] = 'HeatmapSessionRecording_UpdatingData';
        $result[] = 'HeatmapSessionRecording_FieldIncludedTargetsHelp';
        $result[] = 'HeatmapSessionRecording_DeleteX';
        $result[] = 'HeatmapSessionRecording_DeleteHeatmapConfirm';
        $result[] = 'HeatmapSessionRecording_BreakpointGeneralHelpManage';
        $result[] = 'HeatmapSessionRecording_TargetPageTestTitle';
        $result[] = 'HeatmapSessionRecording_TargetPageTestErrorInvalidUrl';
        $result[] = 'HeatmapSessionRecording_TargetPageTestUrlMatches';
        $result[] = 'HeatmapSessionRecording_TargetPageTestUrlNotMatches';
        $result[] = 'HeatmapSessionRecording_TargetPageTestLabel';
        $result[] = 'HeatmapSessionRecording_ErrorXNotProvided';
        $result[] = 'HeatmapSessionRecording_ErrorPageRuleRequired';
        $result[] = 'HeatmapSessionRecording_CreationDate';
        $result[] = 'HeatmapSessionRecording_HeatmapCreated';
        $result[] = 'HeatmapSessionRecording_HeatmapUpdated';
        $result[] = 'HeatmapSessionRecording_FieldNamePlaceholder';
        $result[] = 'HeatmapSessionRecording_HeatmapNameHelp';
        $result[] = 'HeatmapSessionRecording_CreateNewHeatmap';
        $result[] = 'HeatmapSessionRecording_CreateNewSessionRecording';
        $result[] = 'HeatmapSessionRecording_EditSessionRecordingX';
        $result[] = 'HeatmapSessionRecording_DeleteSessionRecordingConfirm';
        $result[] = 'HeatmapSessionRecording_EndHeatmapConfirm';
        $result[] = 'HeatmapSessionRecording_EndSessionRecordingConfirm';
        $result[] = 'HeatmapSessionRecording_SessionRecordingCreated';
        $result[] = 'HeatmapSessionRecording_SessionRecordingUpdated';
        $result[] = 'HeatmapSessionRecording_Filter';
        $result[] = 'HeatmapSessionRecording_PlayRecordedSession';
        $result[] = 'HeatmapSessionRecording_DeleteRecordedSession';
        $result[] = 'HeatmapSessionRecording_DeleteRecordedPageview';
        $result[] = 'Live_ViewVisitorProfile';
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/HeatmapSessionRecording/javascripts/rowaction.js";
        $jsFiles[] = "plugins/HeatmapSessionRecording/libs/heatmap.js/build/heatmap.min.js";
        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/common/hsrIframeLoade.directive.js";
        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/heatmapvis/heatmapvis.controller.js";
        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/heatmapvis/heatmapvis.directive.js";

        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/sessionvis/sessionvis.controller.js";
        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/sessionvis/sessionvis.directive.js";

        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/targettest/targettest.directive.js";
        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/urltarget/urltarget.directive.js";
        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/manage/model.js";
        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/manageHeatmap/list.controller.js";
        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/manageHeatmap/list.directive.js";
        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/manageHeatmap/edit.controller.js";
        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/manageHeatmap/edit.directive.js";
        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/manageHeatmap/manage.controller.js";
        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/manageHeatmap/manage.directive.js";

        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/manageSession/list.controller.js";
        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/manageSession/list.directive.js";
        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/manageSession/edit.controller.js";
        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/manageSession/edit.directive.js";
        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/manageSession/manage.controller.js";
        $jsFiles[] = "plugins/HeatmapSessionRecording/angularjs/manageSession/manage.directive.js";
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/HeatmapSessionRecording/angularjs/manage/list.directive.less";
        $stylesheets[] = "plugins/HeatmapSessionRecording/angularjs/manage/edit.directive.less";
        $stylesheets[] = "plugins/HeatmapSessionRecording/angularjs/targettest/targettest.directive.less";
        $stylesheets[] = "plugins/HeatmapSessionRecording/stylesheets/recordings.less";
        $stylesheets[] = "plugins/HeatmapSessionRecording/angularjs/sessionvis/sessionvis.directive.less";
        $stylesheets[] = "plugins/HeatmapSessionRecording/angularjs/heatmapvis/heatmapvis.directive.less";
    }

    public function activate()
    {
        $this->installHtAccess();
    }

    public function install()
    {
        $siteHsr = new SiteHsrDao();
        $siteHsr->install();

        $hsrSite = new LogHsrSite();
        $hsrSite->install();

        $hsr = new LogHsr($hsrSite);
        $hsr->install();

        $blobHsr = new LogHsrBlob();
        $blobHsr->install();

        $event = new LogHsrEvent($blobHsr);
        $event->install();

        $this->installHtAccess();

        $configuration = new Configuration();
        $configuration->install();
    }

    public function installHtAccess()
    {
        $htaccess = new HtAccess();
        $htaccess->install();
    }

    public function uninstall()
    {
        $siteHsr = new SiteHsrDao();
        $siteHsr->uninstall();

        $hsrSite = new LogHsrSite();
        $hsrSite->uninstall();

        $hsr = new LogHsr($hsrSite);
        $hsr->uninstall();

        $blobHsr = new LogHsrBlob();
        $blobHsr->uninstall();

        $event = new LogHsrEvent($blobHsr);
        $event->uninstall();

        $configuration = new Configuration();
        $configuration->uninstall();
    }

    public function isTrackerPlugin()
    {
        return true;
    }

    private function getSiteHsrDao()
    {
        return StaticContainer::get('Piwik\Plugins\HeatmapSessionRecording\Dao\SiteHsrDao');
    }

    public function addSiteTrackerCache(&$content, $idSite)
    {
        $hsr = $this->getSiteHsrDao();
        $content['hsr'] = $hsr->getActiveRecords($idSite);
    }

    public function addActionReferenceColumnsByTable(&$result)
    {
        $result['log_hsr'] = array('idaction_url');
        $result['log_hsr_event'] = array('idselector');
    }

}
