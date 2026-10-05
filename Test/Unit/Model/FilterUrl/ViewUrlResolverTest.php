<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Model\FilterUrl;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Panth\FilterSeo\Helper\Config;
use Panth\FilterSeo\Model\FilterUrl\UrlBuilder;
use Panth\FilterSeo\Model\FilterUrl\ViewUrlResolver;
use PHPUnit\Framework\TestCase;

class ViewUrlResolverTest extends TestCase
{
    private function store(int $id, int $rootId, string $baseUrl): Store
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn($id);
        $store->method('getRootCategoryId')->willReturn($rootId);
        $store->method('getBaseUrl')->willReturn($baseUrl);
        return $store;
    }

    /**
     * @param array<int, Store> $stores
     * @param array<int, string> $categoryPaths
     * @param array<string, string> $rewrites  "categoryId|storeId" => request path
     */
    private function resolver(
        array $stores,
        array $categoryPaths,
        array $rewrites,
        bool $filterUrlsEnabled = false,
        ?UrlBuilder $urlBuilder = null,
        ?ResourceConnection $resource = null,
        ?AttributeRepositoryInterface $attributeRepository = null
    ): ViewUrlResolver {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturnCallback(function ($id) use ($stores) {
            if (!isset($stores[$id])) {
                throw new NoSuchEntityException();
            }
            return $stores[$id];
        });
        $storeManager->method('getStores')->willReturn(array_values($stores));

        $categoryRepository = $this->createStub(CategoryRepositoryInterface::class);
        $categoryRepository->method('get')->willReturnCallback(function ($id) use ($categoryPaths) {
            if (!isset($categoryPaths[$id])) {
                throw new NoSuchEntityException();
            }
            $category = $this->createStub(\Magento\Catalog\Model\Category::class);
            $category->method('getPath')->willReturn($categoryPaths[$id]);
            return $category;
        });

        $finder = $this->createStub(UrlFinderInterface::class);
        $finder->method('findOneByData')->willReturnCallback(function (array $data) use ($rewrites) {
            $key = $data[UrlRewrite::ENTITY_ID] . '|' . $data[UrlRewrite::STORE_ID];
            if (!isset($rewrites[$key])) {
                return null;
            }
            $rewrite = $this->createStub(UrlRewrite::class);
            $rewrite->method('getRequestPath')->willReturn($rewrites[$key]);
            return $rewrite;
        });

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn($path) => $path === Config::XML_FILTER_URL_ENABLED && $filterUrlsEnabled
        );

        return new ViewUrlResolver(
            $resource ?? $this->createStub(ResourceConnection::class),
            $categoryRepository,
            $storeManager,
            $scopeConfig,
            $urlBuilder ?? $this->createStub(UrlBuilder::class),
            $attributeRepository ?? $this->createStub(AttributeRepositoryInterface::class),
            $finder
        );
    }

    private function resourceReturning(array $fetchOneResults): ResourceConnection
    {
        $select = $this->createStub(Select::class);
        foreach (['from', 'join', 'where', 'order', 'limit'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls(...$fetchOneResults);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return $resource;
    }

    public function testGuardClausesReturnEmptyString(): void
    {
        $resolver = $this->resolver([], [], []);

        $this->assertSame('', $resolver->resolveForCategory(0, 'color', 5, 1));
        $this->assertSame('', $resolver->resolveForCategory(10, '', 5, 1));
        $this->assertSame('', $resolver->resolveForCategory(10, 'color', 0, 1));
        $this->assertSame('', $resolver->resolveWithoutCategory('', 5, 1));
        $this->assertSame('', $resolver->resolveWithoutCategory('color', 0, 1));
    }

    public function testUnknownStoreYieldsEmptyString(): void
    {
        $this->assertSame('', $this->resolver([], [10 => '1/2/10'], [])->resolveForCategory(10, 'color', 5, 9));
    }

    public function testCategoryOutsideStoreRootYieldsEmptyString(): void
    {
        $resolver = $this->resolver(
            [1 => $this->store(1, 2, 'https://shop.test/')],
            [10 => '1/3/10'],
            ['10|1' => 'women.html']
        );

        $this->assertSame('', $resolver->resolveForCategory(10, 'color', 5, 1));
    }

    public function testMissingRewriteYieldsEmptyString(): void
    {
        $resolver = $this->resolver([1 => $this->store(1, 2, 'https://shop.test/')], [10 => '1/2/10'], []);

        $this->assertSame('', $resolver->resolveForCategory(10, 'color', 5, 1));
    }

    public function testFallsBackToQueryStringWhenFilterUrlsDisabled(): void
    {
        $resolver = $this->resolver(
            [1 => $this->store(1, 2, 'https://shop.test/')],
            [10 => '1/2/10'],
            ['10|1' => '/women.html']
        );

        $this->assertSame('https://shop.test/women.html?color=5', $resolver->resolveForCategory(10, 'color', 5, 1));
    }

    public function testUsesCleanUrlWhenFilterUrlsEnabled(): void
    {
        $builder = $this->createMock(UrlBuilder::class);
        $builder->expects($this->once())->method('build')
            ->with('https://shop.test/women.html', [['attribute_code' => 'color', 'option_id' => 5]], 1)
            ->willReturn('https://shop.test/women/color-red.html');

        $resolver = $this->resolver(
            [1 => $this->store(1, 2, 'https://shop.test/')],
            [10 => '1/2/10'],
            ['10|1' => 'women.html'],
            true,
            $builder
        );

        $this->assertSame('https://shop.test/women/color-red.html', $resolver->resolveForCategory(10, 'color', 5, 1));
    }

    public function testFallsBackToQueryWhenBuilderCannotSlug(): void
    {
        $builder = $this->createStub(UrlBuilder::class);
        $builder->method('build')->willReturnArgument(0);

        $resolver = $this->resolver(
            [1 => $this->store(1, 2, 'https://shop.test/')],
            [10 => '1/2/10'],
            ['10|1' => 'women.html'],
            true,
            $builder
        );

        $this->assertSame('https://shop.test/women.html?color=5', $resolver->resolveForCategory(10, 'color', 5, 1));
    }

    public function testAdminStoreScopeTriesEachStorefrontStore(): void
    {
        $resolver = $this->resolver(
            [1 => $this->store(1, 2, 'https://one.test/'), 2 => $this->store(2, 3, 'https://two.test/')],
            [10 => '1/3/10'],
            ['10|2' => 'kids.html']
        );

        $this->assertSame('https://two.test/kids.html?color=5', $resolver->resolveForCategory(10, 'color', 5, 0));
    }

    public function testWithoutCategoryUsesFilterMetaCategoryFirst(): void
    {
        $resolver = $this->resolver(
            [1 => $this->store(1, 2, 'https://shop.test/')],
            [42 => '1/2/42'],
            ['42|1' => 'sale.html'],
            false,
            null,
            $this->resourceReturning(['42'])
        );

        $this->assertSame('https://shop.test/sale.html?color=5', $resolver->resolveWithoutCategory('color', 5, 1));
    }

    public function testWithoutCategoryFallsBackToFirstStorefrontCategory(): void
    {
        $attributeRepository = $this->createStub(AttributeRepositoryInterface::class);
        $attributeRepository->method('get')->willThrowException(new NoSuchEntityException());

        $resolver = $this->resolver(
            [1 => $this->store(1, 2, 'https://shop.test/')],
            [7 => '1/2/7'],
            ['7|1' => 'men.html'],
            false,
            null,
            $this->resourceReturning([false, '7']),
            $attributeRepository
        );

        $this->assertSame('https://shop.test/men.html?color=5', $resolver->resolveWithoutCategory('color', 5, 1));
    }

    public function testWithoutCategoryReturnsEmptyWhenStoreHasNoRoot(): void
    {
        $attributeRepository = $this->createStub(AttributeRepositoryInterface::class);
        $attributeRepository->method('get')->willThrowException(new NoSuchEntityException());

        $resolver = $this->resolver(
            [1 => $this->store(1, 0, 'https://shop.test/')],
            [],
            [],
            false,
            null,
            null,
            $attributeRepository
        );

        $this->assertSame('', $resolver->resolveWithoutCategory('color', 5, 1));
    }
}
