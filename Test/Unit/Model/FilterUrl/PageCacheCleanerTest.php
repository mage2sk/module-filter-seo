<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Model\FilterUrl;

use Magento\Catalog\Block\Category\View;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Indexer\CacheContext;
use Magento\Framework\Indexer\CacheContextFactory;
use Panth\FilterSeo\Model\FilterUrl\PageCacheCleaner;
use Panth\FilterSeo\Plugin\FilterUrl\CategoryIdentitiesPlugin;
use PHPUnit\Framework\TestCase;

class PageCacheCleanerTest extends TestCase
{
    public function testCleanDispatchesCleanCacheByTagsWithTheModuleTag(): void
    {
        $context = new CacheContext();
        $factory = $this->createStub(CacheContextFactory::class);
        $factory->method('create')->willReturn($context);
        $events = $this->createMock(ManagerInterface::class);
        $events->expects($this->once())->method('dispatch')
            ->with('clean_cache_by_tags', ['object' => $context]);

        (new PageCacheCleaner($events, $factory))->clean();

        $this->assertSame(['panth_fseo'], $context->getIdentities());
    }

    public function testCategoryPagesCarryTheModuleTagOnce(): void
    {
        $plugin = new CategoryIdentitiesPlugin();
        $view = $this->createStub(View::class);

        $this->assertSame(['cat_c_4', 'panth_fseo'], $plugin->afterGetIdentities($view, ['cat_c_4']));
        $this->assertSame(['cat_c_4', 'panth_fseo'], $plugin->afterGetIdentities($view, ['cat_c_4', 'panth_fseo']));
        $this->assertSame(['panth_fseo'], $plugin->afterGetIdentities($view, null));
    }
}
