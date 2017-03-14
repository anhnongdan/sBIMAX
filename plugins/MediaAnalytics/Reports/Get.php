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

namespace Piwik\Plugins\MediaAnalytics\Reports;

use Piwik\Piwik;
use Piwik\Plugins\MediaAnalytics\Columns\Metrics\FinishRate;
use Piwik\Plugins\MediaAnalytics\Columns\Metrics\ImpressionRate;
use Piwik\Plugins\MediaAnalytics\Columns\Metrics\PlayRate;
use Piwik\Plugins\MediaAnalytics\Metrics;

class Get extends Base
{
    protected function init()
    {
        parent::init();

        $this->name = Piwik::translate('MediaAnalytics_Summary');
        $this->documentation = Piwik::translate('MediaAnalytics_ReportDocumentationMediaSummary');
        $this->metrics = array(
            Metrics::METRIC_NB_PLAYS,
            Metrics::METRIC_NB_PLAYS_BY_UNIQUE_VISITORS,
            Metrics::METRIC_NB_IMPRESSIONS,
            Metrics::METRIC_NB_IMPRESSIONS_BY_UNIQUE_VISITORS,
            Metrics::METRIC_IMPRESSION_RATE,
            Metrics::METRIC_NB_FINISHES,
            Metrics::METRIC_TOTAL_TIME_WATCHED,
            Metrics::METRIC_TOTAL_AUDIO_PLAYS,
            Metrics::METRIC_TOTAL_AUDIO_IMPRESSIONS,
            Metrics::METRIC_TOTAL_VIDEO_PLAYS,
            Metrics::METRIC_TOTAL_VIDEO_IMPRESSIONS,
        );
        $this->order = 0;

        $this->processedMetrics = array(
            new PlayRate(),
            new FinishRate(),
            new ImpressionRate()
        );
    }

    public function getRelatedReports()
    {
        return array();
    }

}
