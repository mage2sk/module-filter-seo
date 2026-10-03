<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class MigrateConfigPaths implements DataPatchInterface
{
    private const MIGRATIONS = [
        'panth_seo/filter_urls/' => 'panth_filter_seo/filter_urls/',
        'panth_seo/filter_meta/' => 'panth_filter_seo/filter_meta/',
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');
        foreach (self::MIGRATIONS as $from => $to) {
            $connection->update(
                $table,
                ['path' => new \Zend_Db_Expr(
                    sprintf('REPLACE(path, %s, %s)', $connection->quote($from), $connection->quote($to))
                )],
                $connection->quoteInto('path LIKE ?', $from . '%')
            );
        }
        return $this;
    }

    public static function getDependencies(): array { return []; }
    public function getAliases(): array { return []; }
}
