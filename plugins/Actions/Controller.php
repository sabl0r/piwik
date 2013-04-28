<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package Piwik_Actions
 */

/**
 * Actions controller
 *
 * @package Piwik_Actions
 */
class Piwik_Actions_Controller extends Piwik_Controller
{
    /**
     * PAGES
     * @param bool $fetch
     * @return string
     */

    public function indexPageUrls($fetch = false)
    {
        return Piwik_View::singleReport(
                Piwik_Translate('Actions_SubmenuPages'),
                $this->_fetchDataTable('Actions-getPageUrls'), $fetch);
    }

    /**
     * ENTRY PAGES
     * @param bool $fetch
     * @return string|void
     */
    public function indexEntryPageUrls($fetch = false)
    {
        return Piwik_View::singleReport(
                Piwik_Translate('Actions_SubmenuPagesEntry'),
                $this->_fetchDataTable('Actions-getEntryPageUrls'), $fetch);
    }

    /**
     * EXIT PAGES
     */
    public function indexExitPageUrls($fetch = false)
    {
        return Piwik_View::singleReport(
                Piwik_Translate('Actions_SubmenuPagesExit'),
                $this->_fetchDataTable('Actions-getExitPageUrls'), $fetch);
    }

    /**
     * SITE SEARCH
     */
    public function indexSiteSearch()
    {
        $view = Piwik_View::factory('indexSiteSearch');
        $view->categories = Piwik_PluginsManager::getInstance()->isPluginActivated('CustomVariables');
        echo $view->render();
    }

    /**
     * PAGE TITLES
     */
    public function indexPageTitles($fetch = false)
    {
        return Piwik_View::singleReport(
                Piwik_Translate('Actions_SubmenuPageTitles'),
                $this->_fetchDataTable('Actions-getPageTitles'), $fetch);
    }


    /**
     * DOWNLOADS
     */
    public function indexDownloads($fetch = false)
    {
        return Piwik_View::singleReport(
                Piwik_Translate('Actions_SubmenuDownloads'),
                $this->_fetchDataTable('Actions-getDownloads'), $fetch);
    }

    /**
     * OUTLINKS
     */
    public function indexOutlinks($fetch = false)
    {
        return Piwik_View::singleReport(
                Piwik_Translate('Actions_SubmenuOutlinks'),
                $this->_fetchDataTable('Actions-getOutlinks'), $fetch);
    }
}
