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

namespace Piwik\Plugins\AbTesting\Reports;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\AbTesting\Columns\Variation;
use Piwik\Plugins\AbTesting\Metrics;
use Piwik\Plugins\AbTesting\Model\Experiments;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;
use Piwik\Widget\WidgetsList;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Period;


/**
 * Experiment overview report. Shows raw values for each selected success metric for each variation.   
 */
class GetMetricsOverview extends Base
{
    protected function init()
    {
        parent::init();

        $this->name          = Piwik::translate('AbTesting_ExperimentOverview');
        $this->dimension     = new Variation();
        $this->documentation = null; // TODO
        $this->order = 1;
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');
        if (empty($idSite)) {
            return;
        }

        $experiments = StaticContainer::get('Piwik\Plugins\AbTesting\Model\Experiments');
        $experiments = $experiments->getExperimentsWithReports($idSite);

        foreach ($experiments as $experiment) {
            $widgetsList->addWidgetConfig(
                $factory->createWidget()
                    ->setName('General_EvolutionOverPeriod')
                    ->setSubcategoryId($experiment['idexperiment'])
                    ->forceViewDataTable(Evolution::ID)
                    ->setIsNotWidgetizable()
                    ->setIsWide()
                    ->setAction('getEvolutionGraph')
                    ->setParameters(array(
                        'idExperiment' => $experiment['idexperiment'],
                        'variationName' => Piwik::translate('AbTesting_NameOriginalVariation'),
                        'columns' => array(Metrics::METRIC_VISITS)
                    ))
                    ->setOrder(5)
            );

            $widgetsList->addWidgetConfig(
                $factory->createWidget()
                    ->setName('General_Overview')
                    ->setIsNotWidgetizable()
                    ->setIsWide()
                    ->setSubcategoryId($experiment['idexperiment'])
                    ->forceViewDataTable(HtmlTable::ID)
                    ->setParameters(array('idExperiment' => $experiment['idexperiment']))
                    ->setOrder(10)
            );
        }
    }

    private function getPrettyDate($date)
    {
        return Period\Factory::build('day', $date)->getLocalizedShortString();
    }

    /**
     * Here you can configure how your report should be displayed. For instance whether your report supports a search
     * etc. You can also change the default request config. For instance change how many rows are displayed by default.
     *
     * @param ViewDataTable $view
     */
    public function configureView(ViewDataTable $view)
    {
        if (!empty($this->dimension)) {
            $view->config->addTranslations(array('label' => $this->dimension->getName()));
        }

        $idExperiment = Common::getRequestVar('idExperiment', null, 'int');
        $idSite = Common::getRequestVar('idSite', null, 'int');
        $showSummary = Common::getRequestVar('showSummary', 0, 'int');

        $experiment = StaticContainer::get('Piwik\Plugins\AbTesting\Model\Experiments');
        $experiment = $experiment->getExperiment($idExperiment, $idSite);

        if (!empty($showSummary)) {
            $view->config->title = $experiment['name'];

            $view->config->show_footer_message .= '<a href="" piwik-experiment-page-link="' . (int) $idExperiment .'"><span class="icon-show"></span> ' . Piwik::translate('AbTesting_ActionViewReport') .'</a> ';

            if ($experiment['status'] == Experiments::STATUS_FINISHED) {
                $start = $this->getPrettyDate($experiment['start_date_site_timezone']);
                $end = $this->getPrettyDate($experiment['end_date_site_timezone']);
                $view->config->show_footer_message .= Piwik::translate('AbTesting_ReportStatusFinished', array($experiment['duration'], $start, $end));
            } elseif ($experiment['status'] == Experiments::STATUS_RUNNING) {
                $start = $this->getPrettyDate($experiment['start_date_site_timezone']);
                $view->config->show_footer_message .= Piwik::translate('AbTesting_ReportStatusRunning', array($experiment['duration'], $start));
            }
        }

        $view->config->filters[] = array(function (DataTable $table) {
            $summaryRow = new DataTable\Row(array(DataTable\Row::COLUMNS => array('label' => Piwik::translate('General_Total'))));
            foreach ($table->getRowsWithoutSummaryRow() as $row) {
                $summaryRow->sumRow($row);
            }
            $summaryRow->deleteMetadata('segment');
            $summaryRow->deleteMetadata('segmentValue');
            $summaryRow->setMetadata('css_class', 'totalOverviewRow');
            $table->addRow($summaryRow);
        }, array(), $priority = true);

        $metrics = StaticContainer::get('Piwik\Plugins\AbTesting\Metrics');

        $view->config->columns_to_display = $metrics->getMetricOverviewNames($experiment['success_metrics']);
        $view->config->enable_sort = false;
        $view->config->show_pagination_control = false;
        $view->config->show_offset_information = false;
        $view->config->show_limit_control = false;
        $view->config->show_exclude_low_population = false;
        $view->config->show_table_all_columns = false;
        $view->config->show_pie_chart = false;
        $view->config->show_bar_chart = false;
        $view->config->show_tag_cloud = false;
        $view->config->show_goals = false;
        $view->config->show_ecommerce = false;
        $view->config->show_all_views_icons = false;
        $view->config->metrics_documentation = array_merge($view->config->metrics_documentation, $metrics->getMetricDocumentations());

        if ($view->isViewDataTableId(HtmlTable::ID)) {
            $view->config->disable_row_evolution = true;
        }

        $view->requestConfig->request_parameters_to_modify['idExperiment'] = Common::getRequestVar('idExperiment', null, 'int');
        $view->requestConfig->filter_sort_column = 'label';

        $translations = $metrics->getMetricOverviewTranslations($idSite);
        $view->config->addTranslations($translations);

        $view->config->show_search = false;
    }

    protected function buildReportMetadata()
    {
        return;
    }

    /**
     * Here you can define related reports that will be shown below the reports. Just return an array of related
     * report instances if there are any.
     *
     * @return \Piwik\Plugin\Report[]
     */
    public function getRelatedReports()
    {
        return array(); // eg return array(new XyzReport());
    }

    public function getDefaultTypeViewDataTable()
    {
        return HtmlTable::ID;
    }

}
