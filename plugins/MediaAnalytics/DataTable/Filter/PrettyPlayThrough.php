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
namespace Piwik\Plugins\MediaAnalytics\DataTable\Filter;

use Piwik\DataTable;
use Piwik\Plugins\MediaAnalytics\Archiver;

class PrettyPlayThrough extends DataTable\BaseFilter
{
    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        $table->filter('ColumnCallbackReplace', array('label', function ($value) {
            if ($value === 0) {
                return 'Below 25%';
            }
            if ($value === 1) {
                return '25% to 50%';
            }
            if ($value === 2) {
                return '50% to 75%';
            }
            if ($value === 3) {
                return 'Above 75%';
            }
            return 'other';
        }));
    }
}
