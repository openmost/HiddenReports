<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\HideReports;

use Piwik\Option;

/**
 * Persists hidden report ids, globally and per website, in the option table.
 */
class HiddenReportsStore
{
    public const OPTION_PREFIX = 'HideReports_';
    public const OPTION_GLOBAL = 'HideReports_global';

    /**
     * @return string[]
     */
    public function getGlobalHiddenReports()
    {
        return $this->read(self::OPTION_GLOBAL);
    }

    /**
     * @param int $idSite
     * @return string[]
     */
    public function getSiteHiddenReports($idSite)
    {
        return $this->read($this->siteOptionName($idSite));
    }

    /**
     * Report ids hidden for the website, either globally or for this website only.
     *
     * @param int $idSite
     * @return string[]
     */
    public function getEffectiveHiddenReports($idSite)
    {
        return array_values(array_unique(array_merge(
            $this->getGlobalHiddenReports(),
            $this->getSiteHiddenReports($idSite)
        )));
    }

    /**
     * @param string[] $reportIds
     * @param bool $hidden
     */
    public function setGlobalReportsHidden(array $reportIds, $hidden)
    {
        $this->toggle(self::OPTION_GLOBAL, $reportIds, $hidden);
    }

    /**
     * @param int $idSite
     * @param string[] $reportIds
     * @param bool $hidden
     */
    public function setSiteReportsHidden($idSite, array $reportIds, $hidden)
    {
        $this->toggle($this->siteOptionName($idSite), $reportIds, $hidden);
    }

    /**
     * @param int $idSite
     */
    public function deleteSite($idSite)
    {
        Option::delete($this->siteOptionName($idSite));
    }

    public function deleteAll()
    {
        Option::deleteLike(self::OPTION_PREFIX . '%');
    }

    private function siteOptionName($idSite)
    {
        return self::OPTION_PREFIX . 'site_' . (int) $idSite;
    }

    private function toggle($optionName, array $reportIds, $hidden)
    {
        $ids = $this->read($optionName);

        if ($hidden) {
            $ids = array_merge($ids, $reportIds);
        } else {
            $ids = array_diff($ids, $reportIds);
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        if (empty($ids)) {
            Option::delete($optionName);
        } else {
            Option::set($optionName, json_encode($ids));
        }
    }

    /**
     * @param string $optionName
     * @return string[]
     */
    private function read($optionName)
    {
        $value = Option::get($optionName);
        if (empty($value)) {
            return [];
        }

        $ids = json_decode($value, true);
        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_filter($ids, 'is_string'));
    }
}
