<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Model\FilterUrl;

use Magento\Framework\DataObject;
use Panth\FilterSeo\Model\FilterUrl\RewriteRepository;
use Panth\FilterSeo\Model\ResourceModel\FilterRewrite\Collection;
use Panth\FilterSeo\Model\ResourceModel\FilterRewrite\CollectionFactory;
use PHPUnit\Framework\TestCase;

class RewriteRepositoryTest extends TestCase
{
    private static function row(string $code, int $optionId, string $slug, int $storeId = 0): DataObject
    {
        return new DataObject([
            'attribute_code' => $code,
            'option_id'      => $optionId,
            'rewrite_slug'   => $slug,
            'store_id'       => $storeId,
        ]);
    }

    private function collection(array $rows, array &$filters = []): Collection
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(
            function ($field, $condition) use (&$filters, $collection) {
                $filters[$field] = $condition;
                return $collection;
            }
        );
        $collection->method('getIterator')->willReturn(new \ArrayIterator($rows));
        return $collection;
    }

    public function testLooksUpSlugsInBothDirections(): void
    {
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($this->collection([
            self::row('color', 5, 'red'),
            self::row('size', 7, 'xl'),
        ]));
        $repo = new RewriteRepository($factory);

        $this->assertSame('red', $repo->getSlug('color', 5, 1));
        $this->assertSame(['size', 7], $repo->getBySlug('xl', 1));
        $this->assertSame(7, $repo->getOptionIdBySlug('size', 'xl', 1));
        $this->assertNull($repo->getSlug('color', 6, 1));
        $this->assertNull($repo->getBySlug('blue', 1));
        $this->assertNull($repo->getOptionIdBySlug('color', 'xl', 1));
    }

    public function testFiltersActiveRowsForGlobalAndRequestedStore(): void
    {
        $filters = [];
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($this->collection([], $filters));

        (new RewriteRepository($factory))->getSlug('color', 5, 3);

        $this->assertSame(1, $filters['is_active']);
        $this->assertSame(['in' => [0, 3]], $filters['store_id']);
    }

    public function testStoreSpecificRowOverridesGlobalRow(): void
    {
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($this->collection([
            self::row('color', 5, 'red', 0),
            self::row('color', 5, 'rouge', 2),
        ]));

        $this->assertSame('rouge', (new RewriteRepository($factory))->getSlug('color', 5, 2));
    }

    public function testPreloadsOncePerStore(): void
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(fn() => $this->collection([self::row('color', 5, 'red')]));
        $repo = new RewriteRepository($factory);

        $repo->getSlug('color', 5, 1);
        $repo->getBySlug('red', 1);
        $repo->getOptionIdBySlug('color', 'red', 1);
        $repo->getSlug('color', 5, 2);
    }

    public function testResetForcesReload(): void
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(fn() => $this->collection([self::row('color', 5, 'red')]));
        $repo = new RewriteRepository($factory);

        $repo->getSlug('color', 5, 1);
        $repo->reset();
        $this->assertSame('red', $repo->getSlug('color', 5, 1));
    }
}
