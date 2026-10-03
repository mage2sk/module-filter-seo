<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Model\FilterMeta;

use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Magento\Catalog\Model\Layer\Filter\Item;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Catalog\Model\Layer\State;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute as EavAttribute;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection as AttributeCollection;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Page\Title;
use Magento\Theme\Block\Html\Pager;
use Panth\FilterSeo\Helper\Config;
use Panth\FilterSeo\Model\FilterMeta\MetaInjector;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class MetaInjectorTest extends TestCase
{
    private ?string $title = 'Tops';
    private string $description = '';
    private ?string $keywords = null;

    private function item(string $code, string $value, string $name = '', string $label = ''): Item
    {
        $filter = $this->createStub(FilterInterface::class);
        $filter->method('getRequestVar')->willReturn($code);
        $filter->method('getName')->willReturn($name);
        return new Item(
            $this->createStub(UrlInterface::class),
            $this->createStub(Pager::class),
            ['filter' => $filter, 'value' => $value, 'label' => $label]
        );
    }

    private function layerResolver(?array $items): LayerResolver
    {
        $resolver = $this->createStub(LayerResolver::class);
        if ($items === null) {
            $resolver->method('get')->willThrowException(new \RuntimeException('no layer'));
            return $resolver;
        }
        $state = $this->createStub(State::class);
        $state->method('getFilters')->willReturn($items);
        $layer = $this->createStub(Layer::class);
        $layer->method('getState')->willReturn($state);
        $resolver->method('get')->willReturn($layer);
        return $resolver;
    }

    /**
     * @param array<string, array|false|\Throwable> $rowsByCode
     */
    private function resource(array $rowsByCode, array &$queried = []): ResourceConnection
    {
        $codes = new \SplObjectStorage();
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturnCallback(function () use ($codes) {
            $select = $this->createStub(Select::class);
            $select->method('from')->willReturnSelf();
            $select->method('order')->willReturnSelf();
            $select->method('limit')->willReturnSelf();
            $select->method('where')->willReturnCallback(function ($cond, $value = null) use ($select, $codes) {
                if ($cond === 'attribute_code = ?') {
                    $codes[$select] = $value;
                }
                return $select;
            });
            return $select;
        });
        $connection->method('fetchRow')->willReturnCallback(function ($select) use ($rowsByCode, &$queried, $codes) {
            $code = isset($codes[$select]) ? $codes[$select] : '';
            $queried[] = $code;
            $row = $rowsByCode[$code] ?? false;
            if ($row instanceof \Throwable) {
                throw $row;
            }
            return $row;
        });
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return $resource;
    }

    private function pageConfig(): PageConfig
    {
        $title = $this->createStub(Title::class);
        $title->method('set')->willReturnCallback(function ($value) {
            $this->title = (string) $value;
        });
        $title->method('getShort')->willReturnCallback(fn() => $this->title);

        $page = $this->createStub(PageConfig::class);
        $page->method('getTitle')->willReturn($title);
        $page->method('setDescription')->willReturnCallback(function ($value) {
            $this->description = (string) $value;
        });
        $page->method('getDescription')->willReturnCallback(fn() => $this->description);
        $page->method('setKeywords')->willReturnCallback(function ($value) {
            $this->keywords = (string) $value;
        });
        return $page;
    }

    private function injector(
        LayerResolver $layerResolver,
        ResourceConnection $resource,
        array $flags = [],
        array $request = [],
        array $filterableCodes = [],
        ?EavConfig $eavConfig = null,
        ?LoggerInterface $logger = null
    ): MetaInjector {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(static fn($path) => (bool) ($flags[$path] ?? false));

        $requestStub = $this->createStub(RequestInterface::class);
        $requestStub->method('getParam')->willReturnCallback(static fn($k) => $request[$k] ?? null);

        $collection = $this->createStub(AttributeCollection::class);
        $collection->method('getIterator')->willReturnCallback(static fn() => new \ArrayIterator(
            array_map(static fn($c) => new DataObject(['attribute_code' => $c]), $filterableCodes)
        ));
        $factory = $this->createStub(AttributeCollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return new MetaInjector(
            $resource,
            $requestStub,
            $scopeConfig,
            $layerResolver,
            $logger ?? $this->createStub(LoggerInterface::class),
            $factory,
            $eavConfig ?? $this->createStub(EavConfig::class)
        );
    }

    public function testDoesNothingWithoutActiveFilters(): void
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects($this->never())->method('getConnection');

        $this->injector($this->layerResolver([]), $resource)->inject($this->pageConfig(), 3, 1);

        $this->assertSame('Tops', $this->title);
    }

    public function testAppliesStoredMetaFromMatchingRow(): void
    {
        $resource = $this->resource([
            'color' => ['meta_title' => 'Red Tops', 'meta_description' => 'All red tops', 'meta_keywords' => 'red,tops'],
        ]);

        $this->injector(
            $this->layerResolver([$this->item('color', '5', 'Color', 'Red')]),
            $resource,
            [Config::XML_FILTER_META_INJECT_TITLE => true, Config::XML_FILTER_META_INJECT_DESCRIPTION => true]
        )->inject($this->pageConfig(), 3, 1);

        $this->assertSame('Red Tops', $this->title);
        $this->assertSame('All red tops', $this->description);
        $this->assertSame('red,tops', $this->keywords);
    }

    public function testLaterFilterRowOverridesEarlierAndEmptyFieldsAreIgnored(): void
    {
        $resource = $this->resource([
            'color' => ['meta_title' => 'Red Tops', 'meta_description' => 'Color desc', 'meta_keywords' => ''],
            'size'  => ['meta_title' => 'XL Tops', 'meta_description' => '', 'meta_keywords' => null],
        ]);

        $this->injector(
            $this->layerResolver([$this->item('color', '5'), $this->item('size', '7')]),
            $resource
        )->inject($this->pageConfig(), 3, 1);

        $this->assertSame('XL Tops', $this->title);
        $this->assertSame('Color desc', $this->description);
        $this->assertNull($this->keywords);
    }

    public function testAppendsFilterLabelsToTitleAndDescriptionWhenNoRowMatches(): void
    {
        $this->description = 'Shop tops';

        $this->injector(
            $this->layerResolver([
                $this->item('color', '5', 'Color', '<b>Red</b>'),
                $this->item('size', '7', 'Size', 'XL'),
            ]),
            $this->resource([]),
            [Config::XML_FILTER_META_INJECT_TITLE => true, Config::XML_FILTER_META_INJECT_DESCRIPTION => true]
        )->inject($this->pageConfig(), 3, 1);

        $this->assertSame('Tops | Color: Red, Size: XL', $this->title);
        $this->assertSame('Shop tops | Color: Red, Size: XL', $this->description);
    }

    public function testDescriptionAppendIsIdempotentAcrossRepeatedInjection(): void
    {
        $injector = $this->injector(
            $this->layerResolver([$this->item('color', '5', 'Color', 'Red')]),
            $this->resource([]),
            [Config::XML_FILTER_META_INJECT_DESCRIPTION => true]
        );
        $page = $this->pageConfig();

        $injector->inject($page, 3, 1);
        $injector->inject($page, 3, 1);

        $this->assertSame('Color: Red', $this->description);
        $this->assertSame('Tops', $this->title);
    }

    public function testNoAppendWhenInjectionFlagsAreOff(): void
    {
        $this->injector(
            $this->layerResolver([$this->item('color', '5', 'Color', 'Red')]),
            $this->resource([])
        )->inject($this->pageConfig(), 3, 1);

        $this->assertSame('Tops', $this->title);
        $this->assertSame('', $this->description);
    }

    public function testQueryFailureIsLoggedAndOtherFiltersStillApply(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')
            ->with($this->stringContains('filter meta'), ['error' => 'boom']);

        $this->injector(
            $this->layerResolver([$this->item('color', '5'), $this->item('size', '7')]),
            $this->resource(['color' => new \RuntimeException('boom'), 'size' => ['meta_title' => 'XL']]),
            [],
            [],
            [],
            null,
            $logger
        )->inject($this->pageConfig(), 3, 1);

        $this->assertSame('XL', $this->title);
    }

    public function testRequestFallbackResolvesAttributeAndOptionLabels(): void
    {
        $source = $this->createStub(AbstractSource::class);
        $source->method('getOptionText')->willReturnCallback(
            static fn($id) => ['5' => '<i>Navy &amp; Gold</i>', '6' => 'Teal'][$id] ?? false
        );
        $attribute = $this->createStub(EavAttribute::class);
        $attribute->method('getId')->willReturn(93);
        $attribute->method('getStoreLabel')->willReturn('Colour');
        $attribute->method('usesSource')->willReturn(true);
        $attribute->method('getSource')->willReturn($source);
        $eav = $this->createStub(EavConfig::class);
        $eav->method('getAttribute')->willReturn($attribute);

        $queried = [];
        $this->injector(
            $this->layerResolver(null),
            $this->resource([], $queried),
            [Config::XML_FILTER_META_INJECT_TITLE => true],
            ['color' => '5,6', 'size' => ''],
            ['color', 'size'],
            $eav
        )->inject($this->pageConfig(), 3, 1);

        $this->assertSame(['color'], $queried);
        $this->assertSame('Tops | Colour: Navy & Gold, Teal', $this->title);
    }

    public function testUnknownAttributeFallsBackToCodeAndRawValue(): void
    {
        $eav = $this->createStub(EavConfig::class);
        $eav->method('getAttribute')->willReturn(false);

        $this->injector(
            $this->layerResolver(null),
            $this->resource([]),
            [Config::XML_FILTER_META_INJECT_TITLE => true],
            ['material' => '12'],
            ['material'],
            $eav
        )->inject($this->pageConfig(), 3, 1);

        $this->assertSame('Tops | Material: 12', $this->title);
    }
}
