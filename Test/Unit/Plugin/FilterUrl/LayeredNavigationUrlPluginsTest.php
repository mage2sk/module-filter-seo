<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Plugin\FilterUrl;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Magento\Catalog\Model\Layer\Filter\Item;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\LayeredNavigation\Block\Navigation\State as StateBlock;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Swatches\Block\LayeredNavigation\RenderLayered;
use Magento\Theme\Block\Html\Pager;
use Panth\FilterSeo\Helper\Config;
use Panth\FilterSeo\Model\FilterUrl\UrlBuilder;
use Panth\FilterSeo\Plugin\FilterUrl\ClearAllUrlPlugin;
use Panth\FilterSeo\Plugin\FilterUrl\FilterItemRemoveUrlPlugin;
use Panth\FilterSeo\Plugin\FilterUrl\FilterItemUrlPlugin;
use Panth\FilterSeo\Plugin\FilterUrl\PagerPlugin;
use Panth\FilterSeo\Plugin\FilterUrl\SwatchUrlPlugin;
use PHPUnit\Framework\TestCase;

class LayeredNavigationUrlPluginsTest extends TestCase
{
    private const CATEGORY_URL = 'https://shop.test/women.html';

    private const REQUEST_PARAMS = [
        'id' => '3',
        'color' => '5',
        'p' => '2',
        'q' => 'shirt',
        'size' => '',
        'bad-key' => '1',
        'list' => ['a'],
        'price' => '10-20',
    ];

    private const EXPECTED_ACTIVE = [
        ['attribute_code' => 'color', 'option_id' => 5, 'value' => '5'],
        ['attribute_code' => 'price', 'option_id' => 10, 'value' => '10-20'],
    ];

    private function config(bool $enabled = true): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isFilterUrlEnabled')->willReturn($enabled);
        return $config;
    }

    private function request(array $params = self::REQUEST_PARAMS): RequestInterface
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParams')->willReturn($params);
        return $request;
    }

    private function registry(bool $withCategory = true): Registry
    {
        $category = null;
        if ($withCategory) {
            $category = $this->createStub(Category::class);
            $category->method('getUrl')->willReturn(self::CATEGORY_URL);
        }
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(
            static fn($key) => $key === 'current_category' ? $category : null
        );
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

    private function item(string $code, $value): Item
    {
        $filter = $this->createStub(FilterInterface::class);
        $filter->method('getRequestVar')->willReturn($code);
        return new Item(
            $this->createStub(UrlInterface::class),
            $this->createStub(Pager::class),
            ['filter' => $filter, 'value' => $value]
        );
    }

    private static function proceedWith(string $value): callable
    {
        return static fn(...$args) => $value . (empty($args) ? '' : ':' . json_encode($args));
    }

    public function testItemUrlAddsItemToActiveFilters(): void
    {
        $builder = $this->createMock(UrlBuilder::class);
        $builder->expects($this->once())->method('build')
            ->with(self::CATEGORY_URL, [...self::EXPECTED_ACTIVE, ['attribute_code' => 'size', 'option_id' => 7, 'value' => '7']], 2)
            ->willReturn('https://shop.test/women/color-red-size-xl.html?price=10-20');

        $plugin = new FilterItemUrlPlugin(
            $builder,
            $this->config(),
            $this->request(),
            $this->registry(),
            $this->storeManager(),
            $this->createStub(UrlInterface::class)
        );

        $this->assertSame(
            'https://shop.test/women/color-red-size-xl.html?price=10-20',
            $plugin->aroundGetUrl($this->item('size', '7'), self::proceedWith('core'))
        );
    }

    public function testItemUrlFallsBackWhenNothingCouldBeSlugged(): void
    {
        $builder = $this->createStub(UrlBuilder::class);
        $builder->method('build')->willReturn(self::CATEGORY_URL);

        $plugin = new FilterItemUrlPlugin(
            $builder,
            $this->config(),
            $this->request([]),
            $this->registry(),
            $this->storeManager(),
            $this->createStub(UrlInterface::class)
        );

        $this->assertSame('core', $plugin->aroundGetUrl($this->item('size', ['a']), self::proceedWith('core')));
    }

    public function testItemUrlUsesCoreWhenDisabledOrNoCategory(): void
    {
        $builder = $this->createMock(UrlBuilder::class);
        $builder->expects($this->never())->method('build');

        $disabled = new FilterItemUrlPlugin(
            $builder,
            $this->config(false),
            $this->request(),
            $this->registry(),
            $this->storeManager(),
            $this->createStub(UrlInterface::class)
        );
        $noCategory = new FilterItemUrlPlugin(
            $builder,
            $this->config(),
            $this->request(),
            $this->registry(false),
            $this->storeManager(),
            $this->createStub(UrlInterface::class)
        );

        $this->assertSame('core', $disabled->aroundGetUrl($this->item('size', '7'), self::proceedWith('core')));
        $this->assertSame('core', $noCategory->aroundGetUrl($this->item('size', '7'), self::proceedWith('core')));
    }

    public function testRemoveUrlDelegatesToBuildWithout(): void
    {
        $builder = $this->createMock(UrlBuilder::class);
        $builder->expects($this->once())->method('buildWithout')
            ->with(self::CATEGORY_URL, self::EXPECTED_ACTIVE, 'color', 5, 2)
            ->willReturn('https://shop.test/women.html?price=10-20');

        $plugin = new FilterItemRemoveUrlPlugin(
            $builder,
            $this->config(),
            $this->request(),
            $this->registry(),
            $this->storeManager()
        );

        $this->assertSame(
            'https://shop.test/women.html?price=10-20',
            $plugin->aroundGetRemoveUrl($this->item('color', '5'), self::proceedWith('core'))
        );
    }

    public function testRemoveUrlUsesCoreWhenDisabledOrNoCategory(): void
    {
        $builder = $this->createMock(UrlBuilder::class);
        $builder->expects($this->never())->method('buildWithout');

        $disabled = new FilterItemRemoveUrlPlugin($builder, $this->config(false), $this->request(), $this->registry(), $this->storeManager());
        $noCategory = new FilterItemRemoveUrlPlugin($builder, $this->config(), $this->request(), $this->registry(false), $this->storeManager());

        $this->assertSame('core', $disabled->aroundGetRemoveUrl($this->item('color', '5'), self::proceedWith('core')));
        $this->assertSame('core', $noCategory->aroundGetRemoveUrl($this->item('color', '5'), self::proceedWith('core')));
    }

    public function testSwatchUrlAppendsSwatchOption(): void
    {
        $builder = $this->createMock(UrlBuilder::class);
        $builder->expects($this->once())->method('build')
            ->with(self::CATEGORY_URL, [...self::EXPECTED_ACTIVE, ['attribute_code' => 'size', 'option_id' => 7]], 2)
            ->willReturn('https://shop.test/women/color-red-size-xl.html');

        $plugin = new SwatchUrlPlugin($builder, $this->config(), $this->request(), $this->registry(), $this->storeManager());

        $this->assertSame(
            'https://shop.test/women/color-red-size-xl.html',
            $plugin->aroundBuildUrl($this->createStub(RenderLayered::class), self::proceedWith('core'), 'size', 7)
        );
    }

    public function testSwatchUrlFallsBackToCoreWithOriginalArguments(): void
    {
        $builder = $this->createStub(UrlBuilder::class);
        $builder->method('build')->willReturn(self::CATEGORY_URL);

        $plugin = new SwatchUrlPlugin($builder, $this->config(), $this->request(), $this->registry(), $this->storeManager());
        $disabled = new SwatchUrlPlugin($builder, $this->config(false), $this->request(), $this->registry(), $this->storeManager());

        $subject = $this->createStub(RenderLayered::class);
        $this->assertSame('core:["size",7]', $plugin->aroundBuildUrl($subject, self::proceedWith('core'), 'size', 7));
        $this->assertSame('core:["size",7]', $disabled->aroundBuildUrl($subject, self::proceedWith('core'), 'size', 7));
    }

    public function testPagerUrlAppendsNonNullParamsToCleanUrl(): void
    {
        $builder = $this->createStub(UrlBuilder::class);
        $builder->method('build')->willReturn('https://shop.test/women/color-red.html?price=10-20');

        $plugin = new PagerPlugin($builder, $this->config(), $this->request(), $this->registry(), $this->storeManager());

        $this->assertSame(
            'https://shop.test/women/color-red.html?price=10-20&p=3',
            $plugin->aroundGetPagerUrl($this->createStub(Pager::class), self::proceedWith('core'), ['p' => 3, 'product_list_limit' => null])
        );
    }

    public function testPagerUrlWithoutParamsIsTheCleanUrl(): void
    {
        $builder = $this->createStub(UrlBuilder::class);
        $builder->method('build')->willReturn('https://shop.test/women/color-red.html');

        $plugin = new PagerPlugin($builder, $this->config(), $this->request(), $this->registry(), $this->storeManager());

        $this->assertSame(
            'https://shop.test/women/color-red.html',
            $plugin->aroundGetPagerUrl($this->createStub(Pager::class), self::proceedWith('core'), ['p' => null])
        );
    }

    public function testPagerUsesCoreWithoutActiveFiltersOrWhenUnchanged(): void
    {
        $builder = $this->createStub(UrlBuilder::class);
        $builder->method('build')->willReturn(self::CATEGORY_URL);
        $pager = $this->createStub(Pager::class);

        $noFilters = new PagerPlugin($builder, $this->config(), $this->request(['p' => '2', 'id' => '3']), $this->registry(), $this->storeManager());
        $unchanged = new PagerPlugin($builder, $this->config(), $this->request(), $this->registry(), $this->storeManager());
        $noCategory = new PagerPlugin($builder, $this->config(), $this->request(), $this->registry(false), $this->storeManager());

        $this->assertSame('core:[{"p":4}]', $noFilters->aroundGetPagerUrl($pager, self::proceedWith('core'), ['p' => 4]));
        $this->assertSame('core:[{"p":4}]', $unchanged->aroundGetPagerUrl($pager, self::proceedWith('core'), ['p' => 4]));
        $this->assertSame('core:[{"p":4}]', $noCategory->aroundGetPagerUrl($pager, self::proceedWith('core'), ['p' => 4]));
    }

    private function layerResolver(?Category $category, bool $throws = false): LayerResolver
    {
        $resolver = $this->createStub(LayerResolver::class);
        if ($throws) {
            $resolver->method('get')->willThrowException(new \RuntimeException('no layer'));
            return $resolver;
        }
        $layer = $this->createStub(Layer::class);
        $layer->method('getCurrentCategory')->willReturn($category);
        $resolver->method('get')->willReturn($layer);
        return $resolver;
    }

    public function testClearAllPointsToBareCategoryUrl(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(3);
        $category->method('getUrl')->willReturn(self::CATEGORY_URL);

        $plugin = new ClearAllUrlPlugin($this->config(), $this->layerResolver($category), $this->storeManager());

        $this->assertSame(self::CATEGORY_URL, $plugin->afterGetClearUrl($this->createStub(StateBlock::class), 'core-clear'));
    }

    public function testClearAllKeepsCoreResultWhenNotApplicable(): void
    {
        $noId = $this->createStub(Category::class);
        $noId->method('getId')->willReturn(null);
        $subject = $this->createStub(StateBlock::class);

        $disabled = new ClearAllUrlPlugin($this->config(false), $this->layerResolver($noId), $this->storeManager());
        $missing = new ClearAllUrlPlugin($this->config(), $this->layerResolver($noId), $this->storeManager());
        $failing = new ClearAllUrlPlugin($this->config(), $this->layerResolver(null, true), $this->storeManager());

        $this->assertSame('core-clear', $disabled->afterGetClearUrl($subject, 'core-clear'));
        $this->assertSame('core-clear', $missing->afterGetClearUrl($subject, 'core-clear'));
        $this->assertSame('core-clear', $failing->afterGetClearUrl($subject, 'core-clear'));
    }
}
