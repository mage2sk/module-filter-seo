<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Ui\Component\Listing;

use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Panth\FilterSeo\Ui\Component\Listing\LikeFulltextFilter;
use PHPUnit\Framework\TestCase;

class LikeFulltextFilterTest extends TestCase
{
    private function filter($value): Filter
    {
        $filter = new Filter();
        $filter->setValue($value);
        return $filter;
    }

    private function collection(?string $expectedWhere): AbstractDb
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('quoteIdentifier')->willReturnCallback(static fn($c) => '`' . $c . '`');
        $connection->method('quoteInto')->willReturnCallback(
            static fn($text, $value) => str_replace('?', "'" . $value . "'", $text)
        );

        $select = $this->createMock(Select::class);
        if ($expectedWhere === null) {
            $select->expects($this->never())->method('where');
        } else {
            $select->expects($this->once())->method('where')->with($expectedWhere);
        }

        $collection = $this->createStub(AbstractDb::class);
        $collection->method('getConnection')->willReturn($connection);
        $collection->method('getSelect')->willReturn($select);
        return $collection;
    }

    public function testBuildsOrLikeConditionOverStringColumnsOnly(): void
    {
        $applier = new LikeFulltextFilter(['attribute_code', 42, 'rewrite_slug']);

        $applier->apply(
            $this->collection("`attribute_code` LIKE '%red%' OR `rewrite_slug` LIKE '%red%'"),
            $this->filter('  red ')
        );
    }

    public function testEscapesWildcardsInSearchTerm(): void
    {
        $applier = new LikeFulltextFilter(['option_label']);

        $applier->apply(
            $this->collection("`option_label` LIKE '%50\\%\\_off%'"),
            $this->filter('50%_off')
        );
    }

    public function testTruncatesVeryLongTerms(): void
    {
        $applier = new LikeFulltextFilter(['option_label']);

        $applier->apply(
            $this->collection("`option_label` LIKE '%" . str_repeat('a', 200) . "%'"),
            $this->filter(str_repeat('a', 500))
        );
    }

    public function testIgnoresBlankAndNonScalarValues(): void
    {
        $applier = new LikeFulltextFilter(['option_label']);

        $applier->apply($this->collection(null), $this->filter('   '));
        $applier->apply($this->collection(null), $this->filter(['x']));
    }

    public function testNoColumnsOrNonDbCollectionIsANoOp(): void
    {
        (new LikeFulltextFilter([]))->apply($this->collection(null), $this->filter('red'));

        $plain = $this->createMock(Collection::class);
        $plain->expects($this->never())->method('getIterator');
        (new LikeFulltextFilter(['option_label']))->apply($plain, $this->filter('red'));
    }
}
