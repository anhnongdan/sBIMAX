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
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\MediaAnalytics\Metrics;
use Piwik\Plugins\MediaAnalytics\Columns\PlayThroughRate;

class GetPlayCountProgressPercentile extends Base
{
    protected function init()
    {
        parent::init();

        $this->name = Piwik::translate('MediaAnalytics_PlayThrough').' - How far viewers reached in the video';

        $this->documentation = Piwik::translate('MediaAnalytics_ReportDocumentationMediaPlayers');
        $this->dimension = new PlayThroughRate();

        $this->metrics = array(
            Metrics::METRIC_NB_PLAYS,
            Metrics::METRIC_SUM_TIME_WATCHED,
            Metrics::METRIC_SUM_MEDIA_LENGTH,
            Metrics::METRIC_SUM_FULLSCREEN_PLAYS
        );

        $this->order = 40;

        $this->subcategoryId = 'MediaAnalytics_PlayThrough';
    }

    public function configureView(ViewDataTable $view)
    {
        // $e = new \Exception();
        // print_r(str_replace('/path/to/code/', '', $e->getTraceAsString()));

        $this->configureTableReport($view);
        //$view->config->addTranslations(array('label' => $this->dimension->getName()));
        $view->requestConfig->apiMethodToRequestDataTable = 'MediaAnalytics.getPlayCountProgressPercentile';

        $view->config->columns_to_display = array(
                'label',
                Metrics::METRIC_NB_PLAYS,
                Metrics::METRIC_SUM_TIME_WATCHED,
                Metrics::METRIC_SUM_MEDIA_LENGTH,
                Metrics::METRIC_SUM_FULLSCREEN_PLAYS
            );

        $view->config->show_pagination_control = false;
        $view->config->show_flatten_table = false;
        $view->config->show_offset_information = false;
        $view->config->show_limit_control = false;
        $view->config->show_search = false;
        $view->config->show_exclude_low_population = false;
    }

}
