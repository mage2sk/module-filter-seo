<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Model\Config\Source;

use Magento\Catalog\Api\CategoryListInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\CategorySearchResultsInterface;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection as AttributeCollection;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\DataObject;
use Panth\FilterSeo\Model\Config\Source\Categories;
use Panth\FilterSeo\Model\Config\Source\FilterableAttributes;
use Panth\FilterSeo\Model\Config\Source\FilterUrlFormat;
use Panth\FilterSeo\Ui\Component\Form\Element\EmptyOptions;
use PHPUnit\Framework\TestCase;

class SourceModelsTest extends TestCase
{
    private function criteriaBuilder(array &$filters = []): SearchCriteriaBuilder
    {
        $builder = $this->createStub(SearchCriteriaBuilder::class);
        $builder->method('addFilter')->willReturnCallback(
            function ($field, $value, $type = 'eq') use (&$filters, &$builder) {
                $filters[] = [$field, $value, $type];
                return $builder;
            }
        );
        $builder->method('create')->willReturn($this->createStub(SearchCriteria::class));
        return $builder;
    }

    private function category(string $name, $id): CategoryInterface
    {
        $category = $this->createStub(CategoryInterface::class);
        $category->method('getName')->willReturn($name);
        $category->method('getId')->willReturn($id);
        return $category;
    }

    public function testCategoriesAreLabelledSortedAndFiltered(): void
    {
        $results = $this->createStub(CategorySearchResultsInterface::class);
        $results->method('getItems')->willReturn([
            $this->category('women', 4),
            $this->category('', 5),
            $this->category('Accessories', 9),
            $this->category('Men', 3),
        ]);
        $list = $this->createStub(CategoryListInterface::class);
        $list->method('getList')->willReturn($results);

        $filters = [];
        $options = (new Categories($list, $this->criteriaBuilder($filters)))->toOptionArray();

        $this->assertSame([
            ['value' => '9', 'label' => 'Accessories (9)'],
            ['value' => '3', 'label' => 'Men (3)'],
            ['value' => '4', 'label' => 'women (4)'],
        ], $options);
        $this->assertSame([['is_active', 1, 'eq'], ['level', 1, 'gt']], $filters);
    }

    public function testCategoriesReturnEmptyWhenListFails(): void
    {
        $list = $this->createStub(CategoryListInterface::class);
        $list->method('getList')->willThrowException(new \RuntimeException('fail'));

        $this->assertSame([], (new Categories($list, $this->criteriaBuilder()))->toOptionArray());
    }

    public function testFilterableAttributesBuildLabelledOptionsOnce(): void
    {
        $collection = $this->createStub(AttributeCollection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([
            new DataObject(['attribute_code' => 'color', 'frontend_label' => 'Color']),
            new DataObject(['attribute_code' => 'shoe_size', 'frontend_label' => null]),
        ]));
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($collection);

        $source = new FilterableAttributes($factory);
        $options = $source->toOptionArray();

        $this->assertCount(3, $options);
        $this->assertSame('', $options[0]['value']);
        $this->assertSame(['value' => 'color', 'label' => 'Color (color)'], $options[1]);
        $this->assertSame(['value' => 'shoe_size', 'label' => 'shoe_size (shoe_size)'], $options[2]);
        $this->assertSame($options, $source->toOptionArray());
    }

    public function testFilterUrlFormatOffersShortAndLong(): void
    {
        $values = array_column((new FilterUrlFormat())->toOptionArray(), 'value');

        $this->assertSame(['short', 'long'], $values);
    }

    public function testEmptyOptionsIsEmpty(): void
    {
        $this->assertSame([], (new EmptyOptions())->toOptionArray());
    }
}
