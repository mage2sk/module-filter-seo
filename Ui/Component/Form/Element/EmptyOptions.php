<?php
/**
 * Panth Filter SEO — placeholder options source used until JS populates
 * the option-picker select from the chosen attribute.
 *
 * @copyright Copyright (c) Panth
 */
declare(strict_types=1);

namespace Panth\FilterSeo\Ui\Component\Form\Element;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Empty options list. The option-picker JS component replaces the server-side
 * options with attribute-specific ones as soon as the user selects an attribute.
 */
class EmptyOptions implements OptionSourceInterface
{
    /**
     * @return array<int,array{value:string,label:string}>
     */
    public function toOptionArray(): array
    {
        return [];
    }
}
