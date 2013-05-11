<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik
 * @package Piwik
 */
/**
// TODO: for getDataTableNumeric, this means one row per site/period. is this correct? Will this be the case for every get... method? Need to make this clear somewhere, piwik docs don't mention anything about this.
// TODO: add test for ts_archived (in metadata)
// TODO: create ticket for this: when building archives, should use each site's timezone (ONLY FOR 'now'). 
*/
/**
 * The archive object is used to query specific data for a day or a period of statistics for a given website.
 *
 * Example:
 * <pre>
 *        $archive = Piwik_Archive::build($idSite = 1, $period = 'week', '2008-03-08');
 *        $dataTable = $archive->getDataTable('Provider_hostnameExt');
 *        $dataTable->queueFilter('ReplaceColumnNames');
 *        return $dataTable;
 * </pre>
 *
 * Example bis:
 * <pre>
 *        $archive = Piwik_Archive::build($idSite = 3, $period = 'day', $date = 'today');
 *        $nbVisits = $archive->getNumeric('nb_visits');
 *        return $nbVisits;
 * </pre>
 *
 * If the requested statistics are not yet processed, Archive uses ArchiveProcessing to archive the statistics.
 *
 * @package Piwik
 * @subpackage Piwik_Archive
 */
class Piwik_Archive
{
    /**
     * When saving DataTables in the DB, we sometimes replace the columns name by these IDs so we save up lots of bytes
     * Eg. INDEX_NB_UNIQ_VISITORS is an integer: 4 bytes, but 'nb_uniq_visitors' is 16 bytes at least
     * (in php it's actually even much more)
     *
     */
    const INDEX_NB_UNIQ_VISITORS = 1;
    const INDEX_NB_VISITS = 2;
    const INDEX_NB_ACTIONS = 3;
    const INDEX_MAX_ACTIONS = 4;
    const INDEX_SUM_VISIT_LENGTH = 5;
    const INDEX_BOUNCE_COUNT = 6;
    const INDEX_NB_VISITS_CONVERTED = 7;
    const INDEX_NB_CONVERSIONS = 8;
    const INDEX_REVENUE = 9;
    const INDEX_GOALS = 10;
    const INDEX_SUM_DAILY_NB_UNIQ_VISITORS = 11;

    // Specific to the Actions reports
    const INDEX_PAGE_NB_HITS = 12;
    const INDEX_PAGE_SUM_TIME_SPENT = 13;

    const INDEX_PAGE_EXIT_NB_UNIQ_VISITORS = 14;
    const INDEX_PAGE_EXIT_NB_VISITS = 15;
    const INDEX_PAGE_EXIT_SUM_DAILY_NB_UNIQ_VISITORS = 16;

    const INDEX_PAGE_ENTRY_NB_UNIQ_VISITORS = 17;
    const INDEX_PAGE_ENTRY_SUM_DAILY_NB_UNIQ_VISITORS = 18;
    const INDEX_PAGE_ENTRY_NB_VISITS = 19;
    const INDEX_PAGE_ENTRY_NB_ACTIONS = 20;
    const INDEX_PAGE_ENTRY_SUM_VISIT_LENGTH = 21;
    const INDEX_PAGE_ENTRY_BOUNCE_COUNT = 22;

    // Ecommerce Items reports
    const INDEX_ECOMMERCE_ITEM_REVENUE = 23;
    const INDEX_ECOMMERCE_ITEM_QUANTITY = 24;
    const INDEX_ECOMMERCE_ITEM_PRICE = 25;
    const INDEX_ECOMMERCE_ORDERS = 26;
    const INDEX_ECOMMERCE_ITEM_PRICE_VIEWED = 27;

    // Site Search
    const INDEX_SITE_SEARCH_HAS_NO_RESULT = 28;
    const INDEX_PAGE_IS_FOLLOWING_SITE_SEARCH_NB_HITS = 29;

    // Performance Analytics
    const INDEX_PAGE_SUM_TIME_GENERATION = 30;
    const INDEX_PAGE_NB_HITS_WITH_TIME_GENERATION = 31;
    const INDEX_PAGE_MIN_TIME_GENERATION = 32;
    const INDEX_PAGE_MAX_TIME_GENERATION = 33;

    // Goal reports
    const INDEX_GOAL_NB_CONVERSIONS = 1;
    const INDEX_GOAL_REVENUE = 2;
    const INDEX_GOAL_NB_VISITS_CONVERTED = 3;

    const INDEX_GOAL_ECOMMERCE_REVENUE_SUBTOTAL = 4;
    const INDEX_GOAL_ECOMMERCE_REVENUE_TAX = 5;
    const INDEX_GOAL_ECOMMERCE_REVENUE_SHIPPING = 6;
    const INDEX_GOAL_ECOMMERCE_REVENUE_DISCOUNT = 7;
    const INDEX_GOAL_ECOMMERCE_ITEMS = 8;

    public static $mappingFromIdToName = array(
        Piwik_Archive::INDEX_NB_UNIQ_VISITORS                      => 'nb_uniq_visitors',
        Piwik_Archive::INDEX_NB_VISITS                             => 'nb_visits',
        Piwik_Archive::INDEX_NB_ACTIONS                            => 'nb_actions',
        Piwik_Archive::INDEX_MAX_ACTIONS                           => 'max_actions',
        Piwik_Archive::INDEX_SUM_VISIT_LENGTH                      => 'sum_visit_length',
        Piwik_Archive::INDEX_BOUNCE_COUNT                          => 'bounce_count',
        Piwik_Archive::INDEX_NB_VISITS_CONVERTED                   => 'nb_visits_converted',
        Piwik_Archive::INDEX_NB_CONVERSIONS                        => 'nb_conversions',
        Piwik_Archive::INDEX_REVENUE                               => 'revenue',
        Piwik_Archive::INDEX_GOALS                                 => 'goals',
        Piwik_Archive::INDEX_SUM_DAILY_NB_UNIQ_VISITORS            => 'sum_daily_nb_uniq_visitors',

        // Actions metrics
        Piwik_Archive::INDEX_PAGE_NB_HITS                          => 'nb_hits',
        Piwik_Archive::INDEX_PAGE_SUM_TIME_SPENT                   => 'sum_time_spent',
        Piwik_Archive::INDEX_PAGE_SUM_TIME_GENERATION              => 'sum_time_generation',
        Piwik_Archive::INDEX_PAGE_NB_HITS_WITH_TIME_GENERATION     => 'nb_hits_with_time_generation',
        Piwik_Archive::INDEX_PAGE_MIN_TIME_GENERATION              => 'min_time_generation',
        Piwik_Archive::INDEX_PAGE_MAX_TIME_GENERATION              => 'max_time_generation',

        Piwik_Archive::INDEX_PAGE_EXIT_NB_UNIQ_VISITORS            => 'exit_nb_uniq_visitors',
        Piwik_Archive::INDEX_PAGE_EXIT_NB_VISITS                   => 'exit_nb_visits',
        Piwik_Archive::INDEX_PAGE_EXIT_SUM_DAILY_NB_UNIQ_VISITORS  => 'sum_daily_exit_nb_uniq_visitors',

        Piwik_Archive::INDEX_PAGE_ENTRY_NB_UNIQ_VISITORS           => 'entry_nb_uniq_visitors',
        Piwik_Archive::INDEX_PAGE_ENTRY_SUM_DAILY_NB_UNIQ_VISITORS => 'sum_daily_entry_nb_uniq_visitors',
        Piwik_Archive::INDEX_PAGE_ENTRY_NB_VISITS                  => 'entry_nb_visits',
        Piwik_Archive::INDEX_PAGE_ENTRY_NB_ACTIONS                 => 'entry_nb_actions',
        Piwik_Archive::INDEX_PAGE_ENTRY_SUM_VISIT_LENGTH           => 'entry_sum_visit_length',
        Piwik_Archive::INDEX_PAGE_ENTRY_BOUNCE_COUNT               => 'entry_bounce_count',
        Piwik_Archive::INDEX_PAGE_IS_FOLLOWING_SITE_SEARCH_NB_HITS => 'nb_hits_following_search',

        // Items reports metrics
        Piwik_Archive::INDEX_ECOMMERCE_ITEM_REVENUE                => 'revenue',
        Piwik_Archive::INDEX_ECOMMERCE_ITEM_QUANTITY               => 'quantity',
        Piwik_Archive::INDEX_ECOMMERCE_ITEM_PRICE                  => 'price',
        Piwik_Archive::INDEX_ECOMMERCE_ITEM_PRICE_VIEWED           => 'price_viewed',
        Piwik_Archive::INDEX_ECOMMERCE_ORDERS                      => 'orders',
    );

    public static $mappingFromIdToNameGoal = array(
        Piwik_Archive::INDEX_GOAL_NB_CONVERSIONS             => 'nb_conversions',
        Piwik_Archive::INDEX_GOAL_NB_VISITS_CONVERTED        => 'nb_visits_converted',
        Piwik_Archive::INDEX_GOAL_REVENUE                    => 'revenue',
        Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_SUBTOTAL => 'revenue_subtotal',
        Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_TAX      => 'revenue_tax',
        Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_SHIPPING => 'revenue_shipping',
        Piwik_Archive::INDEX_GOAL_ECOMMERCE_REVENUE_DISCOUNT => 'revenue_discount',
        Piwik_Archive::INDEX_GOAL_ECOMMERCE_ITEMS            => 'items',
    );

    /**
     * string indexed column name => Integer indexed column name
     * @var array
     */
    public static $mappingFromNameToId = array(
        'nb_uniq_visitors'           => Piwik_Archive::INDEX_NB_UNIQ_VISITORS,
        'nb_visits'                  => Piwik_Archive::INDEX_NB_VISITS,
        'nb_actions'                 => Piwik_Archive::INDEX_NB_ACTIONS,
        'max_actions'                => Piwik_Archive::INDEX_MAX_ACTIONS,
        'sum_visit_length'           => Piwik_Archive::INDEX_SUM_VISIT_LENGTH,
        'bounce_count'               => Piwik_Archive::INDEX_BOUNCE_COUNT,
        'nb_visits_converted'        => Piwik_Archive::INDEX_NB_VISITS_CONVERTED,
        'nb_conversions'             => Piwik_Archive::INDEX_NB_CONVERSIONS,
        'revenue'                    => Piwik_Archive::INDEX_REVENUE,
        'goals'                      => Piwik_Archive::INDEX_GOALS,
        'sum_daily_nb_uniq_visitors' => Piwik_Archive::INDEX_SUM_DAILY_NB_UNIQ_VISITORS,
    );

    /**
     * Metrics calculated and archived by the Actions plugin.
     *
     * @var array
     */
    public static $actionsMetrics = array(
        'nb_pageviews',
        'nb_uniq_pageviews',
        'nb_downloads',
        'nb_uniq_downloads',
        'nb_outlinks',
        'nb_uniq_outlinks',
        'nb_searches',
        'nb_keywords',
        'nb_hits',
        'nb_hits_following_search',
    );

    const LABEL_ECOMMERCE_CART = 'ecommerceAbandonedCart';
    const LABEL_ECOMMERCE_ORDER = 'ecommerceOrder';
    
    /**
     * The list of site IDs to query archive data for.
     * 
     * @var array
     */
    private $siteIds;
    
    /**
     * The list of Piwik_Period's to query archive data for.
     * 
     * @var array
     */
    public $periods;
    
    /**
     * Segment applied to the visits set.
     * 
     * @var Piwik_Segment
     */
    private $segment;
    
    /**
     * List of archive IDs for the sites, periods and segment we are querying with.
     * Archive IDs are indexed by done flag and period, ie:
     * 
     * array(
     *     'done.Referers' => array(
     *         '2010-01-01' => 1,
     *         '2010-01-02' => 2,
     *     ),
     *     'done.VisitsSummary' => array(
     *         '2010-01-01' => 3,
     *         '2010-01-02' => 4,
     *     ),
     * )
     * 
     * or,
     * 
     * array(
     *     'done.all' => array(
     *         '2010-01-01' => 1,
     *         '2010-01-02' => 2
     *     )
     * )
     * 
     * @var array
     */
    private $idarchives = array();
    
    /**
     * If set to true, the result of all get functions (ie, getNumeric, getBlob, etc.)
     * will be indexed by the site ID, even if we're only querying data for one site.
     * 
     * @var bool
     */
    private $forceIndexedBySite;
    
    /**
     * If set to true, the result of all get functions (ie, getNumeric, getBlob, etc.)
     * will be indexed by the period, even if we're only querying data for one period.
     * 
     * @var bool
     */
    private $forceIndexedByDate;
    
    /**
     * Cache of Piwik_ArchiveProcessing instances used when launching the archiving
     * process.
     * 
     * @var array
     */
    private $processingCache = array();
    
    /**
     * Constructor.
     * 
     * @param array|int $siteIds List of site IDs to query data for.
     * @param array|Piwik_Period $periods List of periods to query data for.
     * @param Piwik_Segment $segment The segment used to narrow the visits set.
     * @param bool $forceIndexedBySite Whether to force index the result of a query by site ID.
     * @param bool $forceIndexedByDate Whether to force index the result of a query by period.
     */
    public function __construct($siteIds, $periods, Piwik_Segment $segment, $forceIndexedBySite = false,
                                  $forceIndexedByDate = false)
    {
        $this->siteIds = $this->getAsNonEmptyArray($siteIds, 'siteIds');
        
        $periods = $this->getAsNonEmptyArray($periods, 'periods');
        $this->periods = array();
        foreach ($periods as $period) {
            $this->periods[$period->getRangeString()] = $period;
        }
        
        $this->segment = $segment;
        $this->forceIndexedBySite = $forceIndexedBySite;
        $this->forceIndexedByDate = $forceIndexedByDate;
    }
    
    /**
     * Destructor.
     */
    public function __destruct()
    {
        $this->periods = null;
        $this->siteIds = null;
        $this->segment = null;
        $this->idarchives = array();
    }

    /**
     * Builds an Archive object using query parameter values.
     *
     * @param int|string $idSite Integer, or comma separated list of integer site IDs.
     * @param string $period 'day', 'week', 'month', 'year' or 'range'
     * @param Piwik_Date|string $strDate 'YYYY-MM-DD', magic keywords (ie, 'today'; @see Piwik_Date::factory())
     *                                   or date range (ie, 'YYYY-MM-DD,YYYY-MM-DD').
     * @param false|string $segment Segment definition - defaults to false for backward compatibility.
     * @param false|string $_restrictSitesToLogin Used only when running as a scheduled task.
     * @return Piwik_Archive
     */
    public static function build($idSite, $period, $strDate, $segment = false, $_restrictSitesToLogin = false)
    {
        $forceIndexedBySite = false;
        $forceIndexedByDate = false;
        
        // determine site IDs to query from
        if (is_array($idSite)
            || $idSite == 'all'
        ) {
            $forceIndexedBySite = true;
        }
        $sites = Piwik_Site::getIdSitesFromIdSitesString($idSite);
        
        // if a period date string is detected: either 'last30', 'previous10' or 'YYYY-MM-DD,YYYY-MM-DD'
        if (is_string($strDate)
            && self::isMultiplePeriod($strDate, $period)
        ) {
            $oPeriod = new Piwik_Period_Range($period, $strDate);
            $allPeriods = $oPeriod->getSubperiods();
            $forceIndexedByDate = true;
        } else {
            if (count($sites) == 1) {
                $oSite = new Piwik_Site($sites[0]);
            } else {
                $oSite = null;
            }
            
            $oPeriod = Piwik_Archive::makePeriodFromQueryParams($oSite, $period, $strDate);
            $allPeriods = array($oPeriod);
        }
        
        return new Piwik_Archive(
            $sites, $allPeriods, new Piwik_Segment($segment, $sites), $forceIndexedBySite, $forceIndexedByDate);
    }

    /**
     * Creates a period instance using a Piwik_Site instance and two strings describing
     * the period & date.
     *
     * @param Piwik_Site|null $site
     * @param string $strPeriod The period string: day, week, month, year, range
     * @param string $strDate The date or date range string.
     * @return Piwik_Period
     */
    public static function makePeriodFromQueryParams($site, $strPeriod, $strDate)
    {
        if ($site === null) {
            $tz = 'UTC';
        } else {
            $tz = $site->getTimezone();
        }

        if ($strPeriod == 'range') {
            $oPeriod = new Piwik_Period_Range('range', $strDate, $tz, Piwik_Date::factory('today', $tz));
        } else {
            $oDate = $strDate;
            if (!($strDate instanceof Piwik_Date)) {
                if ($strDate == 'now'
                    || $strDate == 'today'
                ) {
                    $strDate = date('Y-m-d', Piwik_Date::factory('now', $tz)->getTimestamp());
                } elseif ($strDate == 'yesterday'
                          || $strDate == 'yesterdaySameTime'
                ) {
                    $strDate = date('Y-m-d', Piwik_Date::factory('now', $tz)->subDay(1)->getTimestamp());
                }
                $oDate = Piwik_Date::factory($strDate);
            }
            $date = $oDate->toString();
            $oPeriod = Piwik_Period::factory($strPeriod, $oDate);
        }

        return $oPeriod;
    }
    
    /**
     * Returns the value of the element $name from the current archive 
     * The value to be returned is a numeric value and is stored in the archive_numeric_* tables
     *
     * @param string|array $names One or more archive names, eg, 'nb_visits', 'Referers_distinctKeywords',
     *                            etc.
     * @return numeric|array|false False if no value with the given name, numeric if only one site
     *                             and date and we're not forcing an index, and array if multiple
     *                             sites/dates are queried.
     */
    public function getNumeric($names)
    {
        $data = $this->get($names, 'numeric');
        return $data->getArray($this->getResultIndices());
    }
    
    /**
     * Returns the value of the elements in $names from the current archive.
     * 
     * The value to be returned is a blob value and is stored in the archive_blob_* tables.
     * 
     * It can return anything from strings, to serialized PHP arrays or PHP objects, etc.
     *
     * @param string|array $names One or more archive names, eg, 'Referers_keywordBySearchEngine'.
     * @return string|array|false False if no value with the given name, numeric if only one site
     *                            and date and we're not forcing an index, and array if multiple
     *                            sites/dates are queried.
     */
    public function getBlob($names, $idSubtable = null)
    {
        $data = $this->get($names, 'blob', $idSubtable);
        return $data->getArray($this->getResultIndices());
    }
    
    /**
     * Returns the numeric values of the elements in $names as a DataTable.
     * 
     * @param string|array $names One or more archive names, eg, 'nb_visits', 'Referers_distinctKeywords',
     *                            etc.
     * @return Piwik_DataTable|false False if no value with the given names. Based on the number
     *                               of sites/periods, the result can be a DataTable_Array, which
     *                               contains DataTable instances.
     */
    public function getDataTableFromNumeric($names)
    {
        $data = $this->get($names, 'numeric');
        $dataTable = $data->getDataTable($this->getResultIndices());
        $this->transformMetadata($dataTable);
        return $dataTable;
    }

    /**
     * This method will build a dataTable from the blob value $name in the current archive.
     * 
     * For example $name = 'Referers_searchEngineByKeyword' will return a
     * Piwik_DataTable containing all the keywords. If a $idSubtable is given, the method
     * will return the subTable of $name. If 'all' is supplied for $idSubtable every subtable
     * will be returned.
     * 
     * @param string $name The name of the record to get.
     * @param int|string|null $idSubtable The subtable ID (if any) or 'all' if requesting every datatable.
     * @return Piwik_DataTable|false
     */
    public function getDataTable($name, $idSubtable = null)
    {
        $data = $this->get($name, 'blob', $idSubtable);
        $dataTable = $data->getDataTable($this->getResultIndices());
        $this->transformMetadata($dataTable); // TODO: move to DataTableFactory
        return $dataTable;
    }
    
    /**
     * Same as getDataTable() except that it will also load in memory all the subtables
     * for the DataTable $name. You can then access the subtables by using the
     * Piwik_DataTable_Manager::getTable() function.
     *
     * @param string $name The name of the record to get.
     * @param int|string|null $idSubtable The subtable ID (if any) or 'all' if requesting every datatable.
     * @return Piwik_DataTable
     */
    public function getDataTableExpanded($name, $idSubtable = null, $addMetadataSubtableId = true)
    {
        $data = $this->get($name, 'blob', 'all');
        $dataTable = $data->getExpandedDataTable($this->getResultIndices(), $idSubtable, $addMetadataSubtableId);
        $this->transformMetadata($dataTable);
        return $dataTable;
    }
    
    /**
     * Queries archive tables for data and returns the result.
     */
    private function get($archiveNames, $archiveDataType, $idSubtable = null)
    {
        $archiveTableType = 'archive_'.$archiveDataType;
        
        if (!is_array($archiveNames)) {
            $archiveNames = array($archiveNames);
        }
        
        // apply idSubtable
        if ($idSubtable !== null
            && $idSubtable != 'all'
        ) {
            foreach ($archiveNames as &$name) {
                $name .= "_$idSubtable";
            }
        }
        
        $result = new Piwik_Archive_DataCollection(
            $archiveNames, $archiveDataType, $this->siteIds, $this->periods, $defaultRow = null);
        
        // get the archive IDs
        $archiveIds = $this->getArchiveIds($archiveNames);
        if (empty($archiveIds)) {
            return $result;
        }
        
        // create the SQL to select archive data
        $inNames = Piwik_Common::getSqlStringFieldsArray($archiveNames);
        if ($idSubtable != 'all') {
            $getValuesSql = "SELECT name, value, idsite, date1, date2, ts_archived
                               FROM %s
                              WHERE idarchive IN (%s)
                                AND name IN ($inNames)";
            $bind = array_values($archiveNames);
        } else {
            // select blobs w/ name like "$name_[0-9]+" w/o using RLIKE
            $name = reset($archiveNames);
            $nameEnd = strlen($name) + 2;
            $getValuesSql = "SELECT value, name, idsite, date1, date2, ts_archived
                                FROM %s
                                WHERE idarchive IN (%s)
                                  AND (name = ? OR
                                            (name LIKE ? AND SUBSTRING(name, $nameEnd, 1) >= '0'
                                                         AND SUBSTRING(name, $nameEnd, 1) <= '9') )";
            $bind = array($name, $name.'%');
        }
        
        // get data from every table we're querying
        foreach ($archiveIds as $tableMonth => $ids) {
            $table = Piwik_Common::prefixTable($archiveTableType."_".$tableMonth);
            $sql = sprintf($getValuesSql, $table, implode(',', $ids));
            
            foreach (Piwik_FetchAll($sql, $bind) as $row) {
                // values are grouped by idsite (site ID), date1-date2 (date range), then name (field name)
                $idSite = $row['idsite'];
                $periodStr = $row['date1'].",".$row['date2'];
                
                if ($archiveTableType == 'archive_numeric') {
                    $value = (float)$row['value'];
                } else {
                    $value = $this->uncompress($row['value']);
                    $result->addKey($idSite, $periodStr, 'ts_archived', $row['ts_archived']);
                }
                
                $result->set($idSite, $periodStr, $row['name'], $value);
            }
        }
        
        return $result;
    }
    
    /**
     * Returns archive IDs for the sites, periods and archive names that are being
     * queried. This function will use the idarchive cache if it has the right data,
     * query archive tables for IDs w/o launching archiving, or launch archiving and
     * get the idarchive from Piwik_ArchiveProcessing instances.
     */
    private function getArchiveIds($archiveNames)
    {
        $requestedReports = $this->getRequestedReports($archiveNames);
        
        // figure out which archives haven't been processed
        $doneFlags = array();
        $reportsToArchive = array();
        foreach ($requestedReports as $report) {
            $doneFlag = Piwik_ArchiveProcessing::getDoneStringFlagFor(
                $this->segment, $this->getPeriodLabel(), $report);
            
            $doneFlags[$doneFlag] = true;
            if (!isset($this->idarchives[$doneFlag])) {
                $reportsToArchive[] = $report;
            }
        }
        
        // cache id archives for plugins we haven't processed yet
        if (!empty($reportsToArchive)) {
            if (!$this->isArchivingDisabled()) {
                $this->getArchiveIdsAfterLaunching($reportsToArchive); // TODO: rename this & below to cacheArchiveIds...
            } else {
                $this->getArchiveIdsWithoutLaunching($reportsToArchive);
            }
        }
        
        // order idarchives by the table month they belong to
        $idArchivesByMonth = array();
        foreach (array_keys($doneFlags) as $doneFlag) {
            if (empty($this->idarchives[$doneFlag])) {
                continue;
            }
            
            foreach ($this->idarchives[$doneFlag] as $dateRange => $idarchives) {
                $tableMonth = $this->getTableMonthFromDateRange($dateRange);
                
                foreach ($idarchives as $id) {
                    $idArchivesByMonth[$tableMonth][] = $id;
                }
            }
        }
        
        return $idArchivesByMonth;
    }
    
    /**
     * TODO
     */
    private function getArchiveIdsAfterLaunching($requestedReports)
    {
        $today = Piwik_Date::today();
        
        // for every individual query permutation, launch the archiving process and get the archive ID
        foreach ($this->getPeriodsByTableMonth() as $tableMonth => $periods) {
            foreach ($this->siteIds as $idSite) {
                $site = new Piwik_Site($idSite); // TODO: don't need to create a Site instance

                foreach ($periods as $period) {
                    $periodStr = $period->getRangeString();
                    
                    // if the END of the period is BEFORE the website creation date
                    // we already know there are no stats for this period
                    // we add one day to make sure we don't miss the day of the website creation
                    if ($period->getDateEnd()->addDay(2)->isEarlier($site->getCreationDate())) {
                        $archiveDesc = $this->getArchiveDescriptor($idSite, $period);
                        Piwik::log("Archive $archiveDesc skipped, archive is before the website was created.");
                        continue;
                    }
            
                    // if the starting date is in the future we know there is no visit
                    if ($period->getDateStart()->subDay(2)->isLater($today)) {
                        $archiveDesc = $this->getArchiveDescriptor($idSite, $period);
                        Piwik::log("Archive $archiveDesc skipped, archive is after today.");
                        continue;
                    }
                    
                    // prepare the ArchiveProcessing instance
                    $processing = $this->getArchiveProcessingInstance($period);
                    $processing->setSite($site);
                    $processing->setPeriod($period);
                    $processing->setSegment($this->segment);
                    
                    $processing->isThereSomeVisits = null;
                    
                    // process for each requested report as well
                    foreach ($requestedReports as $report) {
                        $processing->init();
                        $processing->setRequestedReport($report);
                        
                        // launch archiving if the requested data hasn't been archived
                        $idArchive = $processing->loadArchive();
                        if (empty($idArchive)) {
                            $processing->launchArchiving();
                            $idArchive = $processing->getIdArchive();
                        }
                        
                        if (!$processing->isThereSomeVisits()) {
                            continue;
                        }
                        
                        $doneFlag = Piwik_ArchiveProcessing::getDoneStringFlagFor(
                            $this->segment, $period->getLabel(), $report);
                        $this->idarchives[$doneFlag][$periodStr][] = $idArchive;
                    }
                }
            }
        }
    }
    
    /**
     * TODO
     */
    private function getArchiveProcessingInstance($period)
    {
        $label = $period->getLabel();
        if (!isset($this->processingCache[$label])) {
            $this->processingCache[$label] = Piwik_ArchiveProcessing::factory($label);
        }
        return $this->processingCache[$label];
    }
    
    /**
     * TODO
     */
    private function getArchiveIdsWithoutLaunching($requestedReports)
    {
        $periodType = $this->getPeriodLabel();
        
        $getArchiveIdsSql = "SELECT idsite, name, date1, date2, MAX(idarchive) as idarchive
                               FROM %s
                              WHERE period = ?
                                AND %s
                                AND ".$this->getNameCondition($requestedReports)."
                                AND idsite IN (".implode(',', $this->siteIds).")
                           GROUP BY idsite, date1, date2";
        
        // for every month within the archive query, select from numeric table
        foreach ($this->getPeriodsByTableMonth() as $tableMonth => $subPeriods) {
            $firstPeriod = $subPeriods[0];
            $table = Piwik_Common::prefixTable("archive_numeric_$tableMonth");
            
            // if looking for a range archive. NOTE: we assume there's only one period if its a range.
            $bind = array($firstPeriod->getId());
            if ($firstPeriod instanceof Piwik_Period_Range) {
                $dateCondition = "date1 = ? AND date2 = ?";
                $bind[] = $firstPeriod->getDateStart()->toString('Y-m-d');
                $bind[] = $firstPeriod->getDateEnd()->toString('Y-m-d');
            } else { // if looking for a normal period
                $dateStrs = array();
                foreach ($subPeriods as $period) {
                    $dateStrs[] = $period->getDateStart()->toString('Y-m-d');
                }
                
                $dateCondition = "date1 IN ('".implode("','", $dateStrs)."')";
            }
            
            $sql = sprintf($getArchiveIdsSql, $table, $dateCondition);
            
            // get the archive IDs
            $archiveIds = array();
            foreach (Piwik_FetchAll($sql, $bind) as $row) {
                $archiveIds[] = $row['idarchive'];
                
                $dateStr = $row['date1'].",".$row['date2'];
                $idSite = (int)$row['idsite'];
                
                $doneFlag = Piwik_ArchiveProcessing::getDoneStringFlagFor($this->segment, $periodType, $row['name']);
                $this->idarchives[$doneFlag][$dateStr][] = $row['idarchive'];
            }
        }
    }
    
    /**
     * TODO
     */
    private function getNameCondition($requestedReports)
    {
        // the flags used to tell how the archiving process for a specific archive was completed,
        // if it was completed
        $doneFlags = array();
        $periodType = $this->getPeriodLabel();
        foreach ($requestedReports as $name) {
            $done = Piwik_ArchiveProcessing::getDoneStringFlagFor($this->segment, $periodType, $name);
            $donePlugins = Piwik_ArchiveProcessing::getDoneStringFlagFor($this->segment, $periodType, $name, true);
            
            $doneFlags[$done] = $done;
            $doneFlags[$donePlugins] = $donePlugins;
        }

        $allDoneFlags = "'".implode("','", $doneFlags)."'";
        
        // create the SQL to find archives that are DONE
        return "(name IN ($allDoneFlags)) AND
                (value = '".Piwik_ArchiveProcessing::DONE_OK."' OR
                 value = '".Piwik_ArchiveProcessing::DONE_OK_TEMPORARY."')";
    }
    
    /**
     * TODO
     */
    private function getPeriodsByTableMonth()
    {
        $result = array();
        foreach ($this->periods as $period) {
            $tableMonth = $period->getDateStart()->toString('Y_m');
            $result[$tableMonth][] = $period;
        }
        return $result;
    }
    
    /**
     * TODO
     */
    private function getPeriodLabel()
    {
        return reset($this->periods)->getLabel();
    }
    
    /**
     * TODO
     */
    private function getTableMonthFromDateRange($dateRange)
    {
        return str_replace('-', '_', substr($dateRange, 0, 7));
    }
    
    /**
     * TODO
     */
    public function getRequestedReports($archiveNames)
    {
        $result = array();
        foreach ($archiveNames as $name) {
            $result[] = self::getRequestedReport($name);
        }
        return array_unique($result);
    }
    
    /**
     * TODO
     */
    public static function getRequestedReport($archiveName)
    {
        // Core metrics are always processed in Core, for the requested date/period/segment
        if (in_array($archiveName, Piwik_ArchiveProcessing::getCoreMetrics())
            || $archiveName == 'max_actions'
        ) {
            return 'VisitsSummary_CoreMetrics';
        }
        // VisitFrequency metrics don't follow the same naming convention (HACK) 
        else if(strpos($archiveName, '_returning') > 0
            // ignore Goal_visitor_returning_1_1_nb_conversions 
            && strpos($archiveName, 'Goal_') === false
        ) {
            return 'VisitFrequency_Metrics';
        }
        // Goal_* metrics are processed by the Goals plugin (HACK)
        else if(strpos($archiveName, 'Goal_') === 0) {
            return 'Goals_Metrics';
        } else {
            return $archiveName;
        }
    }
    
    /**
     * TODO
     */
    private function getResultIndices()
    {
        $indices = array();
        
        if (count($this->siteIds) > 1
            || $this->forceIndexedBySite
        ) {
            $indices['site'] = 'idSite';
        }
        
        if (count($this->periods) > 1
            || $this->forceIndexedByDate
        ) {
            $indices['period'] = 'date';
        }
        
        return $indices;
    }
    
    /**
     * TODO
     */
    private function transformMetadata($table)
    {
        $self = $this;
        $table->filter(function ($table) use($self) {
            $table->metadata['site'] = new Piwik_Site($table->metadata['site']);
            $table->metadata['period'] = $self->periods[$table->metadata['period']];
        });
    }
    
    /**
     * Helper - Loads a DataTable from the Archive.
     * Optionally loads the table recursively,
     * or optionally fetches a given subtable with $idSubtable
     *
     * @param string $name
     * @param int $idSite
     * @param string $period
     * @param Piwik_Date $date
     * @param string $segment
     * @param bool $expanded
     * @param null $idSubtable
     * @return Piwik_DataTable|Piwik_DataTable_Array
     */
    public static function getDataTableFromArchive($name, $idSite, $period, $date, $segment, $expanded, $idSubtable = null)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $archive = Piwik_Archive::build($idSite, $period, $date, $segment);
        if ($idSubtable === false) {
            $idSubtable = null;
        }

        if ($expanded) {
            $dataTable = $archive->getDataTableExpanded($name, $idSubtable);
        } else {
            $dataTable = $archive->getDataTable($name, $idSubtable);
        }

        $dataTable->queueFilter('ReplaceSummaryRowLabel');

        return $dataTable;
    }

    protected function formatNumericValue($value)
    {
        // If there is no dot, we return as is
        // Note: this could be an integer bigger than 32 bits
        if (strpos($value, '.') === false) {
            if ($value === false) {
                return 0;
            }
            return (float)$value;
        }

        // Round up the value with 2 decimals
        // we cast the result as float because returns false when no visitors
        $value = round((float)$value, 2);
        return $value;
    }

    /**
     * Returns true if Segmentation is allowed for this user
     *
     * @return bool
     */
    public static function isSegmentationEnabled()
    {
        return !Piwik::isUserIsAnonymous()
            || Piwik_Config::getInstance()->General['anonymous_user_enable_use_segments_API'];
    }

    /**
     * Indicate if $dateString and $period correspond to multiple periods
     *
     * @static
     * @param  $dateString
     * @param  $period
     * @return boolean
     */
    public static function isMultiplePeriod($dateString, $period)
    {
        return (preg_match('/^(last|previous){1}([0-9]*)$/D', $dateString, $regs)
            || Piwik_Period_Range::parseDateRange($dateString))
            && $period != 'range';
    }

    /**
     * Indicate if $idSiteString corresponds to multiple sites.
     *
     * @param string $idSiteString
     * @return bool
     */
    public static function isMultipleSites($idSiteString)
    {
        return $idSiteString == 'all' || strpos($idSiteString, ',') !== false;
    }
    
    /**
     * TODO
     */
    public function isArchivingDisabled()
    {
        return Piwik_ArchiveProcessing::isArchivingDisabledFor($this->segment, $this->getPeriodLabel());
    }
    
    /**
     * TODO
     */
    private function getArchiveDescriptor($idSite, $period)
    {
        return "site $idSite, {$period->getLabel()} ({$period->getPrettyString()})";
    }
    
    private function uncompress($data)
    {
        return @gzuncompress($data);
    }
    
    /**
     * TODO
     */
    private function getAsNonEmptyArray($array, $paramName)
    {
        if (!is_array($array)) {
            $array = array($array);
        }
        
        if (empty($array)) {
            throw new Exception("Piwik_Archive::__construct: \$$paramName is empty.");
        }
        
        return $array;
    }
}
