<?php
/**
 * Panth Filter SEO — category source for admin forms.
 *
 * @copyright Copyright (c) Panth
 */
declare(strict_types=1);

namespace Panth\FilterSeo\Model\Config\Source;

use Magento\Catalog\Api\CategoryListInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Returns active non-root categories as dropdown options for the
 * Category Filter Meta admin form.
 */
class Categories implements OptionSourceInterface
{
    /**
     * @param CategoryListInterface $categoryList
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        private readonly CategoryListInterface $categoryList,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * @return array<int,array{value:string,label:string}>
     */
    public function toOptionArray(): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('is_active', 1)
            ->addFilter('level', 1, 'gt')
            ->create();

        try {
            $list = $this->categoryList->getList($searchCriteria);
        } catch (\Throwable) {
            return [];
        }

        $options = [];
        foreach ($list->getItems() as $category) {
            $name = (string) $category->getName();
            $id = (string) $category->getId();
            if ($name === '' || $id === '') {
                continue;
            }
            $options[] = [
                'value' => $id,
                'label' => sprintf('%s (%s)', $name, $id),
            ];
        }

        usort($options, static fn($a, $b) => strcasecmp($a['label'], $b['label']));

        return $options;
    }
}
