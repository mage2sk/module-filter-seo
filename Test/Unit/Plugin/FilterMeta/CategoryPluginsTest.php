<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Plugin\FilterMeta;

use Magento\Catalog\Block\Category\View as CategoryViewBlock;
use Magento\Catalog\Model\Category;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\FilterSeo\Helper\Config;
use Panth\FilterSeo\Model\FilterMeta\MetaInjector;
use Panth\FilterSeo\Model\FilterUrl\ActiveFacetProvider;
use Panth\FilterSeo\Plugin\FilterMeta\CategoryViewPlugin;
use Panth\FilterSeo\Plugin\FilterMeta\FacetRobotsPlugin;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class CategoryPluginsTest extends TestCase
{
    private function registry(?int $categoryId): Registry
    {
        $category = null;
        if ($categoryId !== null) {
            $category = $this->createStub(Category::class);
            $category->method('getId')->willReturn($categoryId ?: null);
        }
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturn($category);
        return $registry;
    }

    private function storeManager(): StoreManagerInterface
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(2);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        return $storeManager;
    }

    private function seoConfig(bool $noindex = true, int $minFacets = 2): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isNoindexCombinationsEnabled')->willReturn($noindex);
        $config->method('getNoindexMinFacets')->willReturn($minFacets);
        return $config;
    }

    private function scopeConfig(bool $metaEnabled): ScopeConfigInterface
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn($path) => $path === Config::XML_FILTER_META_ENABLED && $metaEnabled
        );
        return $scopeConfig;
    }

    public function testCategoryViewInjectsMetaForCurrentCategoryAndStore(): void
    {
        $page = $this->createStub(PageConfig::class);
        $injector = $this->createMock(MetaInjector::class);
        $injector->expects($this->once())->method('inject')->with($page, 14, 2);

        $plugin = new CategoryViewPlugin(
            $injector,
            $this->registry(14),
            $page,
            $this->storeManager(),
            $this->scopeConfig(true),
            $this->createStub(LoggerInterface::class),
            $this->seoConfig()
        );

        $this->assertSame('layout', $plugin->afterSetLayout($this->createStub(CategoryViewBlock::class), 'layout'));
    }

    public function testCategoryViewSkipsWhenDisabledOrNoCategory(): void
    {
        $injector = $this->createMock(MetaInjector::class);
        $injector->expects($this->never())->method('inject');
        $logger = $this->createStub(LoggerInterface::class);
        $block = $this->createStub(CategoryViewBlock::class);
        $page = $this->createStub(PageConfig::class);

        $disabled = new CategoryViewPlugin($injector, $this->registry(14), $page, $this->storeManager(), $this->scopeConfig(false), $logger, $this->seoConfig());
        $missing = new CategoryViewPlugin($injector, $this->registry(null), $page, $this->storeManager(), $this->scopeConfig(true), $logger, $this->seoConfig());
        $noId = new CategoryViewPlugin($injector, $this->registry(0), $page, $this->storeManager(), $this->scopeConfig(true), $logger, $this->seoConfig());

        $this->assertSame('r1', $disabled->afterSetLayout($block, 'r1'));
        $this->assertSame('r2', $missing->afterSetLayout($block, 'r2'));
        $this->assertSame('r3', $noId->afterSetLayout($block, 'r3'));
    }

    public function testCategoryViewLogsInjectionFailure(): void
    {
        $injector = $this->createStub(MetaInjector::class);
        $injector->method('inject')->willThrowException(new \RuntimeException('bad'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->anything(), ['error' => 'bad']);

        $plugin = new CategoryViewPlugin(
            $injector,
            $this->registry(14),
            $this->createStub(PageConfig::class),
            $this->storeManager(),
            $this->scopeConfig(true),
            $logger,
            $this->seoConfig()
        );

        $this->assertSame('layout', $plugin->afterSetLayout($this->createStub(CategoryViewBlock::class), 'layout'));
    }

    private function facets(int $count): ActiveFacetProvider
    {
        $provider = $this->createStub(ActiveFacetProvider::class);
        $provider->method('countActiveFacets')->willReturn($count);
        return $provider;
    }

    public function testRobotsNoindexWhenFacetCountReachesThreshold(): void
    {
        $page = $this->createMock(PageConfig::class);
        $page->expects($this->once())->method('setRobots')->with('noindex,follow');

        $plugin = new FacetRobotsPlugin(
            $this->facets(2),
            $this->registry(14),
            $page,
            $this->storeManager(),
            $this->seoConfig(true, 2),
            $this->createStub(LoggerInterface::class)
        );

        $this->assertSame('layout', $plugin->afterSetLayout($this->createStub(CategoryViewBlock::class), 'layout'));
    }

    public function testRobotsUntouchedBelowThresholdDisabledOrNoCategory(): void
    {
        $page = $this->createMock(PageConfig::class);
        $page->expects($this->never())->method('setRobots');
        $logger = $this->createStub(LoggerInterface::class);
        $block = $this->createStub(CategoryViewBlock::class);

        $below = new FacetRobotsPlugin($this->facets(1), $this->registry(14), $page, $this->storeManager(), $this->seoConfig(true, 2), $logger);
        $disabled = new FacetRobotsPlugin($this->facets(5), $this->registry(14), $page, $this->storeManager(), $this->seoConfig(false), $logger);
        $missing = new FacetRobotsPlugin($this->facets(5), $this->registry(null), $page, $this->storeManager(), $this->seoConfig(), $logger);

        $this->assertSame('a', $below->afterSetLayout($block, 'a'));
        $this->assertSame('b', $disabled->afterSetLayout($block, 'b'));
        $this->assertSame('c', $missing->afterSetLayout($block, 'c'));
    }

    public function testRobotsFailureIsLogged(): void
    {
        $provider = $this->createStub(ActiveFacetProvider::class);
        $provider->method('countActiveFacets')->willThrowException(new \RuntimeException('layer'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('noindex'), ['error' => 'layer']);

        $plugin = new FacetRobotsPlugin($provider, $this->registry(14), $this->createStub(PageConfig::class), $this->storeManager(), $this->seoConfig(), $logger);

        $this->assertSame('layout', $plugin->afterSetLayout($this->createStub(CategoryViewBlock::class), 'layout'));
    }
}
