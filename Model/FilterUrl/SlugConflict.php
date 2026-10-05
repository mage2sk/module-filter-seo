<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\FilterUrl;

use Magento\Framework\App\ResourceConnection;

class SlugConflict
{
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    public function find(string $attributeCode, string $slug, int $optionId, int $storeId, int $excludeId = 0): ?array
    {
        if ($attributeCode === '' || $slug === '') {
            return null;
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('panth_seo_filter_rewrite');
        $sql = 'SELECT rewrite_id, option_id, option_label, store_id FROM ' . $connection->quoteIdentifier($table)
            . ' WHERE attribute_code = ? AND rewrite_slug = ? AND option_id <> ? AND rewrite_id <> ?'
            . ' AND (store_id = ? OR store_id = 0 OR ? = 0) LIMIT 1';
        $row = $connection->fetchRow($sql, [$attributeCode, $slug, $optionId, $excludeId, $storeId, $storeId]);

        return is_array($row) && $row !== [] ? $row : null;
    }

    public function findForRow(int $rewriteId, string $slug): ?array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('panth_seo_filter_rewrite');
        $row = $connection->fetchRow(
            'SELECT attribute_code, option_id, store_id FROM ' . $connection->quoteIdentifier($table)
            . ' WHERE rewrite_id = ?',
            [$rewriteId]
        );
        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->find(
            (string) $row['attribute_code'],
            $slug,
            (int) $row['option_id'],
            (int) $row['store_id'],
            $rewriteId
        );
    }
}
