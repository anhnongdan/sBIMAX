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

(function ($, require) {

    var exports = require('piwik/UI'),
        DataTable = exports.DataTable,
        dataTablePrototype = DataTable.prototype;

    /**
     * UI control that handles extra functionality for Media datatables.
     *
     * @constructor
     */
    exports.LiveMediaDataTable = function (element) {
        this.parentAttributeParent = '';
        this.parentId = '';

        DataTable.call(this, element);
    };

    $.extend(exports.LiveMediaDataTable.prototype, dataTablePrototype, {

        postBindEventsAndApplyStyleHook: function (domElem) {
            this.refreshTable();
        },

        refreshTable: function () {
            if (this.refreshTimeout || !this.param.updateInterval) {
                return;
            }

            var self = this;
            this.refreshTimeout = setTimeout(function () {
                self.reloadAjaxDataTable(false, function () {
                    var $wrapper = $(self.$element).find('.dataTableWrapper');
                    var $columns = $wrapper.find('td');

                    if ($columns.size()) {
                        $wrapper = $columns;
                        // if there are columns, we prefer to update columns but there might be none when there is no data
                    }
                    $wrapper.effect('highlight', {}, 600);
                    self.refreshTimeout = null;
                    self.refreshTable();
                });
            }, this.param.updateInterval);
        }
    });

})(jQuery, require);
