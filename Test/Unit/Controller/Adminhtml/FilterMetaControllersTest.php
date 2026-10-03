<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Controller\Adminhtml;

use Magento\Backend\Model\View\Result\Forward;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Backend\Model\View\Result\Page;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Registry;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\PageFactory;
use Magento\Ui\Component\MassAction\Filter;
use Panth\FilterSeo\Controller\Adminhtml\FilterMeta\Delete;
use Panth\FilterSeo\Controller\Adminhtml\FilterMeta\Edit;
use Panth\FilterSeo\Controller\Adminhtml\FilterMeta\MassDelete;
use Panth\FilterSeo\Controller\Adminhtml\FilterMeta\NewAction;
use Panth\FilterSeo\Controller\Adminhtml\FilterMeta\Save;
use Panth\FilterSeo\Model\ResourceModel\CategoryFilterMeta\Collection;
use Panth\FilterSeo\Model\ResourceModel\CategoryFilterMeta\CollectionFactory;

class FilterMetaControllersTest extends ControllerTestCase
{
    private const VALID_POST = [
        'category_id' => '14',
        'attribute_code' => 'color',
        'option_id' => '5',
        'store_id' => '1',
        'meta_title' => 'Red dresses',
        'meta_description' => 'Shop red',
        'meta_keywords' => 'red',
        'breadcrumbs_priority' => '3',
    ];

    public function testSaveWithoutPostRedirectsToGrid(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('insert');

        (new Save($this->context(), $this->resource($connection)))->execute();

        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testSaveRequiresCategoryAndAttribute(): void
    {
        $this->post = ['id' => '8', 'category_id' => '0', 'attribute_code' => 'color'];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('update');

        (new Save($this->context(), $this->resource($connection)))->execute();

        $this->assertSame(['*/*/edit', ['id' => 8]], $this->redirect);
        $this->assertSame([['error', 'Category ID and Attribute Code are required.']], $this->messages);
    }

    public function testSaveInsertsTypedRowAndHonoursBackParam(): void
    {
        $this->post = self::VALID_POST + ['ignored' => 'x'];
        $this->params = ['back' => 'edit'];
        $connection = $this->createMock(Mysql::class);
        $connection->expects($this->once())->method('insert')->with('panth_seo_category_filter_meta', [
            'category_id' => 14,
            'attribute_code' => 'color',
            'option_id' => 5,
            'store_id' => 1,
            'meta_title' => 'Red dresses',
            'meta_description' => 'Shop red',
            'meta_keywords' => 'red',
            'breadcrumbs_priority' => 3,
        ]);
        $connection->method('lastInsertId')->willReturn('31');

        (new Save($this->context(), $this->resource($connection)))->execute();

        $this->assertSame(['*/*/edit', ['id' => 31]], $this->redirect);
        $this->assertSame([['success', 'Filter meta saved.']], $this->messages);
    }

    public function testSaveUpdatesExistingRowAndReturnsToGrid(): void
    {
        $this->post = ['id' => '9'] + self::VALID_POST;
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('update')
            ->with('panth_seo_category_filter_meta', $this->arrayHasKey('meta_title'), ['id = ?' => 9]);
        $connection->expects($this->never())->method('insert');

        (new Save($this->context(), $this->resource($connection)))->execute();

        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testSaveReportsDuplicateRows(): void
    {
        $this->post = self::VALID_POST;
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('insert')->willThrowException(new DuplicateException('dup'));

        (new Save($this->context(), $this->resource($connection)))->execute();

        $this->assertSame(['*/*/edit', ['id' => 0]], $this->redirect);
        $this->assertStringContainsString('already exists', $this->messages[0][1]);
    }

    public function testSaveHidesUnexpectedErrorDetails(): void
    {
        $this->post = ['id' => '4'] + self::VALID_POST;
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('update')->willThrowException(new \RuntimeException('SQLSTATE secret'));

        (new Save($this->context(), $this->resource($connection)))->execute();

        $this->assertSame(['*/*/edit', ['id' => 4]], $this->redirect);
        $this->assertSame([['error', 'The record could not be saved.']], $this->messages);
    }

    public function testDeleteRemovesRowById(): void
    {
        $this->params = ['id' => '6'];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('delete')
            ->with('panth_seo_category_filter_meta', ['id = ?' => 6]);

        (new Delete($this->context(), $this->resource($connection)))->execute();

        $this->assertSame(['*/*/', []], $this->redirect);
        $this->assertSame([['success', 'Filter meta deleted.']], $this->messages);
    }

    public function testDeleteIgnoresMissingIdAndReportsFailures(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('delete')->willThrowException(new \RuntimeException('locked'));

        (new Delete($this->context(), $this->resource($connection)))->execute();
        $this->assertSame([], $this->messages);

        $this->params = ['id' => '6'];
        (new Delete($this->context(), $this->resource($connection)))->execute();
        $this->assertSame([['error', 'locked']], $this->messages);
    }

    public function testMassDeleteDeletesEverySelectedItem(): void
    {
        $items = [];
        for ($i = 0; $i < 3; $i++) {
            $item = $this->createMock(AbstractModel::class);
            $item->expects($this->once())->method('delete');
            $items[] = $item;
        }
        $collection = $this->createStub(Collection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator($items));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $filter = $this->createStub(Filter::class);
        $filter->method('getCollection')->willReturn($collection);

        $redirect = $this->createMock(Redirect::class);
        $redirect->expects($this->once())->method('setPath')->with('*/*/index')->willReturnSelf();
        $this->resultFactory = $this->createStub(ResultFactory::class);
        $this->resultFactory->method('create')->willReturn($redirect);

        $result = (new MassDelete($this->context(), $filter, $factory))->execute();

        $this->assertSame($redirect, $result);
        $this->assertSame([['success', 'A total of 3 record(s) were deleted.']], $this->messages);
    }

    private function page(string $expectedTitle, string $expectedMenu): PageFactory
    {
        $title = $this->createMock(Title::class);
        $title->expects($this->once())->method('prepend')->with($this->callback(
            static fn($t) => (string) $t === $expectedTitle
        ));
        $config = $this->createStub(PageConfig::class);
        $config->method('getTitle')->willReturn($title);
        $page = $this->createMock(Page::class);
        $page->expects($this->once())->method('setActiveMenu')->with($expectedMenu)->willReturnSelf();
        $page->method('getConfig')->willReturn($config);
        $factory = $this->createStub(PageFactory::class);
        $factory->method('create')->willReturn($page);
        return $factory;
    }

    public function testEditRegistersLoadedRow(): void
    {
        $this->params = ['id' => '5'];
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchRow')->willReturn(['id' => 5, 'attribute_code' => 'color']);
        $registry = $this->createMock(Registry::class);
        $registry->expects($this->once())->method('register')
            ->with('panth_seo_category_filter_meta', ['id' => 5, 'attribute_code' => 'color'], true);

        (new Edit(
            $this->context(),
            $this->page('Edit Filter Meta', 'Panth_FilterSeo::filter_meta'),
            $registry,
            $this->resource($connection)
        ))->execute();
    }

    public function testEditForNewRecordRegistersEmptyRow(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('fetchRow');
        $registry = $this->createMock(Registry::class);
        $registry->expects($this->once())->method('register')->with('panth_seo_category_filter_meta', [], true);

        (new Edit(
            $this->context(),
            $this->page('New Filter Meta', 'Panth_FilterSeo::filter_meta'),
            $registry,
            $this->resource($connection)
        ))->execute();
    }

    public function testNewActionForwardsToEdit(): void
    {
        $forward = $this->createMock(Forward::class);
        $forward->expects($this->once())->method('forward')->with('edit')->willReturnSelf();
        $factory = $this->createStub(ForwardFactory::class);
        $factory->method('create')->willReturn($forward);

        $this->assertSame($forward, (new NewAction($this->context(), $factory))->execute());
    }
}
