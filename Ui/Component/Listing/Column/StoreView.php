<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class StoreView extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly StoreManagerInterface $storeManager,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

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
