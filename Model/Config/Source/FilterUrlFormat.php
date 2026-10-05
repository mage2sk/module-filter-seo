<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FilterUrlFormat implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'short', 'label' => __('Short (e.g. /category/color-red-size-xl.html)')],
            ['value' => 'long',  'label' => __('Long (e.g. /category/color/red/size/xl.html)')],
        ];
    }
}
