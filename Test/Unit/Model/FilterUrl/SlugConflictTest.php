<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Model\FilterUrl;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Panth\FilterSeo\Model\FilterUrl\SlugConflict;
use PHPUnit\Framework\TestCase;

class SlugConflictTest extends TestCase
{
    private function conflict(AdapterInterface $connection): SlugConflict
    {
        $connection->method('quoteIdentifier')->willReturnCallback(static fn($name) => '`' . $name . '`');
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return new SlugConflict($resource);
    }

    public function testFindChecksSameAttributeAndSlugForOtherOptionsInOverlappingStores(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('fetchRow')
            ->with(
                $this->callback(static fn($sql) => str_contains($sql, 'FROM `panth_seo_filter_rewrite`')
                    && str_contains($sql, 'attribute_code = ? AND rewrite_slug = ? AND option_id <> ? AND rewrite_id <> ?')
                    && str_contains($sql, '(store_id = ? OR store_id = 0 OR ? = 0)')),
                ['color', 'red', 6, 3, 2, 2]
            )
            ->willReturn(['rewrite_id' => '10', 'option_id' => '58', 'option_label' => 'Red', 'store_id' => '0']);

        $this->assertSame(
            ['rewrite_id' => '10', 'option_id' => '58', 'option_label' => 'Red', 'store_id' => '0'],
            $this->conflict($connection)->find('color', 'red', 6, 2, 3)
        );
    }

    public function testFindReturnsNullWithoutMatchOrInput(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('fetchRow')->willReturn(false);
        $conflict = $this->conflict($connection);

        $this->assertNull($conflict->find('color', 'red', 6, 0));
        $this->assertNull($conflict->find('', 'red', 6, 0));
        $this->assertNull($conflict->find('color', '', 6, 0));
    }

    public function testFindForRowUsesTheStoredAttributeOptionAndStore(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->exactly(2))->method('fetchRow')
            ->willReturnCallback(static function ($sql, $bind) {
                if (str_contains($sql, 'WHERE rewrite_id = ?')) {
                    return $bind === [9] ? ['attribute_code' => 'size', 'option_id' => '7', 'store_id' => '1'] : false;
                }
                return $bind === ['size', 'xl', 7, 9, 1, 1] ? ['rewrite_id' => '4', 'option_label' => 'XL'] : false;
            });

        $this->assertSame(['rewrite_id' => '4', 'option_label' => 'XL'], $this->conflict($connection)->findForRow(9, 'xl'));
    }

    public function testFindForMissingRowIsNull(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('fetchRow')->willReturn([]);

        $this->assertNull($this->conflict($connection)->findForRow(404, 'xl'));
    }
}
