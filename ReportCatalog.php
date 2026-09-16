<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\HideReports;

use Piwik\API\Request;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Report\ReportWidgetFactory;

/**
 * Lists what can be hidden from the reporting menu of the current website.
 *
 * An item is either a report, identified by Report::getId() and covering every widget the report creates, or a
 * widget that does not belong to any report (Transitions, real-time map, manage pages...), identified by
 * "widget:" followed by its unique id.
 *
 * Items are resolved for the idSite of the current request, like the core does for the menu.
 */
class ReportCatalog
{
    public const TYPE_CORE             = 'core';
    public const TYPE_CUSTOM_REPORT    = 'customReport';
    public const TYPE_CUSTOM_DIMENSION = 'customDimension';
    public const TYPE_WIDGET           = 'widget';

    public const WIDGET_PREFIX = 'widget:';

    /**
     * @var array|null
     */
    private $reports;

    /**
     * Reports that create at least one widget.
     *
     * @return array[] Each entry: id, name, type, siteSpecific, widgetIds
     */
    public function getReports()
    {
        if ($this->reports !== null) {
            return $this->reports;
        }

        $this->reports = [];

        /** @var Report[] $reports */
        $reports = StaticContainer::get('Piwik\Plugin\ReportsProvider')->getAllReports();

        foreach ($reports as $report) {
            if (!$report->isEnabled()) {
                continue;
            }

            $collector = new WidgetCollector();
            $report->configureWidgets($collector, new ReportWidgetFactory($report));
            $widgets = $collector->getCollectedWidgets();

            if (empty($widgets)) {
                continue;
            }

            $name      = Piwik::translate((string) $report->getName());
            $widgetIds = [];

            foreach ($widgets as $entry) {
                $widgetIds[] = $entry['widget']->getUniqueId();

                if ($name === '') {
                    $name = Piwik::translate((string) $entry['widget']->getName());
                }
            }

            $this->reports[] = [
                'id'           => $report->getId(),
                'name'         => $name,
                'type'         => $this->getType($report),
                'siteSpecific' => $this->isSiteSpecificReport($report),
                'widgetIds'    => $widgetIds,
            ];
        }

        return $this->reports;
    }

    /**
     * Widget unique ids to remove from the menu for the given hidden item ids.
     *
     * @param string[] $itemIds
     * @return string[]
     */
    public function getWidgetUniqueIdsForItems(array $itemIds)
    {
        $widgetIds   = [];
        $reportIds   = [];

        foreach ($itemIds as $itemId) {
            if (strpos($itemId, self::WIDGET_PREFIX) === 0) {
                $widgetIds[] = substr($itemId, strlen(self::WIDGET_PREFIX));
            } else {
                $reportIds[$itemId] = true;
            }
        }

        if (!empty($reportIds)) {
            foreach ($this->getReports() as $report) {
                if (isset($reportIds[$report['id']])) {
                    $widgetIds = array_merge($widgetIds, $report['widgetIds']);
                }
            }
        }

        return array_values(array_unique($widgetIds));
    }

    /**
     * Ids of every item of the menu.
     *
     * @param int $idSite
     * @return string[]
     */
    public function getItemIds($idSite)
    {
        $ids = [];

        foreach ($this->getGroupedItems($idSite, true) as $category) {
            foreach ($category['subcategories'] as $subcategory) {
                foreach ($subcategory['reports'] as $item) {
                    $ids[] = $item['id'];
                }
            }
        }

        return $ids;
    }

    /**
     * Items of the reporting menu grouped by category and subcategory, in menu order.
     *
     * @param int $idSite
     * @param bool $includeSiteSpecific Whether to keep items bound to an entity of the website (goal, funnel,
     *                                  custom dimension, custom report...)
     * @return array[]
     */
    public function getGroupedItems($idSite, $includeSiteSpecific)
    {
        $reportByWidget = [];
        foreach ($this->getReports() as $report) {
            foreach ($report['widgetIds'] as $widgetId) {
                $reportByWidget[$widgetId] = $report;
            }
        }

        // nested request: the menu filter only applies to root requests, so we get the complete menu
        $pages = Request::processRequest('API.getReportPagesMetadata', [
            'idSite'       => (int) $idSite,
            'filter_limit' => -1,
        ]);

        $groups = [];
        $seen   = [];

        foreach ($pages as $page) {
            if (empty($page['category']['id']) || empty($page['subcategory']['id'])) {
                continue;
            }

            $categoryId    = (string) $page['category']['id'];
            $subcategoryId = (string) $page['subcategory']['id'];

            if ($categoryId === 'Dashboard_Dashboard') {
                continue;
            }

            $items = [];
            foreach ($this->getLeafWidgets($page['widgets'] ?? []) as $widget) {
                $uniqueId = $widget['uniqueId'] ?? '';

                if (isset($reportByWidget[$uniqueId])) {
                    $report = $reportByWidget[$uniqueId];
                    $item   = [
                        'id'           => $report['id'],
                        'name'         => $report['name'],
                        'type'         => $report['type'],
                        'siteSpecific' => $report['siteSpecific'],
                    ];
                } elseif ($uniqueId !== '') {
                    $item = [
                        'id'           => self::WIDGET_PREFIX . $uniqueId,
                        'name'         => (string) ($widget['name'] ?? ''),
                        'type'         => self::TYPE_WIDGET,
                        'siteSpecific' => $this->hasNumericParameter($widget['parameters'] ?? []),
                    ];
                } else {
                    continue;
                }

                if (isset($seen[$item['id']]) || (!$includeSiteSpecific && $item['siteSpecific'])) {
                    continue;
                }
                $seen[$item['id']] = true;

                if ($item['name'] === '') {
                    $item['name'] = (string) $page['subcategory']['name'];
                }

                unset($item['siteSpecific']);
                $items[] = $item;
            }

            if (empty($items)) {
                continue;
            }

            if (!isset($groups[$categoryId])) {
                $groups[$categoryId] = [
                    'id'            => $categoryId,
                    'name'          => (string) $page['category']['name'],
                    'order'         => (float) ($page['category']['order'] ?? 999),
                    'subcategories' => [],
                ];
            }

            if (!isset($groups[$categoryId]['subcategories'][$subcategoryId])) {
                $groups[$categoryId]['subcategories'][$subcategoryId] = [
                    'id'      => $subcategoryId,
                    'name'    => (string) $page['subcategory']['name'],
                    'order'   => (float) ($page['subcategory']['order'] ?? 999),
                    'reports' => [],
                ];
            }

            $groups[$categoryId]['subcategories'][$subcategoryId]['reports'] = array_merge(
                $groups[$categoryId]['subcategories'][$subcategoryId]['reports'],
                $items
            );
        }

        $byOrder = function ($a, $b) {
            if ($a['order'] == $b['order']) {
                return strcmp($a['name'], $b['name']);
            }

            return $a['order'] < $b['order'] ? -1 : 1;
        };

        foreach ($groups as &$group) {
            $group['subcategories'] = array_values($group['subcategories']);
            usort($group['subcategories'], $byOrder);
        }
        unset($group);

        $groups = array_values($groups);
        usort($groups, $byOrder);

        return $groups;
    }

    private function getLeafWidgets(array $widgets)
    {
        $leaves = [];

        foreach ($widgets as $widget) {
            if (!empty($widget['isContainer'])) {
                $leaves = array_merge($leaves, $this->getLeafWidgets($widget['widgets'] ?? []));
            } else {
                $leaves[] = $widget;
            }
        }

        return $leaves;
    }

    private function getType(Report $report)
    {
        $class = get_class($report);

        if (strpos($class, 'Piwik\\Plugins\\CustomReports\\') === 0) {
            return self::TYPE_CUSTOM_REPORT;
        }

        if (strpos($class, 'Piwik\\Plugins\\CustomDimensions\\') === 0) {
            return self::TYPE_CUSTOM_DIMENSION;
        }

        return self::TYPE_CORE;
    }

    /**
     * A report whose parameters point to a numeric id (goal, custom dimension, custom report...) only means
     * something for one website, so it cannot be hidden for all websites at once.
     */
    private function isSiteSpecificReport(Report $report)
    {
        return $this->getType($report) !== self::TYPE_CORE || $this->hasNumericParameter($report->getParameters());
    }

    private function hasNumericParameter($parameters)
    {
        foreach ((array) $parameters as $key => $value) {
            if ($key !== 'module' && $key !== 'action' && is_numeric($value)) {
                return true;
            }
        }

        return false;
    }
}
