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

use Piwik\Menu\MenuReporting;

/**
 * This class allows you to add, remove or rename menu items.
 * To configure a menu (such as Admin Menu, Reporting Menu, User Menu...) simply call the corresponding methods as
 * described in the API-Reference http://developer.piwik.org/api-reference/Piwik/Menu/MenuAbstract
 */
class Menu extends \Piwik\Plugin\Menu
{
    public function configureReportingMenu(MenuReporting $menu)
    {
        $menu->registerMenuIcon('MediaAnalytics_Media', 'icon-folder-charts');
        $menu->addItem('MediaAnalytics_Media', '', $this->urlForDefaultAction(), $orderId = 30);
        $menu->addItem('MediaAnalytics_Media', 'General_Overview', $this->urlForAction('overview'), $orderId = 30);
        $menu->addItem('MediaAnalytics_Media', 'MediaAnalytics_MenuRealTime', $this->urlForAction('live'), $orderId = 31);
        $menu->addItem('MediaAnalytics_Media', 'MediaAnalytics_MenuVideo', $this->urlForAction('video'), $orderId = 32);
        $menu->addItem('MediaAnalytics_Media', 'MediaAnalytics_MenuAudio', $this->urlForAction('audio'), $orderId = 33);
        $menu->addItem('MediaAnalytics_Media', 'MediaAnalytics_MenuMediaVisitorLog', $this->urlForAction('audienceLogReport'), $orderId = 38);
        $menu->addItem('MediaAnalytics_Media', 'MediaAnalytics_MenuAudienceMap', $this->urlForAction('audienceMap'), $orderId = 39);
    }

}
