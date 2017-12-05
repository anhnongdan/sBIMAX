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

namespace Piwik\Plugins\AbTesting\Widgets;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

class GettingStarted extends Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId('AbTesting_Experiments');
        $config->setIsNotWidgetizable();
        $config->setName('AbTesting_GettingStarted');
        $config->setOrder(10);

        $idSite = Common::getRequestVar('idSite', 0, 'int');
        if (self::shouldEnable($idSite)) {
            $experiments = StaticContainer::get('Piwik\Plugins\AbTesting\Model\Experiments');
            $experiments = $experiments->getExperimentsWithReports($idSite);
            if (count($experiments) === 0) {
                // we only make it visible in the UI when there are no experiments. We cannot disable/enable it
                // as we otherwise would show an error message "not allowed to view widget" when suddenly
                // experiments are configured
                $config->setSubcategoryId('AbTesting_GettingStarted');
            }
        }
    }

    private static function shouldEnable($idSite)
    {
        return !empty($idSite) && Piwik::isUserHasViewAccess($idSite) && !Piwik::isUserHasAdminAccess($idSite);
    }

    /**
     * This method renders the widget. It's on you how to generate the content of the widget.
     * As long as you return a string everything is fine. You can use for instance a "Piwik\View" to render a
     * twig template. In such a case don't forget to create a twig template (eg. myViewTemplate.twig) in the
     * "templates" directory of your plugin.
     *
     * @return string
     */
    public function render()
    {
        $idSite = Common::getRequestVar('idSite', null, 'int');
        Piwik::checkUserHasViewAccess($idSite);

        $isAdmin = Piwik::isUserHasAdminAccess($idSite);

        return $this->renderTemplate('gettingStarted.twig', array(
            'isAdmin' => $isAdmin
        ));
    }

}