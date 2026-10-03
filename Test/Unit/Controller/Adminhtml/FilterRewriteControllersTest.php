<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Controller\Adminhtml;

use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Panth\FilterSeo\Controller\Adminhtml\FilterRewrite\Delete;
use Panth\FilterSeo\Controller\Adminhtml\FilterRewrite\FormSave;
use Panth\FilterSeo\Controller\Adminhtml\FilterRewrite\Options;
use Panth\FilterSeo\Controller\Adminhtml\FilterRewrite\Save;

class FilterRewriteControllersTest extends ControllerTestCase
{
    private ?array $json = null;

    private function json(): Json
    {
        $json = $this->createStub(Json::class);
        $json->method('setData')->willReturnCallback(function ($data) use ($json) {
            $this->json = $data;
            return $json;
        });
        return $json;
    }

    private function jsonResultFactory(): ResultFactory
    {
        $factory = $this->createStub(ResultFactory::class);
        $factory->method('create')->willReturn($this->json());
        return $factory;
    }

    private static function strings(array $messages): array
    {
        return array_map('strval', $messages);
    }

    public function testInlineSaveRejectsEmptyPayload(): void
    {
        $this->resultFactory = $this->jsonResultFactory();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('update');

        (new Save($this->context(), $this->resource($connection)))->execute();

        $this->assertTrue($this->json['error']);
        $this->assertSame(['Please correct the data sent.'], self::strings($this->json['messages']));
    }

    public function testInlineSaveUpdatesOnlyWhitelistedFields(): void
    {
        $this->resultFactory = $this->jsonResultFactory();
        $this->params = ['items' => [
            '12' => ['rewrite_slug' => 'navy-blue', 'is_active' => '1', 'option_id' => '99', 'evil' => 'x'],
            '13' => 'not-an-array',
            '14' => ['option_id' => '5'],
        ]];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('update')->with(
            'panth_seo_filter_rewrite',
            ['rewrite_slug' => 'navy-blue', 'is_active' => '1'],
            ['rewrite_id = ?' => 12]
        );

        (new Save($this->context(), $this->resource($connection)))->execute();

        $this->assertFalse($this->json['error']);
        $this->assertSame(['Record(s) saved.'], self::strings($this->json['messages']));
    }

    public function testInlineSaveRejectsInvalidSlugsPerRow(): void
    {
        $this->resultFactory = $this->jsonResultFactory();
        $this->params = ['items' => [
            '3' => ['rewrite_slug' => 'bad slug/..'],
            '4' => ['rewrite_slug' => ['array']],
            '5' => ['rewrite_slug' => 'gruen_blau-2'],
        ]];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('update')
            ->with('panth_seo_filter_rewrite', ['rewrite_slug' => 'gruen_blau-2'], ['rewrite_id = ?' => 5]);

        (new Save($this->context(), $this->resource($connection)))->execute();

        $this->assertTrue($this->json['error']);
        $this->assertCount(2, $this->json['messages']);
        $this->assertStringStartsWith('[ID: 3] ', (string) $this->json['messages'][0]);
        $this->assertStringStartsWith('[ID: 4] ', (string) $this->json['messages'][1]);
    }

    public function testInlineSaveReportsDatabaseFailuresWithoutDetails(): void
    {
        $this->resultFactory = $this->jsonResultFactory();
        $this->params = ['items' => ['7' => ['is_active' => '0']]];
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('update')->willThrowException(new \RuntimeException('SQLSTATE'));

        (new Save($this->context(), $this->resource($connection)))->execute();

        $this->assertTrue($this->json['error']);
        $this->assertSame(['[ID: 7] Could not save record.'], self::strings($this->json['messages']));
    }

    public function testFormSaveRejectsInvalidSlug(): void
    {
        $this->post = ['rewrite_id' => '3', 'attribute_code' => 'color', 'rewrite_slug' => 'red shoes'];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('update');

        (new FormSave($this->context(), $this->resource($connection)))->execute();

        $this->assertSame(['*/*/edit', ['id' => 3]], $this->redirect);
        $this->assertStringContainsString('letters, numbers, hyphens and underscores', $this->messages[0][1]);
    }

    public function testFormSaveInsertsNewRowWithDefaults(): void
    {
        $this->post = ['attribute_code' => 'color', 'option_id' => '5', 'option_label' => 'Gruen', 'rewrite_slug' => 'gruen'];
        $this->params = ['back' => '1'];
        $connection = $this->createMock(Mysql::class);
        $connection->expects($this->once())->method('insert')->with('panth_seo_filter_rewrite', [
            'attribute_code' => 'color',
            'option_id' => 5,
            'option_label' => 'Gruen',
            'rewrite_slug' => 'gruen',
            'store_id' => 0,
            'is_active' => 1,
        ]);
        $connection->method('lastInsertId')->willReturn('44');

        (new FormSave($this->context(), $this->resource($connection)))->execute();

        $this->assertSame(['*/*/edit', ['id' => 44]], $this->redirect);
        $this->assertSame([['success', 'Filter rewrite saved.']], $this->messages);
    }

    public function testFormSaveUpdatesAndAcceptsUnicodeSlug(): void
    {
        $this->post = ['rewrite_id' => '8', 'attribute_code' => 'color', 'rewrite_slug' => "gr\u{00FC}n", 'is_active' => '0'];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('update')->with(
            'panth_seo_filter_rewrite',
            $this->callback(static fn($row) => $row['rewrite_slug'] === "gr\u{00FC}n" && $row['is_active'] === 0),
            ['rewrite_id = ?' => 8]
        );

        (new FormSave($this->context(), $this->resource($connection)))->execute();

        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testFormSaveWithoutPostAndOnDuplicate(): void
    {
        (new FormSave($this->context(), $this->resource($this->createStub(AdapterInterface::class))))->execute();
        $this->assertSame(['*/*/', []], $this->redirect);

        $this->post = ['attribute_code' => 'color', 'rewrite_slug' => 'red'];
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('insert')->willThrowException(new DuplicateException('dup'));
        (new FormSave($this->context(), $this->resource($connection)))->execute();

        $this->assertSame(['*/*/edit', ['id' => 0]], $this->redirect);
        $this->assertStringContainsString('already exists', $this->messages[0][1]);
    }

    public function testFormSaveGenericFailure(): void
    {
        $this->post = ['rewrite_id' => '2', 'attribute_code' => 'color', 'rewrite_slug' => 'red'];
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('update')->willThrowException(new \RuntimeException('down'));

        (new FormSave($this->context(), $this->resource($connection)))->execute();

        $this->assertSame([['error', 'The record could not be saved.']], $this->messages);
        $this->assertSame(['*/*/edit', ['id' => 2]], $this->redirect);
    }

    public function testDeleteRemovesByRewriteId(): void
    {
        $this->params = ['id' => '11'];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('delete')->with('panth_seo_filter_rewrite', ['rewrite_id = ?' => 11]);

        (new Delete($this->context(), $this->resource($connection)))->execute();

        $this->assertSame([['success', 'Filter rewrite deleted.']], $this->messages);
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testDeleteWithoutIdDoesNothing(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('delete');

        (new Delete($this->context(), $this->resource($connection)))->execute();

        $this->assertSame([], $this->messages);
    }

    private function options(AttributeOptionManagementInterface $management): Options
    {
        $factory = $this->createStub(JsonFactory::class);
        $factory->method('create')->willReturn($this->json());
        return new Options($this->context(), $factory, $management);
    }

    private function option($value, string $label): AttributeOptionInterface
    {
        $option = $this->createStub(AttributeOptionInterface::class);
        $option->method('getValue')->willReturn($value);
        $option->method('getLabel')->willReturn($label);
        return $option;
    }

    public function testOptionsSanitisesAttributeCodeAndSuggestsSlugs(): void
    {
        $this->params = ['attribute_code' => "co<l>or';"];
        $management = $this->createMock(AttributeOptionManagementInterface::class);
        $management->expects($this->once())->method('getItems')->with('catalog_product', 'color')->willReturn([
            $this->option('', ' '),
            $this->option('5', '  Navy & Gold '),
            $this->option('6', ''),
        ]);

        $this->options($management)->execute();

        $this->assertSame(['options' => [['value' => '5', 'label' => '  Navy & Gold ', 'slug' => 'navy-gold']]], $this->json);
    }

    public function testOptionsTransliteratesNonAsciiSlugSuggestions(): void
    {
        $this->params = ['attribute_code' => 'color'];
        $management = $this->createStub(AttributeOptionManagementInterface::class);
        $management->method('getItems')->willReturn([$this->option('5', "Gr\u{00FC}n Caf\u{00E9}")]);

        $this->options($management)->execute();

        $this->assertSame('grun-cafe', $this->json['options'][0]['slug']);
    }

    public function testOptionsWithoutUsableCodeReturnsEmptyList(): void
    {
        $this->params = ['attribute_code' => '!!!'];
        $management = $this->createMock(AttributeOptionManagementInterface::class);
        $management->expects($this->never())->method('getItems');

        $this->options($management)->execute();

        $this->assertSame(['options' => []], $this->json);
    }

    public function testOptionsReportsLookupError(): void
    {
        $this->params = ['attribute_code' => 'missing'];
        $management = $this->createStub(AttributeOptionManagementInterface::class);
        $management->method('getItems')->willThrowException(new \RuntimeException('no such attribute'));

        $this->options($management)->execute();

        $this->assertSame(['options' => [], 'error' => 'no such attribute'], $this->json);
    }
}
