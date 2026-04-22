<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\ResourceModel\CategoryFilterMeta;

use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\FilterSeo\Model\FilterMeta\CategoryFilterMeta as CategoryFilterMetaModel;
use Panth\FilterSeo\Model\ResourceModel\CategoryFilterMeta as CategoryFilterMetaResource;

class Collection extends AbstractCollection implements SearchResultInterface
{
    /**
     * @var string
     */
    protected $_idFieldName = 'id';

    /**
     * @var AggregationInterface|null
     */
    private ?AggregationInterface $aggregations = null;

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(CategoryFilterMetaModel::class, CategoryFilterMetaResource::class);
    }

    /**
     * @inheritdoc
     */
    public function getAggregations()
    {
        return $this->aggregations;
    }

    /**
     * @inheritdoc
     */
    public function setAggregations($aggregations)
    {
        $this->aggregations = $aggregations;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSearchCriteria()
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function setSearchCriteria(SearchCriteriaInterface $searchCriteria = null)
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getTotalCount()
    {
        return $this->getSize();
    }

    /**
     * @inheritdoc
     */
    public function setTotalCount($totalCount)
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setItems(array $items = null)
    {
        return $this;
    }
}
