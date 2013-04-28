<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package Piwik_UserCountry
 */

/**
 * @see plugins/UserCountry/GeoIPAutoUpdater.php
 */
require_once PIWIK_INCLUDE_PATH . '/plugins/UserCountry/GeoIPAutoUpdater.php';

/**
 *
 * @package Piwik_UserCountry
 */
class Piwik_UserCountry extends Piwik_Plugin
{
    const VISITS_BY_COUNTRY_RECORD_NAME = 'UserCountry_country';
    const VISITS_BY_REGION_RECORD_NAME = 'UserCountry_region';
    const VISITS_BY_CITY_RECORD_NAME = 'UserCountry_city';

    const DISTINCT_COUNTRIES_METRIC = 'UserCountry_distinctCountries';

    // separate region, city & country info in stored report labels
    const LOCATION_SEPARATOR = '|';

    public function getInformation()
    {
        $info = array(
            'description'     => Piwik_Translate('UserCountry_PluginDescription'),
            'author'          => 'Piwik',
            'author_homepage' => 'http://piwik.org/',
            'version'         => Piwik_Version::VERSION,
            'TrackerPlugin'   => true,
        );
        return $info;
    }

    function getListHooksRegistered()
    {
        $hooks = array(
            'ArchiveProcessing_Day.compute'    => 'archiveDay',
            'ArchiveProcessing_Period.compute' => 'archivePeriod',
            'DataTableList.add'                => 'addDataTables',
            'Menu.add'                         => 'addMenu',
            'AdminMenu.add'                    => 'addAdminMenu',
            'Goals.getReportsWithGoalMetrics'  => 'getReportsWithGoalMetrics',
            'API.getReportMetadata'            => 'getReportMetadata',
            'API.getSegmentsMetadata'          => 'getSegmentsMetadata',
            'AssetManager.getCssFiles'         => 'getCssFiles',
            'AssetManager.getJsFiles'          => 'getJsFiles',
            'Tracker.getVisitorLocation'       => 'getVisitorLocation',
            'TaskScheduler.getScheduledTasks'  => 'getScheduledTasks',
        );
        return $hooks;
    }

    /**
     * @param Piwik_Event_Notification $notification  notification object
     */
    function getScheduledTasks($notification)
    {
        $tasks = & $notification->getNotificationObject();

        // add the auto updater task
        $tasks[] = Piwik_UserCountry_GeoIPAutoUpdater::makeScheduledTask();
    }

    /**
     * @param Piwik_Event_Notification $notification  notification object
     */
    function getCssFiles($notification)
    {
        $cssFiles = & $notification->getNotificationObject();

        $cssFiles[] = "plugins/UserCountry/templates/styles.css";
    }

    /**
     * @param Piwik_Event_Notification $notification  notification object
     */
    public function getJsFiles($notification)
    {
        $jsFiles = & $notification->getNotificationObject();

        $jsFiles[] = "plugins/UserCountry/templates/admin.js";
    }

    /**
     * @param Piwik_Event_Notification $notification  notification object
     */
    public function getVisitorLocation($notification)
    {
        require_once PIWIK_INCLUDE_PATH . "/plugins/UserCountry/LocationProvider.php";
        $location = & $notification->getNotificationObject();
        $visitorInfo = $notification->getNotificationInfo();

        $id = Piwik_Common::getCurrentLocationProviderId();
        $provider = Piwik_UserCountry_LocationProvider::getProviderById($id);
        if ($provider === false) {
            $id = Piwik_UserCountry_LocationProvider_Default::ID;
            $provider = Piwik_UserCountry_LocationProvider::getProviderById($id);
            printDebug("GEO: no current location provider sent, falling back to default '$id' one.");
        }

        $location = $provider->getLocation($visitorInfo);

        // if we can't find a location, use default provider
        if ($location === false) {
            $defaultId = Piwik_UserCountry_LocationProvider_Default::ID;
            $provider = Piwik_UserCountry_LocationProvider::getProviderById($defaultId);
            $location = $provider->getLocation($visitorInfo);
            printDebug("GEO: couldn't find a location with Geo Module '$id', using Default '$defaultId' provider as fallback...");
            $id = $defaultId;
        }
        printDebug("GEO: Found IP location (provider '" . $id . "'): " . var_export($location, true));
    }

    public function addDataTables()
    {
        $widgetContinentLabel = Piwik_Translate('UserCountry_WidgetLocation')
                              . ' ('.Piwik_Translate('UserCountry_Continent').')';
        $widgetCountryLabel = Piwik_Translate('UserCountry_WidgetLocation')
                            . ' ('.Piwik_Translate('UserCountry_Country').')';
        $widgetRegionLabel = Piwik_Translate('UserCountry_WidgetLocation')
                           . ' ('.Piwik_Translate('UserCountry_Region').')';
        $widgetCityLabel = Piwik_Translate('UserCountry_WidgetLocation')
                         . ' ('.Piwik_Translate('UserCountry_City').')';
   
        Piwik_DataTableList::getInstance()->add('UserCountry-getContinent', array(
            'apiMethod'                   => 'UserCountry.getContinent',
            'viewDataTable'               => 'graphVerticalBar',
            'disableSearch'               => true,
            'disableOffsetInformation'    => true,
            'disablePaginationControl'    => true,
            'disableExcludeLowPopulation' => true,
            'showGoals'                   => true,
            'columnsToTranslate'          => array('label' => 'UserCountry_Continent'),
            'reportDocumentation'         => 'UserCountry_getContinentDocumentation',
        ), 'General_Visitors', $widgetContinentLabel);
   
        Piwik_DataTableList::getInstance()->add('UserCountry-getCountry', array(
            'apiMethod'                   => 'UserCountry.getCountry',
            'disableExcludeLowPopulation' => true,
            'showGoals'                   => true,
            'limit'                       => 5,
            'columnsToTranslate'          => array('label' => 'UserCountry_Country'),
            'reportDocumentation'         => 'UserCountry_getCountryDocumentation',
        ), 'General_Visitors', $widgetCountryLabel);
   
        Piwik_DataTableList::getInstance()->add('UserCountry-getRegion', array(
            'apiMethod'                   => 'UserCountry.getRegion',
            'disableExcludeLowPopulation' => true,
            'showGoals'                   => true,
            'limit'                       => 5,
            'columnsToTranslate'          => array('label' => 'UserCountry_Region'),
            'reportDocumentation'         => Piwik_Translate('UserCountry_getRegionDocumentation') . '<br/>' . $this->getGeoIPReportDocSuffix(),
            'customFilter'                => array('Piwik_UserCountry', 'appendFooterMessageIfNoGeoIpData')
        ), 'General_Visitors', $widgetRegionLabel);
   
        Piwik_DataTableList::getInstance()->add('UserCountry-getCity', array(
            'apiMethod'                   => 'UserCountry.getCity',
            'disableExcludeLowPopulation' => true,
            'showGoals'                   => true,
            'limit'                       => 5,
            'columnsToTranslate'          => array('label' => 'UserCountry_City'),
            'reportDocumentation'         => Piwik_Translate('UserCountry_getCityDocumentation') . '<br/>' . $this->getGeoIPReportDocSuffix(),
            'customFilter'                => array('Piwik_UserCountry', 'appendFooterMessageIfNoGeoIpData')
        ), 'General_Visitors', $widgetCityLabel);
   
        // not widget datatables
        Piwik_DataTableList::getInstance()->add('UserCountry-getNumberOfDistinctCountries', array(
            'apiMethod'          => 'UserCountry.getNumberOfDistinctCountries',
            'viewDataTable'      => 'graphEvolution',
            'columnsToTranslate' => array('Referers_distinctSearchEngines' => 'Referers_DistinctSearchEngines'),
            'columnsToDisplay'   => 'UserCountry_distinctCountries',
        ));
    }

    private function getGeoIPReportDocSuffix()
    {
        return Piwik_Translate('UserCountry_GeoIPDocumentationSuffix', array(
            '<a target="_blank" href="http://www.maxmind.com/?rId=piwik">',
            '</a>',
            '<a target="_blank" href="http://www.maxmind.com/en/city_accuracy?rId=piwik">',
            '</a>'
        ));
    }
    
    function addMenu()
    {
        Piwik_AddMenu('General_Visitors', 'UserCountry_SubmenuLocations', array('module' => 'UserCountry', 'action' => 'index'));
    }

    /**
     * Event handler. Adds menu items to the Admin menu.
     */
    function addAdminMenu()
    {
        Piwik_AddAdminSubMenu('General_Settings', 'UserCountry_Geolocation',
            array('module' => 'UserCountry', 'action' => 'adminIndex'),
            Piwik::isUserIsSuperUser(),
            $order = 8);
    }

    /**
     * @param Piwik_Event_Notification $notification  notification object
     */
    public function getSegmentsMetadata($notification)
    {
        $segments =& $notification->getNotificationObject();
        $segments[] = array(
            'type'           => 'dimension',
            'category'       => 'Visit Location',
            'name'           => Piwik_Translate('UserCountry_Country'),
            'segment'        => 'countryCode',
            'sqlSegment'     => 'log_visit.location_country',
            'acceptedValues' => 'de, us, fr, in, es, etc.',
        );
        $segments[] = array(
            'type'           => 'dimension',
            'category'       => 'Visit Location',
            'name'           => Piwik_Translate('UserCountry_Continent'),
            'segment'        => 'continentCode',
            'sqlSegment'     => 'log_visit.location_country',
            'acceptedValues' => 'eur, asi, amc, amn, ams, afr, ant, oce',
            'sqlFilter'      => array('Piwik_UserCountry', 'getCountriesForContinent'),
        );
        $segments[] = array(
            'type'           => 'dimension',
            'category'       => 'Visit Location',
            'name'           => Piwik_Translate('UserCountry_Region'),
            'segment'        => 'regionCode',
            'sqlSegment'     => 'log_visit.location_region',
            'acceptedValues' => '01 02, OR, P8, etc.<br/>eg. region=A1;country=fr',
        );
        $segments[] = array(
            'type'           => 'dimension',
            'category'       => 'Visit Location',
            'name'           => Piwik_Translate('UserCountry_City'),
            'segment'        => 'city',
            'sqlSegment'     => 'log_visit.location_city',
            'acceptedValues' => 'Sydney, Sao Paolo, Rome, etc.',
        );
        $segments[] = array(
            'type'           => 'dimension',
            'category'       => 'Visit Location',
            'name'           => Piwik_Translate('UserCountry_Latitude'),
            'segment'        => 'latitude',
            'sqlSegment'     => 'log_visit.location_latitude',
            'acceptedValues' => '-33.578, 40.830, etc.<br/>You can select visitors within a lat/long range using &segment=lat&gt;X;lat&lt;Y;long&gt;M;long&lt;N.',
        );
        $segments[] = array(
            'type'           => 'dimension',
            'category'       => 'Visit Location',
            'name'           => Piwik_Translate('UserCountry_Longitude'),
            'segment'        => 'longitude',
            'sqlSegment'     => 'log_visit.location_longitude',
            'acceptedValues' => '-70.664, 14.326, etc.',
        );
    }

    /**
     * @param Piwik_Event_Notification $notification  notification object
     */
    public function getReportMetadata($notification)
    {
        $metrics = array(
            'nb_visits'        => Piwik_Translate('General_ColumnNbVisits'),
            'nb_uniq_visitors' => Piwik_Translate('General_ColumnNbUniqVisitors'),
            'nb_actions'       => Piwik_Translate('General_ColumnNbActions'),
        );

        $reports = & $notification->getNotificationObject();

        $reports[] = array(
            'category'  => Piwik_Translate('General_Visitors'),
            'name'      => Piwik_Translate('UserCountry_Country'),
            'module'    => 'UserCountry',
            'action'    => 'getCountry',
            'dimension' => Piwik_Translate('UserCountry_Country'),
            'metrics'   => $metrics,
            'order'     => 5,
        );

        $reports[] = array(
            'category'  => Piwik_Translate('General_Visitors'),
            'name'      => Piwik_Translate('UserCountry_Continent'),
            'module'    => 'UserCountry',
            'action'    => 'getContinent',
            'dimension' => Piwik_Translate('UserCountry_Continent'),
            'metrics'   => $metrics,
            'order'     => 6,
        );

        $reports[] = array(
            'category'  => Piwik_Translate('General_Visitors'),
            'name'      => Piwik_Translate('UserCountry_Region'),
            'module'    => 'UserCountry',
            'action'    => 'getRegion',
            'dimension' => Piwik_Translate('UserCountry_Region'),
            'metrics'   => $metrics,
            'order'     => 7,
        );

        $reports[] = array(
            'category'  => Piwik_Translate('General_Visitors'),
            'name'      => Piwik_Translate('UserCountry_City'),
            'module'    => 'UserCountry',
            'action'    => 'getCity',
            'dimension' => Piwik_Translate('UserCountry_City'),
            'metrics'   => $metrics,
            'order'     => 8,
        );
    }

    /**
     * @param Piwik_Event_Notification $notification  notification object
     */
    function getReportsWithGoalMetrics($notification)
    {
        $dimensions =& $notification->getNotificationObject();
        $dimensions = array_merge($dimensions, array(
                                                    array('category' => Piwik_Translate('General_Visit'),
                                                          'name'     => Piwik_Translate('UserCountry_Country'),
                                                          'module'   => 'UserCountry',
                                                          'action'   => 'getCountry',
                                                    ),
                                                    array('category' => Piwik_Translate('General_Visit'),
                                                          'name'     => Piwik_Translate('UserCountry_Continent'),
                                                          'module'   => 'UserCountry',
                                                          'action'   => 'getContinent',
                                                    ),
                                                    array('category' => Piwik_Translate('General_Visit'),
                                                          'name'     => Piwik_Translate('UserCountry_Region'),
                                                          'module'   => 'UserCountry',
                                                          'action'   => 'getRegion'),
                                                    array('category' => Piwik_Translate('General_Visit'),
                                                          'name'     => Piwik_Translate('UserCountry_City'),
                                                          'module'   => 'UserCountry',
                                                          'action'   => 'getCity'),
                                               ));
    }

    /**
     * @param Piwik_Event_Notification $notification  notification object
     * @return mixed
     */
    function archivePeriod($notification)
    {
        /**
         * @param Piwik_ArchiveProcessing_Period $archiveProcessing
         */
        $archiveProcessing = $notification->getNotificationObject();

        if (!$archiveProcessing->shouldProcessReportsForPlugin($this->getPluginName())) return;

        $dataTableToSum = array(
            self::VISITS_BY_COUNTRY_RECORD_NAME,
            self::VISITS_BY_REGION_RECORD_NAME,
            self::VISITS_BY_CITY_RECORD_NAME,
        );

        $nameToCount = $archiveProcessing->archiveDataTable($dataTableToSum);
        $archiveProcessing->insertNumericRecord(self::DISTINCT_COUNTRIES_METRIC,
            $nameToCount[self::VISITS_BY_COUNTRY_RECORD_NAME]['level0']);
    }

    private $interestTables = null;
    private $latLongForCities = null;

    /**
     * @param Piwik_Event_Notification $notification  notification object
     * @return mixed
     */
    function archiveDay($notification)
    {
        /**
         * @var Piwik_ArchiveProcessing
         */
        $archiveProcessing = $notification->getNotificationObject();

        if (!$archiveProcessing->shouldProcessReportsForPlugin($this->getPluginName())) return;

        $this->interestTables = array('location_country' => array(),
                                      'location_region'  => array(),
                                      'location_city'    => array());
        $this->latLongForCities = array();

        $this->archiveDayAggregateVisits($archiveProcessing);
        $this->archiveDayAggregateGoals($archiveProcessing);
        $this->archiveDayRecordInDatabase($archiveProcessing);

        unset($this->interestTables);
        unset($this->latLongForCities);
    }

    /**
     * @param Piwik_ArchiveProcessing_Day $archiveProcessing
     */
    protected function archiveDayAggregateVisits($archiveProcessing)
    {
        $dimensions = array_keys($this->interestTables);
        $query = $archiveProcessing->queryVisitsByDimension(
            $dimensions,
            $where = '',
            $metrics = false,
            $orderBy = false,
            $rankingQuery = null,
            $addSelect = 'MAX(log_visit.location_latitude) as location_latitude,
						  MAX(log_visit.location_longitude) as location_longitude'
        );

        if ($query === false) {
            return;
        }

        while ($row = $query->fetch()) {
            // get latitude/longitude if there's a city
            $lat = $long = false;
            if (!empty($row['location_city'])) {
                if (!empty($row['location_latitude'])) {
                    $lat = $row['location_latitude'];
                }
                if (!empty($row['location_longitude'])) {
                    $long = $row['location_longitude'];
                }
            }

            // make sure regions & cities w/ the same name don't get merged
            $this->setLongCityRegionId($row);

            // store latitude/longitude, if we should
            if ($lat !== false && $long !== false) {
                $this->latLongForCities[$row['location_city']] = array($lat, $long);
            }

            // add the stats to each dimension's table
            foreach ($this->interestTables as $dimension => &$table) {
                $label = (string)$row[$dimension];

                if (!isset($table[$label])) {
                    $table[$label] = $archiveProcessing->getNewInterestRow();
                }
                $archiveProcessing->updateInterestStats($row, $table[$label]);
            }
        }
    }

    /**
     * @param Piwik_ArchiveProcessing_Day $archiveProcessing
     */
    protected function archiveDayAggregateGoals($archiveProcessing)
    {
        $dimensions = array_keys($this->interestTables);
        $query = $archiveProcessing->queryConversionsByDimension($dimensions);

        if ($query === false) {
            return;
        }

        while ($row = $query->fetch()) {
            // make sure regions & cities w/ the same name don't get merged
            $this->setLongCityRegionId($row);

            $idGoal = $row['idgoal'];
            foreach ($this->interestTables as $dimension => &$table) {
                $label = (string)$row[$dimension];

                if (!isset($table[$label][Piwik_Archive::INDEX_GOALS][$idGoal])) {
                    $table[$label][Piwik_Archive::INDEX_GOALS][$idGoal] = $archiveProcessing->getNewGoalRow($idGoal);
                }

                $archiveProcessing->updateGoalStats($row, $table[$label][Piwik_Archive::INDEX_GOALS][$idGoal]);
            }
        }

        foreach ($this->interestTables as &$table) {
            $archiveProcessing->enrichConversionsByLabelArray($table);
        }
    }

    /**
     * @param Piwik_ArchiveProcessing_Day $archiveProcessing
     */
    protected function archiveDayRecordInDatabase($archiveProcessing)
    {
        $maximumRows = Piwik_Config::getInstance()->General['datatable_archiving_maximum_rows_standard'];

        $tableCountry = Piwik_ArchiveProcessing_Day::getDataTableFromArray($this->interestTables['location_country']);
        $archiveProcessing->insertBlobRecord(self::VISITS_BY_COUNTRY_RECORD_NAME, $tableCountry->getSerialized());
        $archiveProcessing->insertNumericRecord(self::DISTINCT_COUNTRIES_METRIC, $tableCountry->getRowsCount());
        destroy($tableCountry);

        $tableRegion = Piwik_ArchiveProcessing_Day::getDataTableFromArray($this->interestTables['location_region']);
        $serialized = $tableRegion->getSerialized($maximumRows, $maximumRows, Piwik_Archive::INDEX_NB_VISITS);
        $archiveProcessing->insertBlobRecord(self::VISITS_BY_REGION_RECORD_NAME, $serialized);
        destroy($tableRegion);

        $tableCity = Piwik_ArchiveProcessing_Day::getDataTableFromArray($this->interestTables['location_city']);
        $this->setLatitudeLongitude($tableCity);
        $serialized = $tableCity->getSerialized($maximumRows, $maximumRows, Piwik_Archive::INDEX_NB_VISITS);
        $archiveProcessing->insertBlobRecord(self::VISITS_BY_CITY_RECORD_NAME, $serialized);
        destroy($tableCity);
    }

    /**
     * Makes sure the region and city of a query row are unique.
     *
     * @param array $row
     */
    private function setLongCityRegionId(&$row)
    {
        static $locationColumns = array('location_region', 'location_country', 'location_city');

        // to be on the safe side, remove the location separator from the region/city/country we
        // get from the query
        foreach ($locationColumns as $column) {
            $row[$column] = str_replace(self::LOCATION_SEPARATOR, '', $row[$column]);
        }

        if (!empty($row['location_region'])) // do not differentiate between unknown regions
        {
            $row['location_region'] = $row['location_region'] . self::LOCATION_SEPARATOR . $row['location_country'];
        }

        if (!empty($row['location_city'])) // do not differentiate between unknown cities
        {
            $row['location_city'] = $row['location_city'] . self::LOCATION_SEPARATOR . $row['location_region'];
        }
    }

    /**
     * Returns a list of country codes for a given continent code.
     *
     * @param string $continent The continent code.
     * @return array
     */
    public static function getCountriesForContinent($continent)
    {
        $result = array();
        $continent = strtolower($continent);
        foreach (Piwik_Common::getCountriesList() as $countryCode => $continentCode) {
            if ($continent == $continentCode) {
                $result[] = $countryCode;
            }
        }
        return array('SQL'  => "'" . implode("', '", $result) . "', ?",
                     'bind' => '-'); // HACK: SegmentExpression requires a $bind, even if there's nothing to bind
    }

    /**
     * Utility method, appends latitude/longitude pairs to city table labels, if that data
     * exists for the city.
     */
    private function setLatitudeLongitude($tableCity)
    {
        foreach ($tableCity->getRows() as $row) {
            $label = $row->getColumn('label');
            if (isset($this->latLongForCities[$label])) {
                // get lat/long for city
                list($lat, $long) = $this->latLongForCities[$label];
                $lat = round($lat, Piwik_UserCountry_LocationProvider::GEOGRAPHIC_COORD_PRECISION);
                $long = round($long, Piwik_UserCountry_LocationProvider::GEOGRAPHIC_COORD_PRECISION);

                // set latitude + longitude metadata
                $row->setMetadata('lat', $lat);
                $row->setMetadata('long', $long);
            }
        }
    }
    
    
    /**
     * Custom datatable filter used for rendering some datatables
     * Adds a footer message to the datatable if no data is available
     *
     * @static
     *
     * @param Piwik_ViewDataTable $view
     */
    public static function appendFooterMessageIfNoGeoIpData($view)
    {
        // only display on HTML tables since the datatable for HTML graphs aren't accessible
        if (!($view instanceof Piwik_ViewDataTable_HtmlTable)) {
            return;
        }
   
        // if there's only one row whose label is 'Unknown', display a message saying there's no data
        $view->main();
        $dataTable = $view->getDataTable();
        if ($dataTable->getRowsCount() == 1
            && $dataTable->getFirstRow()->getColumn('label') == Piwik_Translate('General_Unknown')
        ) {
            $footerMessage = Piwik_Translate('UserCountry_NoDataForGeoIPReport1');
   
            // if GeoIP is working, don't display this part of the message
            if (!self::_isGeoIPWorking()) {
                $params = array('module' => 'UserCountry', 'action' => 'adminIndex');
                $footerMessage .= ' ' . Piwik_Translate('UserCountry_NoDataForGeoIPReport2', array(
                    '<a target="_blank" href="' . Piwik_Url::getCurrentQueryStringWithParametersModified($params) . '">',
                    '</a>',
                    '<a target="_blank" href="http://dev.maxmind.com/geoip/geolite?rId=piwik">',
                    '</a>'
                ));
            } else {
                $footerMessage .= ' ' . Piwik_Translate('UserCountry_ToGeolocateOldVisits', array(
                    '<a target="_blank" href="http://piwik.org/faq/how-to/#faq_167">',
                    '</a>'
                ));
            }
   
            // HACK! Can't use setFooterMessage because the view gets built in the main function,
            // so instead we set the property by hand.
            $realView                          = $view->getView();
            $properties                        = $realView->properties;
            $properties['show_footer_message'] = $footerMessage;
            $realView->properties              = $properties;
        }
    }
   
    /**
     * Returns true if a GeoIP provider is installed & working, false if otherwise.
     *
     * @return bool
     */
    private static function _isGeoIPWorking()
    {
        $provider = Piwik_UserCountry_LocationProvider::getCurrentProvider();
        return $provider instanceof Piwik_UserCountry_LocationProvider_GeoIp
            && $provider->isAvailable() === true
            && $provider->isWorking() === true;
    }
}
