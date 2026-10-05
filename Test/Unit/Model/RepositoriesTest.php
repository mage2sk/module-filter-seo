<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use Panth\FilterSeo\Model\CategoryFilterMeta\Repository as MetaRepository;
use Panth\FilterSeo\Model\FilterRewrite\Repository as RewriteRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RepositoriesTest extends TestCase
{
    public static function repositories(): array
    {
        return [
            'filter meta'    => [MetaRepository::class, 'panth_seo_category_filter_meta', 'id'],
            'filter rewrite' => [RewriteRepository::class, 'panth_seo_filter_rewrite', 'rewrite_id'],
        ];
    }

    private function resource(AdapterInterface $connection): ResourceConnection
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return $resource;
    }

    #[DataProvider('repositories')]
    public function testMissingTableShortCircuits(string $class, string $table, string $idField): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->exactly(2))->method('isTableExists')->with($table)->willReturn(false);
        $this->assertContains($idField, ['id', 'rewrite_id']);
        $connection->expects($this->never())->method('fetchRow');
        $connection->expects($this->never())->method('delete');

        $repo = new $class($this->resource($connection));

        $this->assertNull($repo->getById(5));
        $this->assertFalse($repo->deleteById(5));
    }

    #[DataProvider('repositories')]
    public function testGetByIdReturnsRowOrNull(string $class, string $table, string $idField): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->with($table)->willReturnSelf();
        $select->expects($this->exactly(2))->method('where')->with($idField . ' = ?', $this->anything())->willReturnSelf();

        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchRow')->willReturnOnConsecutiveCalls([$idField => 5, 'x' => 'y'], false);

        $repo = new $class($this->resource($connection));

        $this->assertSame([$idField => 5, 'x' => 'y'], $repo->getById(5));
        $this->assertNull($repo->getById(6));
    }

    #[DataProvider('repositories')]
    public function testDeleteByIdReportsAffectedRows(string $class, string $table, string $idField): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $connection->expects($this->exactly(2))->method('delete')
            ->with($table, [$idField . ' = ?' => 9])
            ->willReturnOnConsecutiveCalls(1, 0);

        $repo = new $class($this->resource($connection));

        $this->assertTrue($repo->deleteById(9));
        $this->assertFalse($repo->deleteById(9));
    }

    #[DataProvider('repositories')]
    public function testSaveReturnsUnsupportedEntityUntouched(string $class, string $table, string $idField): void
    {
        $entity = new \stdClass();
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects($this->never())->method('getConnection');
        $this->assertStringStartsWith('panth_seo_', $table . $idField);
        $repo = new $class($resource);

        $this->assertSame($entity, $repo->save($entity));
    }

    #[DataProvider('repositories')]
    public function testSaveInsertsNewArrayRowAndReturnsId(string $class, string $table, string $idField): void
    {
        $connection = $this->createMock(Mysql::class);
        $connection->method('isTableExists')->with($table)->willReturn(true);
        $connection->method('describeTable')->with($table)
            ->willReturn([$idField => [], 'attribute_code' => [], 'store_id' => []]);
        $connection->expects($this->once())->method('insert')
            ->with($table, ['attribute_code' => 'color', 'store_id' => 1]);
        $connection->expects($this->never())->method('update');
        $connection->method('lastInsertId')->willReturn('42');

        $repo = new $class($this->resource($connection));
        $saved = $repo->save(['attribute_code' => 'color', 'store_id' => 1, 'unknown' => 'x']);

        $this->assertSame(42, $saved[$idField]);
    }

    #[DataProvider('repositories')]
    public function testSaveUpdatesExistingDataObject(string $class, string $table, string $idField): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $connection->method('describeTable')->willReturn([$idField => [], 'option_id' => []]);
        $connection->expects($this->never())->method('insert');
        $connection->expects($this->once())->method('update')
            ->with($table, ['option_id' => 7], [$idField . ' = ?' => 9]);

        $entity = new DataObject([$idField => '9', 'option_id' => 7, 'form_key' => 'abc']);
        $repo = new $class($this->resource($connection));

        $this->assertSame($entity, $repo->save($entity));
        $this->assertSame(9, $entity->getData($idField));
    }
}
