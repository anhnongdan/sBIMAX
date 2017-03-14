/*!
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
(function () {
    angular.module('piwikApp').controller('ManageRollUpController', ManageRollUpController);

    ManageRollUpController.$inject = ['$scope', 'piwikApi', 'siteSelectorModel', '$filter'];

    function ManageRollUpController($scope, piwikApi, siteSelectorModel, $filter) {
        siteSelectorModel.loadInitialSites();

        var allSitesPromise = piwikApi.fetch({method: 'SitesManager.getSitesWithAdminAccess', filter_limit: -1});

        var self = this;

        this.showAllSites = false;

        this.initPropertiesIfNeeded = function () {
            if ('undefined' === typeof this.sites) {
                this.sites = [];
            }

            if ('undefined' === typeof this.siteIds) {
                this.siteIds = [];
            }
        }

        this.addSite = function (site) {
            this.initPropertiesIfNeeded();

            if (this.showAllSites) {
                return;
            }

            if (site && site.id) {
                if (site.id === 'all') {
                    this.siteIds = [];
                    this.showAllSites = true;
                }

                // we only add the site id if it was not added before
                if (-1 === this.siteIds.indexOf(site.id)) {
                    this.siteIds.push(site.id);

                    this.updateSites();
                }
            }
        };

        this.removeSite = function (site) {
            this.initPropertiesIfNeeded();

            var index = this.siteIds.indexOf(site.id);
            var index2 = this.sites.indexOf(site);

            if (index > -1) {
                this.siteIds.splice(index, 1);
                if (this.siteIds.length === 0) {
                    this.showAllSites = false;
                }
            }

            if (index2 > -1) {
                this.sites.splice(index2, 1);
            }
        };

        this.updateSites = function () {
            if (this.siteIds && this.siteIds.indexOf('all') > -1) {
                this.showAllSites = true;
                this.sites = [{name: $filter('translate')('RollUpReporting_AllMeasurablesAssigned'), id: 'all'}];
                return;
            }

            allSitesPromise.then(function (allSites) {
                if (allSites && allSites.length && self.siteIds) {
                    self.sites = [];
                    for (var i = 0; i < self.siteIds.length; i++) {
                        var idSite = self.siteIds[i];

                        for (var j = 0; j < allSites.length; j++) {
                            if (allSites[j] && allSites[j].idsite == idSite) {
                                self.sites.push({name: allSites[j].name, id: idSite});
                            }
                        }

                    }
                }
            });
        }

        $scope.$watch('manageRollUp.siteIds', function (val, oldVal) {
            // we only update it when it is out of sync for some reason, otherwise we update directly when adding
            // a site or removing a site
            self.updateSites();
        });

    }
})();