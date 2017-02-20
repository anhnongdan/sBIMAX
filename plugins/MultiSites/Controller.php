<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\MultiSites;

use Piwik\Common;
use Piwik\Config;
use Piwik\Date;
use Piwik\Period;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Piwik;
use Piwik\Translation\Translator;
use Piwik\View;
use Piwik\Plugin\Manager;

class Controller extends \Piwik\Plugin\Controller
{
    /**
     * @var Translator
     */
    private $translator;

    public function __construct(Translator $translator)
    {
        parent::__construct();

        $this->translator = $translator;
    }

    /*#Thang 05/30:
     * Called through Front Controller, action:index
     */
    public function index() {
        return $this->getSitesInfo($isWidgetized = false);
    }

    /*#Thang 05/30:
     * Called alone (as with the debug URL) 
     */
    public function standalone() {
        return $this->getSitesInfo($isWidgetized = true);
    }

    //Called after getSiteInfo()
    public function getAllWithGroups() {
        Piwik::checkUserHasSomeViewAccess();

        $period  = Common::getRequestVar('period', null, 'string');
        $date    = Common::getRequestVar('date', null, 'string');
        $segment = Common::getRequestVar('segment', false, 'string');
        $pattern = Common::getRequestVar('pattern', '', 'string');
        $limit   = Common::getRequestVar('filter_limit', 0, 'int');
        $segment = $segment ?: false;
        $request = $_GET + $_POST;

        $dashboard = new Dashboard($period, $date, $segment);

        if ($pattern !== '') {
            $dashboard->search(strtolower($pattern));
        }

        $response = array(
            'numSites' => $dashboard->getNumSites(),
            'totals'   => $dashboard->getTotals(),
            'lastDate' => $dashboard->getLastDate(),
            'sites'    => $dashboard->getSites($request, $limit)
        );

        return json_encode($response);
    }

    public function getSitesInfo($isWidgetized = false)
    {
        Piwik::checkUserHasSomeViewAccess();

        $date   = Common::getRequestVar('date', 'today');
        $period = Common::getRequestVar('period', 'day');

        $view = new View("@MultiSites/getSitesInfo");

        $view->isWidgetized = $isWidgetized;
        
        /**#Thang 05/27
         * Can I centralize checking Bandwidth plugin enabled??
         * (with API getApiMetrics())
         */
        //$view->displayRevenueColumn = Common::isGoalPluginEnabled();
        $view->displayBandwidthColumn = Manager::getInstance()->isPluginActivated('Bandwidth');
        //$view->displayAvgBandwidthColumn = $view->displayBandwidthColumn;
        $view->limit = Config::getInstance()->General['all_websites_website_per_page'];
        // no need for sparlines but leave this here for now
        //$view->show_sparklines = Config::getInstance()->General['show_multisites_sparklines'];

        $view->autoRefreshTodayReport = 0;
        // if the current date is today, or yesterday,
        // in case the website is set to UTC-12), or today in UTC+14, we refresh the page every 5min
        if (in_array($date, array('today', date('Y-m-d'),
                                  'yesterday', Date::factory('yesterday')->toString('Y-m-d'),
                                  Date::factory('now', 'UTC+14')->toString('Y-m-d')))
        ) {
            $view->autoRefreshTodayReport = Config::getInstance()->General['multisites_refresh_after_seconds'];
        }

        $params = $this->getGraphParamsModified();
        //$view->dateSparkline = $period == 'range' ? $date : $params['date'];

        $this->setGeneralVariablesView($view);

        $view->siteName = $this->translator->translate('General_AllWebsitesDashboard');

        return $view->render();
    }

    //Called last to get the Evolution Graph
//    public function getEvolutionGraph($columns = false) {
//        if (empty($columns)) {
//            $columns = Common::getRequestVar('columns');
//        }
//        $api = "API.get";
//
//        if ($columns == 'revenue') {
//            $api = "Goals.get";
//        }
//        
//        $view = $this->getLastUnitGraph($this->pluginName, __FUNCTION__, $api);
//        return $this->renderView($view);
//    }
    
    /*
     * Thang 05/26: Refactor this function to pull data into BYTES TRANSFERED OVERALL 
     * column. No need for Evolution Sparkline anymore
     * TODO: Refactor this function name as well.
     */    
//    public function getEvolutionGraph($columns = false) {
//        if (empty($columns)) {
//            $columns = Common::getRequestVar('columns');
//        }
//        $api = "API.get";
//
//        //could the column show up??
//        //I don't think that the revenue column is retreive here. Anw it's allright.
//        //I MUST keep the API as API.get
//        //if ($columns == 'nb_total_overall_bandwidth') {
//        //    $api = "Goals.get";
//        //}
//        //$view = $this->getLastUnitGraph($this->pluginName, __FUNCTION__, $api);
//        
//        $view = ViewDataTableFactory::build(
//                        $defaultType = null, $api, $this->pluginName . '.' . __FUNCTION__);
//        
//        return $this->renderView($view);
//    }
    
}
