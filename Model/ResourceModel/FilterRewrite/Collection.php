<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\ResourceModel\FilterRewrite;

use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\FilterSeo\Model\FilterUrl\FilterRewrite as FilterRewriteModel;
use Panth\FilterSeo\Model\ResourceModel\FilterRewrite as FilterRewriteResource;

class Collection extends AbstractCollection implements SearchResultInterface
{
    protected $_idFieldName = 'rewrite_id';

    private ?AggregationInterface $aggregations = null;

    protected function _construct(): void
    {
        $this->_init(FilterRewriteModel::class, FilterRewriteResource::class);
    }

    public function getAggregations()
    {
        return $this->aggregations;
    }

    public function setAggregations($aggregations)
    {
        $this->aggregations = $aggregations;
        return $this;
    }

    public function getSearchCriteria()
    {
        return null;
    }

    public function setSearchCriteria(?SearchCriteriaInterface $searchCriteria = null)
    {
        return $this;
    }

    public function getTotalCount()
    {
        return $this->getSize();
    }

    public function setTotalCount($totalCount)
    {
        return $this;
    }

    public function setItems(?array $items = null)
    {
        return $this;
    }
}
