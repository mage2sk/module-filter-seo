<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Model\FilterUrl;

use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Magento\Catalog\Model\Layer\Filter\Item;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Catalog\Model\Layer\State;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection as AttributeCollection;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Panth\FilterSeo\Model\FilterUrl\ActiveFacetProvider;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class ActiveFacetProviderTest extends TestCase
{
    private function item(string $code, string $value): Item
    {
        $filter = $this->createStub(FilterInterface::class);
        $filter->method('getRequestVar')->willReturn($code);
        $item = $this->createStub(Item::class);
        $item->method('getFilter')->willReturn($filter);
        $item->method('getValueString')->willReturn($value);
        return $item;
    }

    private function resolverWithItems(array $items): LayerResolver
    {
        $state = $this->createStub(State::class);
        $state->method('getFilters')->willReturn($items);
        $layer = $this->createStub(Layer::class);
        $layer->method('getState')->willReturn($state);
        $resolver = $this->createStub(LayerResolver::class);
        $resolver->method('get')->willReturn($layer);
        return $resolver;
    }

    private function failingResolver(): LayerResolver
    {
        $resolver = $this->createStub(LayerResolver::class);
        $resolver->method('get')->willThrowException(new \RuntimeException('no layer'));
        return $resolver;
    }

    private function attributeFactory(array $codes): AttributeCollectionFactory
    {
        $attributes = array_map(static fn($c) => new DataObject(['attribute_code' => $c]), $codes);
        $collection = $this->createStub(AttributeCollection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator($attributes));
        $factory = $this->createStub(AttributeCollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        return $factory;
    }

    private function request(array $params): RequestInterface
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn($k) => $params[$k] ?? null);
        return $request;
    }

    public function testReadsFacetsFromLayerStateSkippingNonFacetsAndEmpties(): void
    {
        $provider = new ActiveFacetProvider(
            $this->resolverWithItems([
                $this->item('color', '5'),
                $this->item('cat', '12'),
                $this->item('size', ''),
                $this->item('', '9'),
                $this->item('price', '10-20'),
            ]),
            $this->request([]),
            $this->attributeFactory([]),
            $this->createStub(LoggerInterface::class)
        );

        $this->assertSame(['color' => '5', 'price' => '10-20'], $provider->getActiveFacets());
        $this->assertSame(2, $provider->countActiveFacets());
    }

    public function testFallsBackToRequestParamsForFilterableAttributes(): void
    {
        $provider = new ActiveFacetProvider(
            $this->failingResolver(),
            $this->request(['color' => 5, 'size' => '', 'material' => 'cotton', 'unrelated' => 'x']),
            $this->attributeFactory(['color', 'size', 'material']),
            $this->createStub(LoggerInterface::class)
        );

        $this->assertSame(['color' => '5', 'material' => 'cotton'], $provider->getActiveFacets());
    }

    public function testEmptyLayerStateAlsoUsesRequestFallback(): void
    {
        $provider = new ActiveFacetProvider(
            $this->resolverWithItems([]),
            $this->request(['color' => '5']),
            $this->attributeFactory(['color']),
            $this->createStub(LoggerInterface::class)
        );

        $this->assertSame(1, $provider->countActiveFacets());
    }

    public function testAttributeLoadFailureIsLoggedAndYieldsNoFacets(): void
    {
        $factory = $this->createStub(AttributeCollectionFactory::class);
        $factory->method('create')->willThrowException(new \RuntimeException('db down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')
            ->with($this->stringContains('filterable attributes'), ['error' => 'db down']);

        $provider = new ActiveFacetProvider($this->failingResolver(), $this->request(['color' => 5]), $factory, $logger);

        $this->assertSame([], $provider->getActiveFacets());
        $this->assertSame(0, $provider->countActiveFacets());
    }

    public function testFilterableAttributesAreLoadedOnce(): void
    {
        $collection = $this->createStub(AttributeCollection::class);
        $collection->method('getIterator')->willReturnCallback(
            static fn() => new \ArrayIterator([new DataObject(['attribute_code' => 'color'])])
        );
        $factory = $this->createMock(AttributeCollectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($collection);

        $provider = new ActiveFacetProvider(
            $this->failingResolver(),
            $this->request(['color' => 5]),
            $factory,
            $this->createStub(LoggerInterface::class)
        );

        $provider->getActiveFacets();
        $this->assertSame(['color' => '5'], $provider->getActiveFacets());
    }
}
