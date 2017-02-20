<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\QoS;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Piwik;
use Piwik\View;
use Piwik\ViewDataTable\Factory as ViewDataTableFactory;


class Controller extends \Piwik\Plugin\Controller
{

	public function overview()
	{
		$view = new View('@QoS/overview');

		$this->setPeriodVariablesView($view);

		$view->graphHttpCode        = $this->overViewHttpCodeGraph( 'graphPie', array('request_count_200','request_count_204','request_count_206') );
		// $view->graphOverviewBw      = $this->overViewBandwidthGraph( 'graphVerticalBar', array('traffic_ps') );
		$view->graphIsp             = $this->overViewIspGraph('graphPie', array('isp_request_count_200_mobiphone,isp_request_count_200_vinaphone,isp_request_count_200_fpt,isp_request_count_200_viettel,isp_request_count_200_vnpt'), array('isp_request_count_200_mobiphone,isp_request_count_200_vinaphone,isp_request_count_200_fpt,isp_request_count_200_viettel,isp_request_count_200_vnpt'));
		$view->graphCountry         = $this->overViewCountryGraph('graphPie', array('country_request_count_200_VN','country_request_count_200_US','country_request_count_200_CN'), array('country_request_count_200_VN','country_request_count_200_US','country_request_count_200_CN'));
		// $view->graphCacheHit        = API::getInstance()->overViewCacheHitGraph($this->idSite, $metric = 'isp_request_count_200_viettel');
		// $view->graphSpeed           = API::getInstance()->overViewSpeedGraph($this->idSite, $metric = 'avg_speed');

		// Widget bandwidth
		$lastMinutes = 2;

		$bandwidth = API::getInstance()->overviewGetBandwidth( $lastMinutes, $metrics = 'traffic_ps', 5 );

		$view->bw_lastMinutes  	= $lastMinutes;
		$view->bandwidth   		= $bandwidth['bandwidth'];
		$view->bw_refreshAfterXSecs = 5;
		$view->bw_translations 	= array(
			'bandwidth' => Piwik::translate('QoS_Bandwidth')
		);

		// Widget User speed
		$userSpeed = API::getInstance()->overviewGetUserSpeed( $lastMinutes, $metrics = 'avg_speed', 5 );

		$view->lastMinutes  = $lastMinutes;
		$view->user_speed   = $userSpeed['user_speed'];
		$view->refreshAfterXSecs = 5;
		$view->translations = array(
			'user_speed' => Piwik::translate('QoS_UserSpeed')
		);

		return $view->render();
	}

	public function bandwidth()
	{
		$view = new View('@QoS/bandwidth');

		$this->setPeriodVariablesView($view);

		$view->graphBandwidth   = $this->getEvolutionGraphBw(array(), array('traffic_ps'));

		return $view->render();
	}

	public function userSpeed()
	{
		$view = new View('@QoS/userspeed');

		$this->setPeriodVariablesView($view);

		$userSpeed = API::getInstance()->getUserSpeed();

		$view->graphUserSpeed   = $this->getEvolutionGraphUserSpeed(array(), array($userSpeed));

		return $view->render();
	}

	public function getEvolutionGraphUserSpeed(array $columns = array(), array $defaultColumns = array())
	{
		if (empty($columns)) {
			$columns = Common::getRequestVar('columns', false);
			if (false !== $columns) {
				$columns = Piwik::getArrayFromApiParameter($columns);
			}
		}

		$userSpeed = API::getInstance()->getUserSpeed();
		$selectableColumns = array($userSpeed);

		$view = $this->getLastUnitGraphAcrossPlugins($this->pluginName, __FUNCTION__, $columns, $selectableColumns, '', 'QoS.getGraphEvolution');

		$view->config->enable_sort          = false;
		$view->config->max_graph_elements   = 30;
		$view->requestConfig->filter_sort_column = 'label';
		$view->requestConfig->filter_sort_order  = 'asc';
		$view->requestConfig->disable_generic_filters=true;

		if (empty($view->config->columns_to_display) && !empty($defaultColumns)) {
			$view->config->columns_to_display = $defaultColumns;
		}

		return $this->renderView($view);
	}

	public function cacheHit()
	{
		$view = new View('@QoS/cachehit');

		$this->setGeneralVariablesView($view);

		$cacheHitGraphs   = array();
		$cacheHit         = API::getInstance()->getCacheHit();

		foreach ($cacheHit as $isp => $metrics) {
			$_GET['isp']        = $isp;
			$cacheHitGraphs[]   = array('title'=>Piwik::translate("QoS_".$isp), 'graph'=>$this->getGraphCacheHit(array(), $metrics, $isp));
		}

		$view->cacheHitGraphs = $cacheHitGraphs;

		return $view->render();
	}

	public function getGraphCacheHit(array $columns = array(), array $defaultColumns = array())
	{
		if (empty($columns)) {
			$columns = Common::getRequestVar('columns', false);
			if (false !== $columns) {
				$columns = Piwik::getArrayFromApiParameter($columns);
			}
		}

		$isp        = Common::getRequestVar('isp', false);
		$cacheHit   = API::getInstance()->getCacheHit();
		$selectableColumns = $cacheHit[$isp];

		$view = $this->getLastUnitGraphAcrossPlugins($this->pluginName, __FUNCTION__, $columns, $selectableColumns, '', 'QoS.getGraphEvolution');

		$view->config->enable_sort          = false;
		$view->config->max_graph_elements   = 30;
		$view->requestConfig->filter_sort_column = 'label';
		$view->requestConfig->filter_sort_order  = 'asc';
		$view->requestConfig->disable_generic_filters=true;

		// Can not check empty so have to hardcode. F**k me!
		$view->config->columns_to_display = $defaultColumns;
		// if (empty($view->config->columns_to_display) && !empty($defaultColumns)) {
		//     $view->config->columns_to_display = $defaultColumns;
		// }

		return $this->renderView($view);
	}

	public function httpCode()
	{
		$view = new View('@QoS/httpcode');

		// $this->setGeneralVariablesView($view);
		$this->setPeriodVariablesView($view);

		$httpCodeGraphs = array();
		$httpCpde       = API::getInstance()->getHttpCode();

		foreach ($httpCpde as $statusCode => $metrics) {
			$_GET['statusCode'] = $statusCode;
			$httpCodeGraphs[]   = array('title'=>Piwik::translate("QoS_".$statusCode), 'graph'=>$this->getGraphHttCode(array(), $metrics, $statusCode));
		}

		$view->httpCodeGraphs = $httpCodeGraphs;

		return $view->render();
	}

	public function getGraphHttCode(array $columns = array(), array $defaultColumns = array())
	{
		if (empty($columns)) {
			$columns = Common::getRequestVar('columns', false);
			if (false !== $columns) {
				$columns = Piwik::getArrayFromApiParameter($columns);
			}
		}

		$statusCode = Common::getRequestVar('statusCode', false);
		$httpCode   = API::getInstance()->getHttpCode();
		$selectableColumns = $httpCode[$statusCode];

		$view = $this->getLastUnitGraphAcrossPlugins($this->pluginName, __FUNCTION__, $columns, $selectableColumns, '', 'QoS.getGraphEvolution');

		$view->config->enable_sort          = false;
		$view->config->max_graph_elements   = 30;
		$view->requestConfig->filter_sort_column = 'label';
		$view->requestConfig->filter_sort_order  = 'asc';
		$view->requestConfig->disable_generic_filters=true;

		// Can not check empty so have to hardcode. F**k me!
		$view->config->columns_to_display = $defaultColumns;
		// if (empty($view->config->columns_to_display) && !empty($defaultColumns)) {
		//     $view->config->columns_to_display = $defaultColumns;
		// }

		return $this->renderView($view);
	}

	public function isp()
	{
		$view = new View('@QoS/isp');

		$this->setPeriodVariablesView($view);

		$ispGraphs = array();
		$isp       = API::getInstance()->getIsp();

		foreach ($isp as $ispName => $metrics) {
			$_GET['isp'] = $ispName;
			$ispGraphs[]   = array('title'=>Piwik::translate("QoS_".$ispName), 'graph'=>$this->getGraphIsp(array(), $metrics, $ispName));
		}

		$view->ispGraphs = $ispGraphs;

		return $view->render();
	}

	public function getGraphIsp(array $columns = array(), array $defaultColumns = array())
	{
		if (empty($columns)) {
			$columns = Common::getRequestVar('columns', false);
			if (false !== $columns) {
				$columns = Piwik::getArrayFromApiParameter($columns);
			}
		}

		$isp            = Common::getRequestVar('isp', false);
		$ispSetting     = API::getInstance()->getIsp();

		$selectableColumns = $ispSetting[$isp];

		$view = $this->getLastUnitGraphAcrossPlugins($this->pluginName, __FUNCTION__, $columns, $selectableColumns, '', 'QoS.getGraphEvolution');

		$view->config->enable_sort          = false;
		$view->config->max_graph_elements   = 30;
		$view->requestConfig->filter_sort_column = 'label';
		$view->requestConfig->filter_sort_order  = 'asc';
		$view->requestConfig->disable_generic_filters=true;

		// Can not check empty so have to hardcode. F**k me!
		$view->config->columns_to_display = $defaultColumns;
		// if (empty($view->config->columns_to_display) && !empty($defaultColumns)) {
		//     $view->config->columns_to_display = $defaultColumns;
		// }

		return $this->renderView($view);
	}

	public function country()
	{
		$view = new View('@QoS/country');

		$this->setPeriodVariablesView($view);

		$country = API::getInstance()->getCountry();
		$view->countryGraph   = $this->getEvolutionGraphCountry(array(), $country);

		return $view->render();
	}

	public function getEvolutionGraphCountry(array $columns = array(), array $defaultColumns = array())
	{
		if (empty($columns)) {
			$columns = Common::getRequestVar('columns', false);
			if (false !== $columns) {
				$columns = Piwik::getArrayFromApiParameter($columns);
			}
		}

		$country = API::getInstance()->getCountry();
		$selectableColumns = $country;

		$view = $this->getLastUnitGraphAcrossPlugins($this->pluginName, __FUNCTION__, $columns, $selectableColumns = array('country_request_count_200_VN','country_request_count_200_US','country_request_count_200_CN'), '', 'QoS.getGraphEvolution');

		$view->config->enable_sort          = false;
		$view->config->max_graph_elements   = 30;
		$view->requestConfig->filter_sort_column = 'label';
		$view->requestConfig->filter_sort_order  = 'asc';
		$view->requestConfig->disable_generic_filters=true;

		if (empty($view->config->columns_to_display) && !empty($defaultColumns)) {
			$view->config->columns_to_display = $defaultColumns;
		}

		return $this->renderView($view);
	}

	public function overViewBandwidthGraph($type = 'graphVerticalBar', $metrics = array())
	{
		$view = ViewDataTableFactory::build( $type, 'QoS.buildDataBwGraph', 'QoS.overViewBandwidthGraph', false );

		$view->config->y_axis_unit  = ' bit';
		$view->config->show_footer  = true;
		$view->config->translations['value'] = Piwik::translate("QoS_Bandwidth");
		$view->config->selectable_columns   = array("value");
		$view->config->max_graph_elements   = 24;
		$view->requestConfig->filter_sort_column = 'label';
		$view->requestConfig->filter_sort_order  = 'asc';

		return $view->render();
	}

	public function overViewHttpCodeGraph($type = 'graphPie', $metrics = array())
	{
		$view = ViewDataTableFactory::build( $type, 'QoS.buildDataHttpCodeGraph', 'QoS.overViewHttpCodeGraph', false );

		$view->config->columns_to_display       = array('value');
		$view->config->translations['value']    = Piwik::translate("QoS_The_percentage_of_http_code_2xx");
		$view->config->show_footer_icons        = false;
		$view->config->selectable_columns       = array("value");
		$view->config->max_graph_elements       = 10;

		return $view->render();
	}

	public function overViewIspGraph($type = 'graphPie', $metrics = array())
	{
		$view = ViewDataTableFactory::build( $type, 'QoS.buildDataIspGraph', 'QoS.overViewIspGraph', false );

		$view->config->columns_to_display       = array('value');
		$view->config->translations['value']    = Piwik::translate("QoS_The_percentage_of_list_isp");
		$view->config->show_footer_icons        = false;
		$view->config->selectable_columns       = array("value");
		$view->config->max_graph_elements       = 10;

		return $view->render();
	}

	public function overViewCountryGraph($type = 'graphPie', $metrics = array())
	{
		$view = ViewDataTableFactory::build( $type, 'QoS.buildDataCountryGraph', 'QoS.overViewCountryGraph', false );

		$view->config->columns_to_display       = array('value');
		$view->config->translations['value']    = Piwik::translate("QoS_The_percentage_of_list_isp");
		$view->config->show_footer_icons        = false;
		$view->config->selectable_columns       = array("value");
		$view->config->max_graph_elements       = 10;

		return $view->render();
	}

	public function getIndexGraph()
	{
		return $this->getEvolutionGraph(array(), array(), __FUNCTION__);
	}

	public function getEvolutionGraph(array $columns = array(), array $defaultColumns = array())
	{
		if (empty($columns)) {
			$columns = Common::getRequestVar('columns', false);
			if (false !== $columns) {
				$columns = Piwik::getArrayFromApiParameter($columns);
			}
		}

		$selectableColumns = $defaultColumns;

		$view = $this->getLastUnitGraphAcrossPlugins($this->pluginName, __FUNCTION__, $columns, $selectableColumns, '', 'QoS.getGraphEvolution');

		// $view->config->setDefaultColumnsToDisplay($selectableColumns);
		$view->config->enable_sort          = false;
		$view->config->max_graph_elements   = 30;
		$view->requestConfig->filter_sort_column = 'label';
		$view->requestConfig->filter_sort_order  = 'asc';
		$view->requestConfig->disable_generic_filters=true;

		if (empty($view->config->columns_to_display) && !empty($defaultColumns)) {
			$view->config->columns_to_display = $defaultColumns;
		}

		return $this->renderView($view);
	}

	public function getEvolutionGraphBw(array $columns = array(), array $defaultColumns = array())
	{
		if (empty($columns)) {
			$columns = Common::getRequestVar('columns', false);
			if (false !== $columns) {
				$columns = Piwik::getArrayFromApiParameter($columns);
			}
		}

		$selectableColumns = $defaultColumns;

		$view = $this->getLastUnitGraphAcrossPlugins($this->pluginName, __FUNCTION__, $columns, $selectableColumns = array('traffic_ps'), '', 'QoS.getGraphEvolutionBw');

		$view->config->enable_sort          = false;
		$view->config->max_graph_elements   = 30;
		$view->requestConfig->filter_sort_column = 'label';
		$view->requestConfig->filter_sort_order  = 'asc';
		$view->requestConfig->disable_generic_filters=true;

		if (empty($view->config->columns_to_display) && !empty($defaultColumns)) {
			$view->config->columns_to_display = $defaultColumns;
		}

		return $this->renderView($view);
	}

	public function getEvolutionGraphCacheHit(array $columns = array(), array $defaultColumns = array())
	{
		if (empty($columns)) {
			$columns = Common::getRequestVar('columns', false);
			if (false !== $columns) {
				$columns = Piwik::getArrayFromApiParameter($columns);
			}
		}

		$selectableColumns = $defaultColumns;

		$view = $this->getLastUnitGraphAcrossPlugins($this->pluginName, __FUNCTION__, $columns, $selectableColumns = array('isp_request_count_200_vnpt','isp_request_count_206_vnpt', 'isp_request_count_200_vinaphone','isp_request_count_206_vinaphone'), '', 'QoS.getGraphEvolution');

		$view->config->enable_sort          = false;
		$view->config->max_graph_elements   = 30;
		$view->requestConfig->filter_sort_column = 'label';
		$view->requestConfig->filter_sort_order  = 'asc';
		$view->requestConfig->disable_generic_filters=true;

		if (empty($view->config->columns_to_display) && !empty($defaultColumns)) {
			$view->config->columns_to_display = $defaultColumns;
		}

		return $this->renderView($view);
	}
}
