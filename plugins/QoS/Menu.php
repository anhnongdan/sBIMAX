<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\QoS;

use Piwik\Menu\MenuReporting;
use Piwik\Piwik;


class Menu extends \Piwik\Plugin\Menu
{
	public function configureReportingMenu(MenuReporting $menu)
	{
		if (!Piwik::hasUserSuperUserAccess()) return;

		$menu->registerMenuIcon(Piwik::translate('QoS_QoS'), 'icon-chart-bar');
		$menu->addItem(Piwik::translate('QoS_QoS'), '', array(), 20);

		$this->addSubMenu($menu, Piwik::translate('QoS_Overview'),  'overview',     1);
		$this->addSubMenu($menu, Piwik::translate('QoS_Bandwidth'), 'bandwidth',    2);
		$this->addSubMenu($menu, Piwik::translate('QoS_UserSpeed'), 'userSpeed',    3);
		$this->addSubMenu($menu, Piwik::translate('QoS_CacheHit'),  'cacheHit',     4);
		$this->addSubMenu($menu, Piwik::translate('QoS_HttpCode'),  'httpCode',     5);
		$this->addSubMenu($menu, Piwik::translate('QoS_ISP'),       'isp',          6);
		$this->addSubMenu($menu, Piwik::translate('QoS_Country'),   'country',      7);
	}

	private function addSubMenu(MenuReporting $menu, $subMenu, $action, $order)
	{
		$menu->addItem(Piwik::translate('QoS_QoS'), $subMenu, $this->urlForAction($action), $order);
	}
}