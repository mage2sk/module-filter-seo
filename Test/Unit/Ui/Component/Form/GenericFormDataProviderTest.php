<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Ui\Component\Form;

use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\FilterSeo\Ui\Component\Form\DataProvider\GenericFormDataProvider;
use PHPUnit\Framework\TestCase;

class GenericFormDataProviderTest extends TestCase
{
    private function provider(
        array $items,
        array $meta = [],
        ?AbstractCollection $collection = null,
        ?DataPersistorInterface $persistor = null
    ): GenericFormDataProvider {
        if ($collection === null) {
            $collection = $this->createStub(AbstractCollection::class);
            $collection->method('getItems')->willReturn($items);
        }
        $url = $this->createStub(BackendUrl::class);
        $url->method('getUrl')->willReturnCallback(static fn($route) => 'https://admin.test/' . $route);

        return new GenericFormDataProvider(
            'form_source',
            'rewrite_id',
            'id',
            $collection,
            $meta,
            [],
            $url,
            $persistor ?? $this->createStub(DataPersistorInterface::class)
        );
    }

    public function testDataIsKeyedByItemId(): void
    {
        $data = $this->provider([
            new DataObject(['id' => 4, 'attribute_code' => 'color']),
            new DataObject(['id' => 9, 'attribute_code' => 'size']),
        ])->getData();

        $this->assertSame([
            4 => ['id' => 4, 'attribute_code' => 'color'],
            9 => ['id' => 9, 'attribute_code' => 'size'],
        ], $data);
    }

    public function testEmptyCollectionYieldsBlankRecordForNewForm(): void
    {
        $this->assertSame(['' => []], $this->provider([])->getData());
    }

    public function testDataIsLoadedOnlyOnce(): void
    {
        $collection = $this->createMock(AbstractCollection::class);
        $collection->expects($this->once())->method('getItems')->willReturn([new DataObject(['id' => 1])]);

        $provider = $this->provider([], [], $collection);
        $provider->getData();

        $this->assertSame([1 => ['id' => 1]], $provider->getData());
    }

    public function testMetaInjectsOptionsEndpointIntoEveryFieldset(): void
    {
        $meta = $this->provider([], ['general' => ['children' => []], 'seo' => []])->getMeta();

        foreach (['general', 'seo'] as $fieldset) {
            $this->assertSame(
                'https://admin.test/panth_filterseo/filterrewrite/options',
                $meta[$fieldset]['children']['option_id']['arguments']['data']['config']['optionsEndpoint']
            );
        }
    }

    public function testPersistedInputReplacesTheRecordOnceAndIsCleared(): void
    {
        $persistor = $this->createMock(DataPersistorInterface::class);
        $persistor->method('get')->with('form_source')->willReturn(['rewrite_id' => '7', 'rewrite_slug' => 'bad slug']);
        $persistor->expects($this->once())->method('clear')->with('form_source');

        $data = $this->provider([new DataObject(['id' => 7, 'rewrite_slug' => 'red'])], [], null, $persistor)->getData();

        $this->assertSame([7 => ['rewrite_id' => '7', 'rewrite_slug' => 'bad slug']], $data);
    }

    public function testPersistedInputForNewRecordIsServedForBothNewAndZeroIds(): void
    {
        $persistor = $this->createStub(DataPersistorInterface::class);
        $persistor->method('get')->willReturn(['rewrite_id' => '', 'rewrite_slug' => 'x y']);

        $data = $this->provider([], [], null, $persistor)->getData();

        $this->assertSame(['rewrite_id' => '', 'rewrite_slug' => 'x y'], $data[0]);
        $this->assertSame(['rewrite_id' => '', 'rewrite_slug' => 'x y'], $data['']);
    }
}
