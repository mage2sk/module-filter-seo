<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Setup\Patch\Data;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Panth\FilterSeo\Setup\Patch\Data\MigrateConfigPaths;
use PHPUnit\Framework\TestCase;

class MigrateConfigPathsTest extends TestCase
{
    public function testRewritesLegacyPathPrefixesInCoreConfigData(): void
    {
        $updates = [];
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('quote')->willReturnCallback(static fn($v) => "'" . $v . "'");
        $connection->method('quoteInto')->willReturnCallback(
            static fn($text, $value) => str_replace('?', "'" . $value . "'", $text)
        );
        $connection->method('update')->willReturnCallback(function ($table, $bind, $where) use (&$updates) {
            $updates[] = [$table, (string) $bind['path'], $where];
            return 1;
        });

        $setup = $this->createStub(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn($t) => 'pfx_' . $t);

        $patch = new MigrateConfigPaths($setup);

        $this->assertSame($patch, $patch->apply());
        $this->assertSame([
            [
                'pfx_core_config_data',
                "REPLACE(path, 'panth_seo/filter_urls/', 'panth_filter_seo/filter_urls/')",
                "path LIKE 'panth_seo/filter_urls/%'",
            ],
            [
                'pfx_core_config_data',
                "REPLACE(path, 'panth_seo/filter_meta/', 'panth_filter_seo/filter_meta/')",
                "path LIKE 'panth_seo/filter_meta/%'",
            ],
        ], $updates);
        $this->assertSame([], MigrateConfigPaths::getDependencies());
        $this->assertSame([], $patch->getAliases());
    }
}
