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
namespace Piwik\Plugins\MediaAnalytics\Columns;

use Piwik\Columns\Dimension;
use Piwik\Piwik;
use Piwik\Plugins\MediaAnalytics\Dao\LogTable;
use Piwik\Plugins\MediaAnalytics\Segment;

class SpentTime extends Dimension
{

    protected function configureSegments()
    {
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
        $this->addSegment($segment);
    }

    public function getName()
    {
        return Piwik::translate('MediaAnalytics_SegmentNameSpentTime');
    }
}