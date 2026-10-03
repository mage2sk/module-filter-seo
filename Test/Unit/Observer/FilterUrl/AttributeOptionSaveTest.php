<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Observer\FilterUrl;

use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Panth\FilterSeo\Helper\Config;
use Panth\FilterSeo\Observer\FilterUrl\AttributeOptionSave;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class AttributeOptionSaveTest extends TestCase
{
    private function observer(?DataObject $attribute): Observer
    {
        return new Observer(['event' => new Event(['attribute' => $attribute])]);
    }

    private function option($value, string $label): AttributeOptionInterface
    {
        $option = $this->createStub(AttributeOptionInterface::class);
        $option->method('getValue')->willReturn($value);
        $option->method('getLabel')->willReturn($label);
        return $option;
    }

    private function config(bool $enabled = true): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($enabled);
        return $config;
    }

    private function connection(array $existingOptionIds, array &$inserts): AdapterInterface
    {
        $ids = new \SplObjectStorage();
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturnCallback(function () use ($ids) {
            $select = $this->createStub(Select::class);
            $select->method('from')->willReturnSelf();
            $select->method('limit')->willReturnSelf();
            $select->method('where')->willReturnCallback(function ($cond, $value = null) use ($select, $ids) {
                if ($cond === 'option_id = ?') {
                    $ids[$select] = $value;
                }
                return $select;
            });
            return $select;
        });
        $connection->method('fetchOne')->willReturnCallback(
            static fn($select) => in_array($ids[$select], $existingOptionIds, true) ? '1' : false
        );
        $connection->method('insert')->willReturnCallback(function ($table, $row) use (&$inserts) {
            $inserts[] = [$table, $row];
            return 1;
        });
        return $connection;
    }

    private function resource(AdapterInterface $connection): ResourceConnection
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return $resource;
    }

    public function testInsertsSlugsForNewOptionsOnly(): void
    {
        $inserts = [];
        $options = $this->createMock(AttributeOptionManagementInterface::class);
        $options->expects($this->once())->method('getItems')->with('catalog_product', 'color')->willReturn([
            $this->option('', 'Admin placeholder'),
            $this->option('5', 'Navy Blue!'),
            $this->option('6', 'Existing'),
            $this->option('7', ''),
            $this->option('8', '###'),
            $this->option('9', '  Light   Grey  '),
        ]);

        (new AttributeOptionSave(
            $this->resource($this->connection([6], $inserts)),
            $options,
            $this->createStub(LoggerInterface::class),
            $this->config()
        ))->execute($this->observer(new DataObject(['frontend_input' => 'select', 'attribute_code' => 'color'])));

        $this->assertCount(2, $inserts);
        $this->assertSame('panth_seo_filter_rewrite', $inserts[0][0]);
        $this->assertSame([
            'attribute_code' => 'color',
            'option_id'      => 5,
            'option_label'   => 'Navy Blue!',
            'rewrite_slug'   => 'navy-blue',
            'store_id'       => 0,
            'is_active'      => 1,
        ], $inserts[0][1]);
        $this->assertSame('light-grey', $inserts[1][1]['rewrite_slug']);
    }

    public function testSkipsNonSelectAttributes(): void
    {
        $options = $this->createMock(AttributeOptionManagementInterface::class);
        $options->expects($this->never())->method('getItems');

        (new AttributeOptionSave(
            $this->createStub(ResourceConnection::class),
            $options,
            $this->createStub(LoggerInterface::class),
            $this->config()
        ))->execute($this->observer(new DataObject(['frontend_input' => 'text', 'attribute_code' => 'name'])));
    }

    public function testSkipsWhenNoAttributeOrDisabled(): void
    {
        $options = $this->createMock(AttributeOptionManagementInterface::class);
        $options->expects($this->never())->method('getItems');
        $logger = $this->createStub(LoggerInterface::class);

        (new AttributeOptionSave($this->createStub(ResourceConnection::class), $options, $logger, $this->config()))
            ->execute($this->observer(null));
        (new AttributeOptionSave($this->createStub(ResourceConnection::class), $options, $logger, $this->config(false)))
            ->execute($this->observer(new DataObject(['frontend_input' => 'select', 'attribute_code' => 'color'])));
    }

    public function testNoOptionsMeansNoDatabaseAccess(): void
    {
        $options = $this->createStub(AttributeOptionManagementInterface::class);
        $options->method('getItems')->willReturn([]);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects($this->never())->method('getConnection');

        (new AttributeOptionSave($resource, $options, $this->createStub(LoggerInterface::class), $this->config()))
            ->execute($this->observer(new DataObject(['frontend_input' => 'multiselect', 'attribute_code' => 'tags'])));
    }

    public function testFailuresAreLoggedNotThrown(): void
    {
        $options = $this->createStub(AttributeOptionManagementInterface::class);
        $options->method('getItems')->willThrowException(new \RuntimeException('eav down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')
            ->with($this->stringContains('auto-generate'), ['exception' => 'eav down']);

        (new AttributeOptionSave($this->createStub(ResourceConnection::class), $options, $logger, $this->config()))
            ->execute($this->observer(new DataObject(['frontend_input' => 'select', 'attribute_code' => 'color'])));
    }

    public function testNonAsciiLabelsAreTransliterated(): void
    {
        $inserts = [];
        $options = $this->createStub(AttributeOptionManagementInterface::class);
        $options->method('getItems')->willReturn([
            $this->option('5', "Gr\u{00FC}n Caf\u{00E9}"),
            $this->option('6', "\u{0416}\u{0451}\u{043B}\u{0442}\u{044B}\u{0439}"),
        ]);

        (new AttributeOptionSave(
            $this->resource($this->connection([], $inserts)),
            $options,
            $this->createStub(LoggerInterface::class),
            $this->config()
        ))->execute($this->observer(new DataObject(['frontend_input' => 'select', 'attribute_code' => 'color'])));

        $this->assertCount(2, $inserts);
        $this->assertSame('grun-cafe', $inserts[0][1]['rewrite_slug']);
        $this->assertSame('zeltyj', $inserts[1][1]['rewrite_slug']);
    }
}
