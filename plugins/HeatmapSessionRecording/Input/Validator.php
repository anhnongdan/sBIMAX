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
namespace Piwik\Plugins\HeatmapSessionRecording\Input;

use Piwik\Piwik;
use Piwik\Site;

class Validator
{
    public function checkWritePermission($idSite)
    {
        $this->checkSiteExists($idSite);
        Piwik::checkUserIsNotAnonymous();
        Piwik::checkUserHasAdminAccess($idSite);
    }

    public function checkHeatmapReportViewPermission($idSite)
    {
        $this->checkSiteExists($idSite);
        Piwik::checkUserHasViewAccess($idSite);
    }

    public function checkSessionReportViewPermission($idSite)
    {
        $this->checkSiteExists($idSite);
        Piwik::checkUserIsNotAnonymous();
        Piwik::checkUserHasViewAccess($idSite);
    }

    private function checkSiteExists($idSite)
    {
        new Site($idSite);
    }

    public function canViewSessionReport($idSite)
    {
        if (empty($idSite)) {
            return false;
        }

        return !Piwik::isUserIsAnonymous() && Piwik::isUserHasViewAccess($idSite);
    }

    public function canViewHeatmapReport($idSite)
    {
        if (empty($idSite)) {
            return false;
        }

        return Piwik::isUserHasViewAccess($idSite);
    }

    public function canWrite($idSite)
    {
        if (empty($idSite)) {
            return false;
        }

        return Piwik::isUserHasAdminAccess($idSite);
    }


}

