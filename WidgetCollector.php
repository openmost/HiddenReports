<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\HideReports;

use Piwik\Widget\WidgetConfig;
use Piwik\Widget\WidgetContainerConfig;
use Piwik\Widget\WidgetsList;

/**
 * Widgets list handed to a single report's configureWidgets() to find out which widgets that report creates.
 */
class WidgetCollector extends WidgetsList
{
    /**
     * @var array[] Each entry: ['widget' => WidgetConfig, 'category' => ?string, 'subcategory' => ?string]
     */
    private $collected = [];

    /**
     * @var WidgetContainerConfig[]
     */
    private $containers = [];

    public function addWidgetConfig(WidgetConfig $widget)
    {
        parent::addWidgetConfig($widget);

        if ($widget instanceof WidgetContainerConfig) {
            $this->containers[] = $widget;
            return;
        }

        $this->collect($widget, null);
    }

    public function addToContainerWidget($containerId, WidgetConfig $widget)
    {
        parent::addToContainerWidget($containerId, $widget);

        if ($widget instanceof WidgetContainerConfig) {
            $this->containers[] = $widget;
            return;
        }

        $this->collect($widget, null);
    }

    /**
     * Leaf widgets created by the report, with the category and subcategory under which they show up in the menu.
     *
     * @return array[]
     */
    public function getCollectedWidgets()
    {
        $widgets = $this->collected;

        foreach ($this->containers as $container) {
            $this->collectFromContainer($container, $widgets);
        }

        $unique = [];
        foreach ($widgets as $entry) {
            $unique[$entry['widget']->getUniqueId()] = $entry;
        }

        return array_values($unique);
    }

    private function collectFromContainer(WidgetContainerConfig $container, array &$widgets)
    {
        foreach ($container->getWidgetConfigs() as $child) {
            if ($child instanceof WidgetContainerConfig) {
                $this->collectFromContainer($child, $widgets);
                continue;
            }

            $widgets[] = $this->buildEntry($child, $container);
        }
    }

    private function collect(WidgetConfig $widget, $container)
    {
        $this->collected[] = $this->buildEntry($widget, $container);
    }

    private function buildEntry(WidgetConfig $widget, $container)
    {
        $category    = $widget->getCategoryId();
        $subcategory = $widget->getSubcategoryId();

        if ($container instanceof WidgetContainerConfig && $container->getSubcategoryId()) {
            $category    = $container->getCategoryId();
            $subcategory = $container->getSubcategoryId();
        }

        return [
            'widget'      => $widget,
            'category'    => $category ?: null,
            'subcategory' => $subcategory ?: null,
        ];
    }
}
