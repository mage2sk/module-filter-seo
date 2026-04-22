<?php
/**
 * Panth Filter SEO — Store View column for admin grids.
 *
 * @copyright Copyright (c) Panth
 */
declare(strict_types=1);

namespace Panth\FilterSeo\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Renders a scalar `store_id` value as a human-readable store label for the
 * grid listing. Accepts 0 (All Store Views) or any store ID.
 */
class StoreView extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param StoreManagerInterface $storeManager
     * @param array<int, mixed> $components
     * @param array<string, mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly StoreManagerInterface $storeManager,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array<string, mixed> $dataSource
     * @return array<string, mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $fieldName = $this->getData('name') ?: 'store_id';

        foreach ($dataSource['data']['items'] as &$item) {
            $value = $item[$fieldName] ?? null;
            $item[$fieldName] = $this->resolveLabel($value);
        }

        return $dataSource;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function resolveLabel(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $storeId = (int) $value;

        if ($storeId === 0) {
            return (string) __('All Store Views');
        }

        try {
            $store = $this->storeManager->getStore($storeId);
            $website = $this->storeManager->getWebsite($store->getWebsiteId());
            $group = $this->storeManager->getGroup($store->getStoreGroupId());
            return sprintf(
                '%s / %s / %s',
                $website->getName(),
                $group->getName(),
                $store->getName()
            );
        } catch (\Throwable) {
            return (string) __('Unknown');
        }
    }
}
