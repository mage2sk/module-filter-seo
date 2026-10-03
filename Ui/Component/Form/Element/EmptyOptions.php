<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Ui\Component\Form\Element;

use Magento\Framework\Data\OptionSourceInterface;

class EmptyOptions implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [];
    }
}
