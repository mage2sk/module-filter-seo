<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\FilterRewrite;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Panth\FilterSeo\Api\FilterRewriteRepositoryInterface;

class Repository implements FilterRewriteRepositoryInterface
{
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

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

    public function save($entity)
    {
        if ($entity instanceof DataObject) {
            $data = $entity->getData();
        } elseif (is_array($entity)) {
            $data = $entity;
        } else {
            return $entity;
        }
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('panth_seo_filter_rewrite');
        if (!$connection->isTableExists($table)) {
            return $entity;
        }
        $row = array_intersect_key($data, $connection->describeTable($table));
        $id = (int) ($row['rewrite_id'] ?? 0);
        unset($row['rewrite_id']);
        if ($id > 0) {
            if ($row !== []) {
                $connection->update($table, $row, ['rewrite_id = ?' => $id]);
            }
        } else {
            $connection->insert($table, $row);
            $id = (int) $connection->lastInsertId($table);
        }
        if ($entity instanceof DataObject) {
            $entity->setData('rewrite_id', $id);
            return $entity;
        }
        $entity['rewrite_id'] = $id;
        return $entity;
    }

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
