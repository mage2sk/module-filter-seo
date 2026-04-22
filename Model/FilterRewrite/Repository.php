<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\FilterRewrite;

use Magento\Framework\App\ResourceConnection;
use Panth\FilterSeo\Api\FilterRewriteRepositoryInterface;

class Repository implements FilterRewriteRepositoryInterface
{
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @param int $id
     * @return array|null
     */
    public function getById(int $id)
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('panth_seo_filter_rewrite');
        if (!$connection->isTableExists($table)) {
            return null;
        }
        $row = $connection->fetchRow(
            $connection->select()->from($table)->where('rewrite_id = ?', $id)
        );
        return $row ?: null;
    }

    /**
     * @param mixed $entity
     * @return mixed
     */
    public function save($entity)
    {
        return $entity;
    }

    /**
     * @param int $id
     * @return bool
     */
    public function deleteById(int $id): bool
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('panth_seo_filter_rewrite');
        if (!$connection->isTableExists($table)) {
            return false;
        }
        return (bool) $connection->delete($table, ['rewrite_id = ?' => $id]);
    }
}
