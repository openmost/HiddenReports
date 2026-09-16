<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\HideReports;

use Exception;
use Piwik\Piwik;

/**
 * Hide reports from the reporting menu, for one website or for all websites. Hidden reports stay archived and keep
 * working through the API, exports, scheduled reports and dashboards.
 *
 * @method static \Piwik\Plugins\HideReports\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    /**
     * Lists the reports of the website menu, grouped by category and subcategory, with their hidden state.
     *
     * @param int $idSite
     * @return array
     */
    public function getReports($idSite)
    {
        $idSite = (int) $idSite;
        Piwik::checkUserHasAdminAccess($idSite);

        $store      = new HiddenReportsStore();
        $siteHidden = array_fill_keys($store->getSiteHiddenReports($idSite), true);
        $global     = array_fill_keys($store->getGlobalHiddenReports(), true);

        $catalog = new ReportCatalog();

        return $this->decorate($catalog->getGroupedItems($idSite, true), function ($report) use ($siteHidden, $global) {
            $report['hiddenGlobally'] = isset($global[$report['id']]);
            $report['hidden']         = $report['hiddenGlobally'] || isset($siteHidden[$report['id']]);

            return $report;
        });
    }

    /**
     * Lists the reports that can be hidden for all websites, as they appear in the menu of the given website.
     * Reports bound to an entity of a website (goal, funnel, custom dimension, custom report) are not included.
     *
     * @param int $idSite Website used to build the list
     * @return array
     */
    public function getGlobalReports($idSite)
    {
        Piwik::checkUserHasSuperUserAccess();

        $global  = array_fill_keys((new HiddenReportsStore())->getGlobalHiddenReports(), true);
        $catalog = new ReportCatalog();

        return $this->decorate($catalog->getGroupedItems((int) $idSite, false), function ($report) use ($global) {
            $report['hiddenGlobally'] = isset($global[$report['id']]);
            $report['hidden']         = $report['hiddenGlobally'];

            return $report;
        });
    }

    /**
     * Returns the ids of the reports hidden for a website.
     *
     * @param int $idSite
     * @return array{site: string[], global: string[]}
     */
    public function getHiddenReports($idSite)
    {
        $idSite = (int) $idSite;
        Piwik::checkUserHasAdminAccess($idSite);

        $store = new HiddenReportsStore();

        return [
            'site'   => $store->getSiteHiddenReports($idSite),
            'global' => $store->getGlobalHiddenReports(),
        ];
    }

    /**
     * Hides or shows a report in the menu of one website.
     *
     * @param int $idSite
     * @param string $reportId Report id, for example "Referrers.getWebsites"
     * @param bool $hidden
     * @return bool
     */
    public function setReportHidden($idSite, $reportId, $hidden)
    {
        return $this->setReportsHidden($idSite, [$reportId], $hidden);
    }

    /**
     * Hides or shows several reports at once in the menu of one website.
     *
     * @param int $idSite
     * @param string[] $reportIds Report ids, for example ["Referrers.getWebsites", "UserCountry.getCountry"]
     * @param bool $hidden
     * @return bool
     */
    public function setReportsHidden($idSite, $reportIds, $hidden)
    {
        $idSite = (int) $idSite;
        Piwik::checkUserHasAdminAccess($idSite);

        $reportIds = $this->checkReportIds($reportIds);
        $known     = (new ReportCatalog())->getItemIds($idSite);

        foreach ($reportIds as $reportId) {
            if (!in_array($reportId, $known, true)) {
                throw new Exception(Piwik::translate('HideReports_ErrorUnknownReport'));
            }
        }

        (new HiddenReportsStore())->setSiteReportsHidden($idSite, $reportIds, $this->toBool($hidden));

        return true;
    }

    /**
     * Hides or shows a report in the menu of all websites.
     *
     * @param string $reportId Report id, for example "Referrers.getWebsites"
     * @param bool $hidden
     * @return bool
     */
    public function setReportHiddenGlobally($reportId, $hidden)
    {
        return $this->setReportsHiddenGlobally([$reportId], $hidden);
    }

    /**
     * Hides or shows several reports at once in the menu of all websites.
     *
     * @param string[] $reportIds Report ids, for example ["Referrers.getWebsites", "UserCountry.getCountry"]
     * @param bool $hidden
     * @return bool
     */
    public function setReportsHiddenGlobally($reportIds, $hidden)
    {
        Piwik::checkUserHasSuperUserAccess();

        $reportIds = $this->checkReportIds($reportIds);

        (new HiddenReportsStore())->setGlobalReportsHidden($reportIds, $this->toBool($hidden));

        return true;
    }

    private function decorate(array $groups, callable $decorator)
    {
        foreach ($groups as &$group) {
            foreach ($group['subcategories'] as &$subcategory) {
                $subcategory['reports'] = array_map($decorator, $subcategory['reports']);
            }
            unset($subcategory);
        }
        unset($group);

        return $groups;
    }

    private function checkReportId($reportId)
    {
        $reportId = trim((string) $reportId);

        if ($reportId === '' || strlen($reportId) > 255 || !preg_match('/^(widget:\S+|[A-Za-z0-9_]+\.[A-Za-z0-9_]+\S*)$/', $reportId)) {
            throw new Exception(Piwik::translate('HideReports_ErrorUnknownReport'));
        }

        return $reportId;
    }

    /**
     * @param string[]|string $reportIds
     * @return string[]
     */
    private function checkReportIds($reportIds)
    {
        if (!is_array($reportIds)) {
            $reportIds = explode(',', (string) $reportIds);
        }

        $reportIds = array_values(array_unique(array_map([$this, 'checkReportId'], $reportIds)));

        if (empty($reportIds) || count($reportIds) > 1000) {
            throw new Exception(Piwik::translate('HideReports_ErrorUnknownReport'));
        }

        return $reportIds;
    }

    private function toBool($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
