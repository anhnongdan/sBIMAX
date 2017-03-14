<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CoreAdminHome\Commands;

use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\Period;
use Piwik\Period\Range;
use Piwik\Piwik;
use Piwik\Segment;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;
use Piwik\Site;
use Piwik\Period\Factory as PeriodFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Piwik\Log;

/**
 * [Thangnt 2016-10-27] This class defines the console command for 
 * 
 * Provides a simple interface for invalidating report data by date ranges, site IDs and periods.
 */
class InvalidateReportData extends ConsoleCommand
{
    const ALL_OPTION_VALUE = 'all';

    protected function configure()
    {
        $this->setName('core:invalidate-report-data');
        $this->setDescription('Invalidate archived report data by date range, site and period.');
        $this->addOption('dates', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
            'List of dates or date ranges to invalidate report data for, eg, 2015-01-03 or 2015-01-05,2015-02-12.');
        $this->addOption('sites', null, InputOption::VALUE_REQUIRED,
            'List of site IDs to invalidate report data for, eg, "1,2,3,4" or "all" for all sites.',
            self::ALL_OPTION_VALUE);
        $this->addOption('periods', null, InputOption::VALUE_REQUIRED,
            'List of period types to invalidate report data for. Can be one or more of the following values: hour, day, '
            . 'week, month, year or "all" for all of them.',
            self::ALL_OPTION_VALUE);
        $this->addOption('segment', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
            'List of segments to invalidate report data for.');
        $this->addOption('cascade', null, InputOption::VALUE_NONE,
            'If supplied, invalidation will cascade, invalidating child period types even if they aren\'t specified in'
            . ' --periods. For example, if --periods=week, --cascade will cause the days within those weeks to be '
            . 'invalidated as well. If --periods=month, then weeks and days will be invalidated. Note: if a period '
            . 'falls partly outside of a date range, then --cascade will also invalidate data for child periods '
            . 'outside the date range. For example, if --dates=2015-09-14,2015-09-15 & --periods=week, --cascade will'
            . ' also invalidate all days within 2015-09-13,2015-09-19, even those outside the date range.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'For tests. Runs the command w/o actually '
            . 'invalidating anything.');
        $this->setHelp('Invalidate archived report data by date range, site and period. Invalidated archive data will '
            . 'be re-archived during the next core:archive run. If your log data has changed for some reason, this '
            . 'command can be used to make sure reports are generated using the new, changed log data.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $invalidator = StaticContainer::get('Piwik\Archive\ArchiveInvalidator');

        $cascade = $input->getOption('cascade');
        $dryRun = $input->getOption('dry-run');

        $sites = $this->getSitesToInvalidateFor($input);
        
        // Thangnt: the input periods can not be range 
        // although if a (continuous) range of other periods 
        // is input it will be translate as range
        $periodTypes = $this->getPeriodTypesToInvalidateFor($input);
        
        // The --dates string
        $dateRanges = $this->getDateRangesToInvalidateFor($input);
        
        $segments = $this->getSegmentsToInvalidateFor($input, $sites);

        foreach ($periodTypes as $periodType) {
            foreach ($dateRanges as $dateRange) {
                foreach ($segments as $segment) {
                    $segmentStr = $segment ? $segment->getString() : '';

                    $output->writeln("Invalidating $periodType periods in $dateRange [segment = $segmentStr]...");

                    // @Thangnt: date range will be translated to an array of Startdate
                    // These $dates have timezone of UTC so it complies with datetime in Database
                    if (strlen($dateRange)>21 && $periodType != 'hour') {
                        $trimedDateRange = $this->trimOutTimeFromDatetime($dateRange);
                        $dates = $this->getPeriodDates($periodType, $trimedDateRange);
                    } else {
                        $dates = $this->getPeriodDates($periodType, $dateRange);
                    }
//                    echo "When InvaldateReportData executes: \n";
//                    var_dump($dates);
//                    echo "****\n";
                    
                    if ($dryRun) {
                        $output->writeln("[Dry-run] invalidating archives for site = [ " . implode(', ', $sites)
                            . " ], dates = [ " . implode(', ', $dates) . " ], period = [ $periodType ], segment = [ "
                            . "$segmentStr ], cascade = [ " . (int)$cascade . " ]");
                    } else {
                        $invalidationResult = $invalidator->markArchivesAsInvalidated($sites, $dates, $periodType, $segment, $cascade);

                        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
                            $output->writeln($invalidationResult->makeOutputLogs());
                        }
                    }
                }
            }
        }
    }

    private function getSitesToInvalidateFor(InputInterface $input)
    {
        $sites = $input->getOption('sites');

        $siteIds = Site::getIdSitesFromIdSitesString($sites);
        if (empty($siteIds)) {
            throw new \InvalidArgumentException("Invalid --sites value: '$sites'.");
        }

        $allSiteIds = SitesManagerAPI::getInstance()->getAllSitesId();
        foreach ($siteIds as $idSite) {
            if (!in_array($idSite, $allSiteIds)) {
                throw new \InvalidArgumentException("Invalid --sites value: '$sites', there are no sites with IDs = $idSite");
            }
        }

        return $siteIds;
    }

    private function getPeriodTypesToInvalidateFor(InputInterface $input)
    {
        $periods = $input->getOption('periods');
        
        //Thangnt: although $periods arrays is checked empty, the fact is that
        // if no --periods is supplied in the command all periods will be invalidated,
        // the same as --periods=all
        if (empty($periods)) {
            throw new \InvalidArgumentException("The --periods argument is required.");
        }

        if ($periods == self::ALL_OPTION_VALUE) {
            $result = array_keys(Piwik::$idPeriods);
            unset($result[4]); // remove 'range' period
            return $result;
        }

        /**
         * [Thangnt 2016-11-10] In case of HOUR, the related RANGE also needs to
         * be invalidated as well. 
         */
        if ($periods == 'hour') {
            $result = array_keys(Piwik::$idPeriods);
            return $result;
        }
        
        $periods = explode(',', $periods);
        $periods = array_map('trim', $periods);

        foreach ($periods as $periodIdentifier) {
            if ($periodIdentifier == 'range') {
                throw new \InvalidArgumentException(
                    "Invalid period type: invalidating range periods is not currently supported.");
            }

            if (!isset(Piwik::$idPeriods[$periodIdentifier])) {
                throw new \InvalidArgumentException("Invalid period type '$periodIdentifier' supplied in --periods.");
            }
        }

        return $periods;
    }

    /**
     * @param InputInterface $input
     * @return Date[][]
     */
    private function getDateRangesToInvalidateFor(InputInterface $input)
    {
        $dateRanges = $input->getOption('dates');
        if (empty($dateRanges)) {
            throw new \InvalidArgumentException("The --dates option is required.");
        }

        return $dateRanges;
    }

    private function getPeriodDates($periodType, $dateRange)
    {
        if (!isset(Piwik::$idPeriods[$periodType])) {
            throw new \InvalidArgumentException("Invalid period type '$periodType'.");
        }

        try {
            Log::debug("InvalidateReportData::getPeriodDates: take $dateRange of $periodType.");
            $period = PeriodFactory::build($periodType, $dateRange);
        } catch (\Exception $ex) {
            throw new \InvalidArgumentException("Invalid date or date range specifier (if you put a space after the comma ',' please delete it) '$dateRange'", $code = 0, $ex);
        }

        $result = array();
        if ($period instanceof Range) {
            foreach ($period->getSubperiods() as $subperiod) {
                $result[] = $subperiod->getDateStart();
            }
        } else {
            $result[] = $period->getDateStart();
        }
        return $result;
    }

    private function getSegmentsToInvalidateFor(InputInterface $input, $idSites)
    {
        $segments = $input->getOption('segment');
        $segments = array_map('trim', $segments);
        $segments = array_unique($segments);

        if (empty($segments)) {
            return array(null);
        }

        $result = array();
        foreach ($segments as $segmentString) {
            $result[] = new Segment($segmentString, $idSites);
        }
        return $result;
    }
    
    /**
     * [Thangnt 2016-11-10] Process date range string for other periods 
     * which are processed along with Hour.
     */
    private function trimOutTimeFromDatetime($dateRange) 
    {
	$s1 = explode(",", $dateRange);
	$s2 = explode(" ", $s1[0]);
	$s3 = explode(" ", $s1[1]);	
	return $s2[0].','.$s3[0];
    }
}
