<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\ResourceModel\CategoryFilterMeta\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

class Collection extends SearchResult
{
    /**
     * @return static
     */
    protected function _initSelect(): static
    {
        parent::_initSelect();
        return $this;
    }
}
