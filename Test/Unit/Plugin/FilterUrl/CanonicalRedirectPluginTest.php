<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Plugin\FilterUrl;

use Laminas\Stdlib\Parameters;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Controller\Category\View;
use Magento\Catalog\Model\Category;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection as AttributeCollection;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\DataObject;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\FilterSeo\Helper\Config;
use Panth\FilterSeo\Model\FilterUrl\UrlBuilder;
use Panth\FilterSeo\Plugin\FilterUrl\CanonicalRedirectPlugin;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class CanonicalRedirectPluginTest extends TestCase
{
    private const CATEGORY_URL = 'https://shop.test/women.html';

    private function config(bool $filterUrls = true): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isFilterUrlEnabled')->willReturn($filterUrls);
        return $config;
    }

    private function request(array $query, array $params = []): Http
    {
        $request = $this->createStub(Http::class);
        $request->method('getQuery')->willReturn(new Parameters($query));
        $request->method('getParam')->willReturnCallback(
            static fn($k) => $params[$k] ?? $query[$k] ?? null
        );
        return $request;
    }

    private function plugin(
        Http $request,
        ?UrlBuilder $urlBuilder = null,
        ?RedirectFactory $redirectFactory = null,
        ?CategoryRepositoryInterface $categoryRepository = null,
        ?LoggerInterface $logger = null,
        bool $filterUrls = true
    ): CanonicalRedirectPlugin {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $collection = $this->createStub(AttributeCollection::class);
        $collection->method('getIterator')->willReturnCallback(static fn() => new \ArrayIterator([
            new DataObject(['attribute_code' => 'color']),
            new DataObject(['attribute_code' => 'size']),
            new DataObject(['attribute_code' => 'price']),
        ]));
        $attributeFactory = $this->createStub(AttributeCollectionFactory::class);
        $attributeFactory->method('create')->willReturn($collection);

        if ($categoryRepository === null) {
            $category = $this->createStub(Category::class);
            $category->method('getUrl')->willReturn(self::CATEGORY_URL);
            $categoryRepository = $this->createStub(CategoryRepositoryInterface::class);
            $categoryRepository->method('get')->willReturn($category);
        }

        return new CanonicalRedirectPlugin(
            $this->config($filterUrls),
            $request,
            $storeManager,
            $urlBuilder ?? $this->createStub(UrlBuilder::class),
            $redirectFactory ?? $this->createStub(RedirectFactory::class),
            $attributeFactory,
            $categoryRepository,
            $logger ?? $this->createStub(LoggerInterface::class)
        );
    }

    private function proceed(): callable
    {
        return static fn() => 'proceeded';
    }

    public function testPassesThroughWhenFilterUrlsDisabled(): void
    {
        $plugin = $this->plugin($this->request(['color' => '5'], ['id' => 3]), null, null, null, null, false);

        $this->assertSame('proceeded', $plugin->aroundExecute($this->createStub(View::class), $this->proceed()));
    }

    public function testPassesThroughWithoutCategoryId(): void
    {
        $plugin = $this->plugin($this->request(['color' => '5']));

        $this->assertSame('proceeded', $plugin->aroundExecute($this->createStub(View::class), $this->proceed()));
    }

    public function testPassesThroughWhenNoQueryFilterHasSlug(): void
    {
        $builder = $this->createMock(UrlBuilder::class);
        $builder->method('hasSlug')->willReturn(false);
        $builder->expects($this->never())->method('build');

        $plugin = $this->plugin($this->request(['color' => '5', 'price' => '10-20'], ['id' => 3]), $builder);

        $this->assertSame('proceeded', $plugin->aroundExecute($this->createStub(View::class), $this->proceed()));
    }

    public function testRedirectsPermanentlyToCleanUrlCarryingPagingParams(): void
    {
        $captured = null;
        $builder = $this->createStub(UrlBuilder::class);
        $builder->method('hasSlug')->willReturnCallback(static fn($code, $id) => $code === 'color' && $id === 5);
        $builder->method('build')->willReturnCallback(function ($url, $filters) use (&$captured) {
            $captured = $filters;
            return 'https://shop.test/women/color-red.html?price=10-20';
        });

        $redirect = $this->createMock(Redirect::class);
        $redirect->expects($this->once())->method('setUrl')
            ->with('https://shop.test/women/color-red.html?price=10-20&p=2&product_list_order=price')
            ->willReturnSelf();
        $redirect->expects($this->once())->method('setHttpResponseCode')->with(301)->willReturnSelf();
        $factory = $this->createStub(RedirectFactory::class);
        $factory->method('create')->willReturn($redirect);

        $plugin = $this->plugin(
            $this->request(
                ['color' => '5', 'price' => '10-20', 'p' => '2', 'product_list_order' => 'price', 'product_list_dir' => '', 'utm' => 'x'],
                ['id' => 3]
            ),
            $builder,
            $factory
        );

        $this->assertSame($redirect, $plugin->aroundExecute($this->createStub(View::class), $this->proceed()));
        $this->assertSame([
            ['attribute_code' => 'color', 'option_id' => 5, 'value' => '5'],
            ['attribute_code' => 'price', 'option_id' => 10, 'value' => '10-20'],
        ], $captured);
    }

    public function testPassesThroughWhenBuilderReturnsCategoryUrl(): void
    {
        $builder = $this->createStub(UrlBuilder::class);
        $builder->method('hasSlug')->willReturn(true);
        $builder->method('build')->willReturn(self::CATEGORY_URL);
        $factory = $this->createMock(RedirectFactory::class);
        $factory->expects($this->never())->method('create');

        $plugin = $this->plugin($this->request(['color' => '5'], ['id' => 3]), $builder, $factory);

        $this->assertSame('proceeded', $plugin->aroundExecute($this->createStub(View::class), $this->proceed()));
    }

    public function testFilterOnlyInRouteParamsDoesNotTriggerRedirect(): void
    {
        $builder = $this->createMock(UrlBuilder::class);
        $builder->expects($this->never())->method('hasSlug');
        $builder->expects($this->never())->method('build');

        $plugin = $this->plugin($this->request([], ['id' => 3, 'color' => '5']), $builder);

        $this->assertSame('proceeded', $plugin->aroundExecute($this->createStub(View::class), $this->proceed()));
    }

    public function testErrorsAreLoggedAndRequestProceeds(): void
    {
        $builder = $this->createStub(UrlBuilder::class);
        $builder->method('hasSlug')->willReturn(true);
        $repo = $this->createStub(CategoryRepositoryInterface::class);
        $repo->method('get')->willThrowException(new \RuntimeException('gone'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')
            ->with($this->stringContains('canonical redirect'), ['error' => 'gone']);

        $plugin = $this->plugin($this->request(['color' => '5'], ['id' => 3]), $builder, null, $repo, $logger);

        $this->assertSame('proceeded', $plugin->aroundExecute($this->createStub(View::class), $this->proceed()));
    }
}
