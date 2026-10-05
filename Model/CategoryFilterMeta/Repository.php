<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\CategoryFilterMeta;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Panth\FilterSeo\Api\CategoryFilterMetaRepositoryInterface;

class Repository implements CategoryFilterMetaRepositoryInterface
{
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    public function getById(int $id)
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('panth_seo_category_filter_meta');
        if (!$connection->isTableExists($table)) {
            return null;
        }
        $row = $connection->fetchRow(
            $connection->select()->from($table)->where('id = ?', $id)
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
        $table = $this->resource->getTableName('panth_seo_category_filter_meta');
        if (!$connection->isTableExists($table)) {
            return $entity;
        }
        $row = array_intersect_key($data, $connection->describeTable($table));
        $id = (int) ($row['id'] ?? 0);
        unset($row['id']);
        if ($id > 0) {
            if ($row !== []) {
                $connection->update($table, $row, ['id = ?' => $id]);
            }
        } else {
            $connection->insert($table, $row);
            $id = (int) $connection->lastInsertId($table);
        }
        if ($entity instanceof DataObject) {
            $entity->setData('id', $id);
            return $entity;
        }
        $entity['id'] = $id;
        return $entity;
    }

    public function deleteById(int $id): bool
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('panth_seo_category_filter_meta');
        if (!$connection->isTableExists($table)) {
            return false;
        }
        return (bool) $connection->delete($table, ['id = ?' => $id]);
    }
}
