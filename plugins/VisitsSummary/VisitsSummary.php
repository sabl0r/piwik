<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package Piwik_VisitsSummary
 */

/**
 * Note: This plugin does not hook on Daily and Period Archiving like other Plugins because it reports the
 * very core metrics (visits, actions, visit duration, etc.) which are processed in the Core
 * Piwik_ArchiveProcessing_Day class directly.
 * These metrics can be used by other Plugins so they need to be processed up front.
 *
 * @package Piwik_VisitsSummary
 */
class Piwik_VisitsSummary extends Piwik_Plugin
{
    public function getInformation()
    {
        $info = array(
            'description'     => Piwik_Translate('VisitsSummary_PluginDescription'),
            'author'          => 'Piwik',
            'author_homepage' => 'http://piwik.org/',
            'version'         => Piwik_Version::VERSION,
        );
        return $info;
    }

    function getListHooksRegistered()
    {
        return array(
            'API.getReportMetadata' => 'getReportMetadata',
            'WidgetsList.add'       => 'addWidgets',
            'DataTableList.add'     => 'addDataTables',
            'Menu.add'              => 'addMenu',
        );
    }

    /**
     * @param Piwik_Event_Notification $notification  notification object
     */
    public function getReportMetadata($notification)
    {
        $reports = & $notification->getNotificationObject();
        $reports[] = array(
            'category'         => Piwik_Translate('VisitsSummary_VisitsSummary'),
            'name'             => Piwik_Translate('VisitsSummary_VisitsSummary'),
            'module'           => 'VisitsSummary',
            'action'           => 'get',
            'metrics'          => array(
                'nb_uniq_visitors',
                'nb_visits',
                'nb_actions',
                'nb_actions_per_visit',
                'bounce_rate',
                'avg_time_on_site' => Piwik_Translate('General_VisitDuration'),
                'max_actions'      => Piwik_Translate('General_ColumnMaxActions'),
// Used to process metrics, not displayed/used directly
//								'sum_visit_length',
//								'nb_visits_converted',
            ),
            'processedMetrics' => false,
            'order'            => 1
        );
    }

    public function addDataTables()
    {
        $documentation = Piwik_Translate('VisitsSummary_VisitsSummaryDocumentation') . '<br />'
            . Piwik_Translate('General_BrokenDownReportDocumentation') . '<br /><br />'
            . '<b>' . Piwik_Translate('General_ColumnNbVisits') . ':</b> '
            . Piwik_Translate('General_ColumnNbVisitsDocumentation') . '<br />'
            . '<b>' . Piwik_Translate('General_ColumnNbUniqVisitors') . ':</b> '
            . Piwik_Translate('General_ColumnNbUniqVisitorsDocumentation') . '<br />'
            . '<b>' . Piwik_Translate('General_ColumnNbActions') . ':</b> '
            . Piwik_Translate('General_ColumnNbActionsDocumentation') . '<br />'
            . '<b>' . Piwik_Translate('General_ColumnActionsPerVisit') . ':</b> '
            . Piwik_Translate('General_ColumnActionsPerVisitDocumentation');
    
        $columnsToDisplay = array('nb_visits');
    
        $selectableColumns = array(
            // columns from VisitsSummary.get
            'nb_visits',
            'nb_uniq_visitors',
            'avg_time_on_site',
            'bounce_rate',
            'nb_actions_per_visit',
            'max_actions',
            'nb_visits_converted',
            // columns from Actions.get
            'nb_pageviews',
            'nb_uniq_pageviews',
            'nb_downloads',
            'nb_uniq_downloads',
            'nb_outlinks',
            'nb_uniq_outlinks',
            'avg_time_generation'
        );
    
        $idSite            = Piwik_Common::getRequestVar('idSite');
        $displaySiteSearch = Piwik_Site::isSiteSearchEnabledFor($idSite);
    
        if ($displaySiteSearch) {
            $selectableColumns[] = 'nb_searches';
            $selectableColumns[] = 'nb_keywords';
        }
    
        $idSite = Piwik_Common::getRequestVar('idSite');
        $period = Piwik_Common::getRequestVar('period');
        $date   = Piwik_Common::getRequestVar('date');
        $meta   = Piwik_API_API::getInstance()->getReportMetadata($idSite, $period, $date);
    
        $columns      = array_merge($columnsToDisplay, $selectableColumns);
        $translations = array();
        foreach ($meta as $reportMeta) {
            if ($reportMeta['action'] == 'get' && !isset($reportMeta['parameters'])) {
                foreach ($columns as $column) {
                    if (isset($reportMeta['metrics'][$column])) {
                        $translations[$column] = $reportMeta['metrics'][$column];
                    }
                }
            }
        }
    
        Piwik_DataTableList::getInstance()->add('VisitsSummary-getEvolutionGraph', array(
            'apiMethod'           => 'API.get',
            'viewDataTable'       => 'graphEvolution',
            'reportDocumentation' => $documentation,
            'columnsToTranslate'  => $translations,
            'columnsToDisplay'    => implode(',', $columnsToDisplay),
            'selectableColumns'   => $selectableColumns,
        ), 'VisitsSummary_VisitsSummary', 'VisitsSummary_WidgetLastVisits');
    }
    
    public function addWidgets()
    {
        Piwik_WidgetsList::getInstance()->add('VisitsSummary_VisitsSummary', 'VisitsSummary_WidgetVisits', 'widgetVisitsSummarygetSparklines', array(
            'module' => 'VisitsSummary',
            'action' => 'getSparklines'
        ));
        Piwik_WidgetsList::getInstance()->add('VisitsSummary_VisitsSummary', 'VisitsSummary_WidgetOverviewGraph', 'widgetVisitsSummaryindex', array(
            'module' => 'VisitsSummary',
            'action' => 'index'
        ));
    }

    function addMenu()
    {
        Piwik_AddMenu('General_Visitors', '', array('module' => 'VisitsSummary', 'action' => 'index'), true, 10);
        Piwik_AddMenu('General_Visitors', 'VisitsSummary_SubmenuOverview', array('module' => 'VisitsSummary', 'action' => 'index'), true, 1);
    }
}


