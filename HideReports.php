<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\HideReports;

use Piwik\API\Request;

class HideReports extends \Piwik\Plugin
{
    public function registerEvents()
    {
        return [
            'API.API.getReportPagesMetadata.end' => 'filterReportPages',
            'SitesManager.deleteSite.end'         => 'onSiteDeleted',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'AssetManager.getStylesheetFiles'        => 'getStylesheetFiles',
        ];
    }

    /**
     * Removes the widgets of hidden reports from the reporting menu. Only the root request is filtered so that
     * internal consumers (for example the Custom Reports category picker) keep seeing every page.
     *
     * @param array $pages
     * @param array $info
     */
    public function filterReportPages(&$pages, $info)
    {
        if (!is_array($pages) || !Request::isCurrentApiRequestTheRootApiRequest()) {
            return;
        }

        $idSite = isset($info['parameters']['idSite']) ? (int) $info['parameters']['idSite'] : 0;
        if ($idSite <= 0) {
            return;
        }

        $store     = new HiddenReportsStore();
        $hiddenIds = $store->getEffectiveHiddenReports($idSite);
        if (empty($hiddenIds)) {
            return;
        }

        $catalog         = new ReportCatalog();
        $hiddenWidgetIds = $catalog->getWidgetUniqueIdsForItems($hiddenIds);

        $pages = PagesFilter::removeWidgets($pages, $hiddenWidgetIds);
    }

    public function onSiteDeleted($idSite)
    {
        (new HiddenReportsStore())->deleteSite((int) $idSite);
    }

    public function uninstall()
    {
        (new HiddenReportsStore())->deleteAll();
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = 'plugins/HideReports/vue/src/ManageHiddenReports/ManageHiddenReports.less';
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $keys = [
            'SiteTitle', 'GlobalTitle', 'SiteIntro', 'GlobalIntro', 'GlobalSiteSpecificNote', 'ScopeNote',
            'Search', 'NoReportsFound', 'ColumnReport', 'ColumnType', 'ColumnStatus', 'Visible', 'Hidden',
            'TypeCore', 'TypeCustomReport', 'TypeCustomDimension', 'TypeWidget', 'HiddenGlobally',
            'HiddenOfTotal', 'HideCategory', 'SavedHidden', 'SavedVisible', 'SavedCategoryHidden',
            'SavedCategoryVisible',
        ];

        foreach ($keys as $key) {
            $translationKeys[] = 'HideReports_' . $key;
        }
    }
}
