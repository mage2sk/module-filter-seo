<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class CategoryFilterMeta extends AbstractDb
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('panth_seo_category_filter_meta', 'id');
    }
}
