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

use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Plugins\MediaAnalytics\Dao\LogTable;
use Exception;

class MediaAnalytics extends Plugin
{
    const MEDIA_TYPE_VIDEO = 1;
    const MEDIA_TYPE_AUDIO = 2;
    
    public function registerEvents()
    {
        return array(
            'API.getSegmentDimensionMetadata' => 'addSegmentDimensionMetadata',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'Metrics.getDefaultMetricTranslations' => 'getDefaultMetricTranslations',
            'Metrics.getDefaultMetricDocumentationTranslations' => 'getDefaultMetricDocumentationTranslations',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys'
        );
    }
    
    public function isTrackerPlugin()
    {
        return true;
    }

    public function install()
    {
        $logTable = new LogTable();
        $logTable->install();

        $configuration = new Configuration();
        $configuration->install();
    }

    public function uninstall()
    {
        $logTable = new LogTable();
        $logTable->uninstall();

        $configuration = new Configuration();
        $configuration->uninstall();
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = 'plugins/MediaAnalytics/stylesheets/reports.less';
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = 'plugins/MediaAnalytics/javascripts/mediaDataTable.js';
        $jsFiles[] = 'plugins/MediaAnalytics/javascripts/liveMediaDataTable.js';
        $jsFiles[] = 'plugins/MediaAnalytics/javascripts/mediaBarGraph.js';
        $jsFiles[] = 'plugins/MediaAnalytics/javascripts/rowaction.js';
    }
    
    public function getDefaultMetricTranslations(&$translations)
    {
        $translations[Metrics::METRIC_NB_PLAYS] = Piwik::translate('MediaAnalytics_ColumnPlays');
        $translations[Metrics::METRIC_NB_PLAYS_BY_UNIQUE_VISITORS] = Piwik::translate('MediaAnalytics_ColumnPlaysByUniqueVisitors');
        $translations[Metrics::METRIC_NB_IMPRESSIONS] = Piwik::translate('MediaAnalytics_ColumnImpressions');
        $translations[Metrics::METRIC_NB_IMPRESSIONS_BY_UNIQUE_VISITORS] = Piwik::translate('MediaAnalytics_ColumnImpressionsByUniqueVisitors');
        $translations[Metrics::METRIC_NB_FINISHES] = Piwik::translate('MediaAnalytics_ColumnFinishes');
        $translations[Metrics::METRIC_TOTAL_TIME_WATCHED] = Piwik::translate('MediaAnalytics_ColumnTotalTimeWatched');
        $translations[Metrics::METRIC_TOTAL_AUDIO_PLAYS] = Piwik::translate('MediaAnalytics_ColumnTotalAudioPlays');
        $translations[Metrics::METRIC_TOTAL_AUDIO_IMPRESSIONS] = Piwik::translate('MediaAnalytics_ColumnTotalAudioImpressions');
        $translations[Metrics::METRIC_TOTAL_VIDEO_PLAYS] = Piwik::translate('MediaAnalytics_ColumnTotalVideoPlays');
        $translations[Metrics::METRIC_TOTAL_VIDEO_IMPRESSIONS] = Piwik::translate('MediaAnalytics_ColumnTotalVideoImpressions');
    }

    public function getDefaultMetricDocumentationTranslations(&$translations)
    {
        $translations[Metrics::METRIC_NB_PLAYS] = Piwik::translate('MediaAnalytics_ColumnDescriptionPlays');
        $translations[Metrics::METRIC_NB_PLAYS_BY_UNIQUE_VISITORS] = Piwik::translate('MediaAnalytics_ColumnDescriptionPlaysByUniqueVisitors');
        $translations[Metrics::METRIC_NB_IMPRESSIONS] = Piwik::translate('MediaAnalytics_ColumnDescriptionImpressions');
        $translations[Metrics::METRIC_NB_IMPRESSIONS_BY_UNIQUE_VISITORS] = Piwik::translate('MediaAnalytics_ColumnDescriptionImpressionsByUniqueVisitors');
        $translations[Metrics::METRIC_NB_FINISHES] = Piwik::translate('MediaAnalytics_ColumnDescriptionFinishes');
        $translations[Metrics::METRIC_TOTAL_TIME_WATCHED] = Piwik::translate('MediaAnalytics_ColumnDescriptionTotalTimeWatched');
        $translations[Metrics::METRIC_TOTAL_AUDIO_PLAYS] = Piwik::translate('MediaAnalytics_ColumnDescriptionTotalAudioPlays');
        $translations[Metrics::METRIC_TOTAL_AUDIO_IMPRESSIONS] = Piwik::translate('MediaAnalytics_ColumnDescriptionTotalAudioImpressions');
        $translations[Metrics::METRIC_TOTAL_VIDEO_PLAYS] = Piwik::translate('MediaAnalytics_ColumnDescriptionTotalVideoPlays');
        $translations[Metrics::METRIC_TOTAL_VIDEO_IMPRESSIONS] = Piwik::translate('MediaAnalytics_ColumnDescriptionTotalVideoImpressions');
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = 'MediaAnalytics_RowActionTooltipTitle';
        $translationKeys[] = 'MediaAnalytics_RowActionTooltipDefault';
        $translationKeys[] = 'MediaAnalytics_MediaDetails';
        $translationKeys[] = 'SegmentEditor_CustomSegment';
    }

    public function addSegmentDimensionMetadata(&$segments, $idSite)
    {
        $mediaTypeValues = array(self::MEDIA_TYPE_AUDIO => 'audio', self::MEDIA_TYPE_VIDEO => 'video');
        
        $segment = new Segment();
        $segment->setSegment(Segment::NAME_MEDIA_IMPRESSION_TYPE);
        $segment->setType(Segment::TYPE_DIMENSION);
        $segment->setName(Piwik::translate('MediaAnalytics_SegmentNameMediaImpressionType'));
        $segment->setSqlSegment('log_media.media_type');
        $segment->setAcceptedValues(Piwik::translate('MediaAnalytics_SegmentDescriptionMediaImpressionType'));
        $segment->setSqlFilterValue(function ($mediaType) use ($mediaTypeValues) {
            if (isset($mediaTypeValues[$mediaType])) {
                return (int) $mediaType;
            }
            
            if ($key = array_search($mediaType, $mediaTypeValues)) {
                return (int) $key;
            }

            $message = Piwik::translate('InvalidMediaTypeUseEg', implode(', ', $mediaTypeValues));

            throw new Exception($message);
        });
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) use ($mediaTypeValues) {
            return array_values($mediaTypeValues);
        });
        $segments[] = $segment->toArray();

        $segment = new Segment();
        $segment->setSegment(Segment::NAME_MEDIA_PLAYS_TYPE);
        $segment->setType(Segment::TYPE_DIMENSION);
        $segment->setName(Piwik::translate('MediaAnalytics_SegmentNameMediaPlaysType'));
        $segment->setSqlSegment('log_media.idview');
        $segment->setSqlFilter('\\Piwik\\Plugins\\MediaAnalytics\\Segment::getMediaTypePlays');
        $segment->setAcceptedValues(Piwik::translate('MediaAnalytics_SegmentDescriptionMediaPlayType'));
        $segment->setSqlFilterValue(function ($mediaType) use ($mediaTypeValues) {
            if (isset($mediaTypeValues[$mediaType])) {
                return (int) $mediaType;
            }

            if ($key = array_search($mediaType, $mediaTypeValues)) {
                return (int) $key;
            }

            $message = Piwik::translate('InvalidMediaTypeUseEg', implode(', ', $mediaTypeValues));

            throw new Exception($message);
        });
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) use ($mediaTypeValues) {
            return array_values($mediaTypeValues);
        });
        $segments[] = $segment->toArray();

        // RESOUR
        $segment = new Segment();
        $segment->setSegment(Segment::NAME_RESOURCE);
        $segment->setType(Segment::TYPE_DIMENSION);
        $segment->setName(Piwik::translate('MediaAnalytics_SegmentNameMediaResource'));
        $segment->setSqlSegment('log_media.resource');
        $segment->setAcceptedValues(Piwik::translate('MediaAnalytics_SegmentDescriptionMediaResource'));
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) {
            $logTable = LogTable::getInstance();
            return $logTable->getMostUsedValuesForDimension('resource', $idSite, $maxValuesToReturn);
        });
        $segments[] = $segment->toArray();

        $segment = new Segment();
        $segment->setSegment(Segment::NAME_MEDIA_TITLE);
        $segment->setType(Segment::TYPE_DIMENSION);
        $segment->setName(Piwik::translate('MediaAnalytics_SegmentNameMediaTitle'));
        $segment->setSqlSegment('log_media.media_title');
        $segment->setAcceptedValues(Piwik::translate('MediaAnalytics_SegmentDescriptionMediaTitle'));
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) {
            $logTable = LogTable::getInstance();
            return $logTable->getMostUsedValuesForDimension('media_title', $idSite, $maxValuesToReturn);
        });
        $segments[] = $segment->toArray();

        $segment = new Segment();
        $segment->setSegment(Segment::NAME_SPENT_TIME);
        $segment->setType(Segment::TYPE_METRIC);
        $segment->setName(Piwik::translate('MediaAnalytics_SegmentNameSpentTime'));
        $segment->setSqlSegment('log_media.watched_time');
        $segment->setAcceptedValues(Piwik::translate('MediaAnalytics_SegmentDescriptionSpentTime'));
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) {
            $logTable = LogTable::getInstance();
            return $logTable->getMostUsedValuesForDimension('watched_time', $idSite, $maxValuesToReturn);
        });
        $segments[] = $segment->toArray();

        $segment = new Segment();
        $segment->setSegment(Segment::NAME_TIME_TO_PLAY);
        $segment->setType(Segment::TYPE_METRIC);
        $segment->setName(Piwik::translate('MediaAnalytics_SegmentNameTimeToInitialPlay'));
        $segment->setSqlSegment('log_media.time_to_initial_play');
        $segment->setAcceptedValues(Piwik::translate('MediaAnalytics_SegmentDescriptionTimeToInitialPlay'));
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) {
            $logTable = LogTable::getInstance();
            return $logTable->getMostUsedValuesForDimension('time_to_initial_play', $idSite, $maxValuesToReturn);
        });
        $segments[] = $segment->toArray();

        $segment = new Segment();
        $segment->setSegment(Segment::NAME_MEDIA_LENGTH);
        $segment->setType(Segment::TYPE_METRIC);
        $segment->setName(Piwik::translate('MediaAnalytics_SegmentNameMediaLength'));
        $segment->setSqlSegment('log_media.media_length');
        $segment->setAcceptedValues(Piwik::translate('MediaAnalytics_SegmentDescriptionMediaLength'));
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) {
            $logTable = LogTable::getInstance();
            return $logTable->getMostUsedValuesForDimension('media_length', $idSite, $maxValuesToReturn);
        });
        $segments[] = $segment->toArray();

        $segment = new Segment();
        $segment->setSegment(Segment::NAME_MEDIA_PLAYER);
        $segment->setType(Segment::TYPE_DIMENSION);
        $segment->setName(Piwik::translate('MediaAnalytics_SegmentNameMediaPlayer'));
        $segment->setSqlSegment('log_media.player_name');
        $segment->setAcceptedValues(Piwik::translate('MediaAnalytics_SegmentDescriptionMediaPlayer'));
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) {
            $logTable = LogTable::getInstance();
            return $logTable->getMostUsedValuesForDimension('player_name', $idSite, $maxValuesToReturn);
        });
        $segments[] = $segment->toArray();
    }

}
