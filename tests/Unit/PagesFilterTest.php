<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\HiddenReports\tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\HiddenReports\PagesFilter;

/**
 * @group HiddenReports
 * @group PagesFilterTest
 * @group Plugins
 */
class PagesFilterTest extends TestCase
{
    public function testReturnsPagesUnchangedWhenNothingIsHidden()
    {
        $pages = [$this->page('a', [$this->widget('w1')])];

        $this->assertSame($pages, PagesFilter::removeWidgets($pages, []));
    }

    public function testRemovesHiddenWidgetAndKeepsOthers()
    {
        $pages = [$this->page('a', [$this->widget('w1'), $this->widget('w2')])];

        $result = PagesFilter::removeWidgets($pages, ['w1']);

        $this->assertCount(1, $result);
        $this->assertSame(['w2'], array_column($result[0]['widgets'], 'uniqueId'));
    }

    public function testRemovesPageWhenAllWidgetsAreHidden()
    {
        $pages = [
            $this->page('a', [$this->widget('w1')]),
            $this->page('b', [$this->widget('w2')]),
        ];

        $result = PagesFilter::removeWidgets($pages, ['w1']);

        $this->assertSame(['b'], array_column($result, 'uniqueId'));
    }

    public function testFiltersWidgetsInsideContainers()
    {
        $container = $this->widget('container', [
            'isContainer' => true,
            'widgets'     => [$this->widget('w1'), $this->widget('w2')],
        ]);
        $pages = [$this->page('a', [$container])];

        $result = PagesFilter::removeWidgets($pages, ['w1']);

        $this->assertSame(['w2'], array_column($result[0]['widgets'][0]['widgets'], 'uniqueId'));
    }

    public function testRemovesEmptyContainersAndTheirPage()
    {
        $container = $this->widget('container', [
            'isContainer' => true,
            'widgets'     => [$this->widget('w1')],
        ]);
        $pages = [$this->page('a', [$container])];

        $this->assertSame([], PagesFilter::removeWidgets($pages, ['w1']));
    }

    public function testKeepsPagesWithoutWidgets()
    {
        $pages = [$this->page('a', [])];

        $this->assertSame($pages, PagesFilter::removeWidgets($pages, ['w1']));
    }

    private function page($uniqueId, array $widgets)
    {
        return ['uniqueId' => $uniqueId, 'widgets' => $widgets];
    }

    private function widget($uniqueId, array $extra = [])
    {
        return array_merge(['uniqueId' => $uniqueId, 'name' => $uniqueId], $extra);
    }
}
