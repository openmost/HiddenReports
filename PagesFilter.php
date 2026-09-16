<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\HiddenReports;

/**
 * Removes widgets from the output of API.getReportPagesMetadata.
 */
class PagesFilter
{
    /**
     * @param array $pages Output of API.getReportPagesMetadata
     * @param string[] $widgetUniqueIds Widgets to remove
     * @return array Pages without those widgets. Pages and containers left empty are removed too.
     */
    public static function removeWidgets(array $pages, array $widgetUniqueIds)
    {
        if (empty($widgetUniqueIds)) {
            return $pages;
        }

        $lookup = array_fill_keys($widgetUniqueIds, true);
        $result = [];

        foreach ($pages as $page) {
            if (empty($page['widgets']) || !is_array($page['widgets'])) {
                $result[] = $page;
                continue;
            }

            $page['widgets'] = self::filterWidgets($page['widgets'], $lookup);

            if (!empty($page['widgets'])) {
                $result[] = $page;
            }
        }

        return $result;
    }

    private static function filterWidgets(array $widgets, array $lookup)
    {
        $result = [];

        foreach ($widgets as $widget) {
            if (!empty($widget['isContainer']) && !empty($widget['widgets']) && is_array($widget['widgets'])) {
                $widget['widgets'] = self::filterWidgets($widget['widgets'], $lookup);

                if (!empty($widget['widgets'])) {
                    $result[] = $widget;
                }
                continue;
            }

            if (isset($widget['uniqueId']) && isset($lookup[$widget['uniqueId']])) {
                continue;
            }

            $result[] = $widget;
        }

        return $result;
    }
}
