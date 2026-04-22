<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class FilterRewrite extends AbstractDb
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('panth_seo_filter_rewrite', 'rewrite_id');
    }
}
