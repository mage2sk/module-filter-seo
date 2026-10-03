<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Ui\Component\Listing\Column;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\Group;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use Panth\FilterSeo\Model\FilterUrl\ViewUrlResolver;
use Panth\FilterSeo\Ui\Component\Listing\Column\CategoryName;
use Panth\FilterSeo\Ui\Component\Listing\Column\FilterMetaActions;
use Panth\FilterSeo\Ui\Component\Listing\Column\FilterRewriteActions;
use Panth\FilterSeo\Ui\Component\Listing\Column\OptionLabel;
use Panth\FilterSeo\Ui\Component\Listing\Column\StoreView;
use PHPUnit\Framework\TestCase;

class ColumnsTest extends TestCase
{
    private function ctx(): ContextInterface
    {
        return $this->createStub(ContextInterface::class);
    }

    private function factory(): UiComponentFactory
    {
        return $this->createStub(UiComponentFactory::class);
    }

    public function testColumnsLeaveDataSourceWithoutItemsUntouched(): void
    {
        $source = ['data' => ['totalRecords' => 0]];
        $columns = [
            new CategoryName($this->ctx(), $this->factory(), $this->createStub(CategoryRepositoryInterface::class)),
            new OptionLabel($this->ctx(), $this->factory(), $this->createStub(AttributeOptionManagementInterface::class)),
            new StoreView($this->ctx(), $this->factory(), $this->createStub(StoreManagerInterface::class)),
        ];

        foreach ($columns as $column) {
            $this->assertSame($source, $column->prepareDataSource($source));
        }
    }

    public function testCategoryNameResolvesCachesAndFlagsDeleted(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getName')->willReturn('Dresses');
        $repo = $this->createMock(CategoryRepositoryInterface::class);
        $repo->expects($this->exactly(2))->method('get')->willReturnCallback(
            static function ($id) use ($category) {
                if ($id === 99) {
                    throw new NoSuchEntityException();
                }
                return $category;
            }
        );

        $column = new CategoryName($this->ctx(), $this->factory(), $repo, [], ['name' => 'category_label']);
        $result = $column->prepareDataSource(['data' => ['items' => [
            ['category_id' => '14'],
            ['category_id' => 14],
            ['category_id' => 99],
            ['category_id' => 0],
            [],
        ]]]);

        $this->assertSame(
            ['Dresses (14)', 'Dresses (14)', '(deleted #99)', '', ''],
            array_column($result['data']['items'], 'category_label')
        );
    }

    private function option(string $value, string $label): AttributeOptionInterface
    {
        $option = $this->createStub(AttributeOptionInterface::class);
        $option->method('getValue')->willReturn($value);
        $option->method('getLabel')->willReturn($label);
        return $option;
    }

    public function testOptionLabelResolvesPerAttributeWithCache(): void
    {
        $management = $this->createMock(AttributeOptionManagementInterface::class);
        $management->expects($this->exactly(2))->method('getItems')->willReturnCallback(
            function ($entity, $code) {
                if ($code === 'broken') {
                    throw new \RuntimeException('missing');
                }
                return [$this->option('', 'none'), $this->option('5', 'Red'), $this->option('6', '')];
            }
        );

        $column = new OptionLabel($this->ctx(), $this->factory(), $management);
        $result = $column->prepareDataSource(['data' => ['items' => [
            ['attribute_code' => 'color', 'option_id' => 5],
            ['attribute_code' => 'color', 'option_id' => 6],
            ['attribute_code' => 'color', 'option_id' => 7],
            ['attribute_code' => 'broken', 'option_id' => 1],
            ['attribute_code' => '', 'option_id' => 1],
        ]]]);

        $this->assertSame(
            ['Red (5)', '(option #6)', '(option #7)', '(option #1)', ''],
            array_column($result['data']['items'], 'option_label')
        );
    }

    public function testStoreViewRendersFullStorePath(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getName')->willReturn('English');
        $store->method('getWebsiteId')->willReturn(1);
        $store->method('getStoreGroupId')->willReturn(1);
        $website = $this->createStub(Website::class);
        $website->method('getName')->willReturn('Main Website');
        $group = $this->createStub(Group::class);
        $group->method('getName')->willReturn('Main Store');

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturnCallback(static function ($id) use ($store) {
            if ($id === 5) {
                throw new NoSuchEntityException();
            }
            return $store;
        });
        $storeManager->method('getWebsite')->willReturn($website);
        $storeManager->method('getGroup')->willReturn($group);

        $column = new StoreView($this->ctx(), $this->factory(), $storeManager);
        $result = $column->prepareDataSource(['data' => ['items' => [
            ['store_id' => '1'],
            ['store_id' => '0'],
            ['store_id' => 5],
            ['store_id' => ''],
            [],
        ]]]);

        $this->assertSame(
            ['Main Website / Main Store / English', 'All Store Views', 'Unknown', '', ''],
            array_column($result['data']['items'], 'store_id')
        );
    }

    private function urlBuilder(): UrlInterface
    {
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn($route, $params = []) => 'admin/' . $route . '/id/' . $params['id']
        );
        return $url;
    }

    private function resourceWithRows(array $rows, array &$requestedIds = []): ResourceConnection
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(function ($cond, $ids) use ($select, &$requestedIds) {
            $requestedIds = $ids;
            return $select;
        });
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn($rows);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return $resource;
    }

    public function testFilterMetaActionsBuildEditViewAndDeleteLinks(): void
    {
        $resolver = $this->createMock(ViewUrlResolver::class);
        $resolver->expects($this->exactly(2))->method('resolveForCategory')->willReturnCallback(
            static fn($cat, $code, $opt, $store) => $cat === 14 && $code === 'color' && $opt === 5 && $store === 1
                ? 'https://shop.test/dresses/color-red.html'
                : ''
        );
        $ids = [];
        $column = new FilterMetaActions(
            $this->ctx(),
            $this->factory(),
            $this->urlBuilder(),
            $resolver,
            $this->resourceWithRows([
                ['id' => '1', 'category_id' => '14', 'attribute_code' => 'color', 'option_id' => '5', 'store_id' => '1'],
            ], $ids),
            [],
            ['name' => 'actions']
        );

        $items = $column->prepareDataSource(['data' => ['items' => [['id' => 1], ['id' => 2], ['name' => 'no id']]]])['data']['items'];

        $this->assertSame([1, 2], $ids);
        $this->assertSame('admin/panth_filterseo/filtermeta/edit/id/1', $items[0]['actions']['edit']['href']);
        $this->assertSame('https://shop.test/dresses/color-red.html', $items[0]['actions']['view']['href']);
        $this->assertSame('_blank', $items[0]['actions']['view']['target']);
        $this->assertTrue($items[0]['actions']['delete']['post']);
        $this->assertSame('admin/panth_filterseo/filtermeta/delete/id/2', $items[1]['actions']['delete']['href']);
        $this->assertArrayNotHasKey('view', $items[1]['actions']);
        $this->assertArrayNotHasKey('actions', $items[2]);
    }

    public function testFilterRewriteActionsResolveWithoutCategory(): void
    {
        $resolver = $this->createMock(ViewUrlResolver::class);
        $resolver->expects($this->once())->method('resolveWithoutCategory')
            ->with('size', 7, 0)
            ->willReturn('https://shop.test/men/size-xl.html');

        $column = new FilterRewriteActions(
            $this->ctx(),
            $this->factory(),
            $this->urlBuilder(),
            $resolver,
            $this->resourceWithRows([['rewrite_id' => '3', 'attribute_code' => 'size', 'option_id' => '7', 'store_id' => '0']]),
            [],
            ['name' => 'actions']
        );

        $items = $column->prepareDataSource(['data' => ['items' => [['rewrite_id' => '3']]]])['data']['items'];

        $this->assertSame('admin/panth_filterseo/filterRewrite/edit/id/3', $items[0]['actions']['edit']['href']);
        $this->assertSame('https://shop.test/men/size-xl.html', $items[0]['actions']['view']['href']);
        $this->assertSame('Delete rewrite', $items[0]['actions']['delete']['confirm']['title']);
    }

    public function testActionsSkipDatabaseWhenNoIdsPresent(): void
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects($this->never())->method('getConnection');

        $column = new FilterRewriteActions(
            $this->ctx(),
            $this->factory(),
            $this->urlBuilder(),
            $this->createStub(ViewUrlResolver::class),
            $resource,
            [],
            ['name' => 'actions']
        );

        $source = ['data' => ['items' => [['foo' => 'bar']]]];
        $this->assertSame($source, $column->prepareDataSource($source));
    }
}
