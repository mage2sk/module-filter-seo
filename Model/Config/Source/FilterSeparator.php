<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FilterSeparator implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '-', 'label' => __('Hyphen (-)')],
            ['value' => '_', 'label' => __('Underscore (_)')],
            ['value' => '~', 'label' => __('Tilde (~)')],
        ];
    }
}
