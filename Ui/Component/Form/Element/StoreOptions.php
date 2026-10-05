<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Ui\Component\Form\Element;

use Magento\Store\Ui\Component\Listing\Column\Store\Options;

class StoreOptions extends Options
{
    public function toOptionArray()
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $storeOptions = parent::toOptionArray();
        $this->options = array_merge(
            [['label' => (string) __('All Store Views'), 'value' => '0']],
            is_array($storeOptions) ? $storeOptions : []
        );

        return $this->options;
    }
}
