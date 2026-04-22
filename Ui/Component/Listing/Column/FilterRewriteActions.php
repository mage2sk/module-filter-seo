<?php
/**
 * Panth Filter SEO — Filter URL Rewrite actions column.
 *
 * @copyright Copyright (c) Panth
 */
declare(strict_types=1);

namespace Panth\FilterSeo\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Renders Edit / View on Storefront / Delete actions in the Filter URL
 * Rewrite grid. The View action points at the storefront catalog-search
 * result page with the row's attribute + option applied so the admin can
 * preview products that match this filter without needing to know which
 * category the rewrite is used in.
 */
class FilterRewriteActions extends Column
{
    public const URL_PATH_EDIT = 'panth_filterseo/filterRewrite/edit';
    public const URL_PATH_DELETE = 'panth_filterseo/filterRewrite/delete';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param StoreManagerInterface $storeManager
     * @param array<int,mixed> $components
     * @param array<string,mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly StoreManagerInterface $storeManager,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array<string,mixed> $dataSource
     * @return array<string,mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $name = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $id = $item['rewrite_id'] ?? null;
            if ($id === null) {
                continue;
            }

            $item[$name]['edit'] = [
                'href' => $this->urlBuilder->getUrl(self::URL_PATH_EDIT, ['id' => $id]),
                'label' => (string) __('Edit'),
            ];

            $viewUrl = $this->buildSearchUrl($item);
            if ($viewUrl !== '') {
                $item[$name]['view'] = [
                    'href' => $viewUrl,
                    'label' => (string) __('View on Storefront'),
                    'target' => '_blank',
                ];
            }

            $item[$name]['delete'] = [
                'href' => $this->urlBuilder->getUrl(self::URL_PATH_DELETE, ['id' => $id]),
                'label' => (string) __('Delete'),
                'confirm' => [
                    'title' => (string) __('Delete rewrite'),
                    'message' => (string) __('Are you sure you want to delete this filter rewrite?'),
                ],
            ];
        }

        return $dataSource;
    }

    /**
     * Build a storefront catalog-search URL that applies the row's
     * attribute filter. Gives the admin a preview of products matching
     * the rewrite without needing a specific category.
     *
     * @param array<string,mixed> $row
     * @return string Empty when the row is incomplete or the store
     *                cannot be resolved.
     */
    private function buildSearchUrl(array $row): string
    {
        $attributeCode = (string) ($row['attribute_code'] ?? '');
        $optionId = (int) ($row['option_id'] ?? 0);

        if ($attributeCode === '' || $optionId === 0) {
            return '';
        }

        $storeId = (int) ($row['store_id'] ?? 0);

        try {
            $store = $storeId === 0
                ? $this->storeManager->getDefaultStoreView()
                : $this->storeManager->getStore($storeId);
        } catch (\Throwable) {
            return '';
        }

        if ($store === null) {
            return '';
        }

        $baseUrl = (string) $store->getBaseUrl();
        if ($baseUrl === '') {
            return '';
        }

        return sprintf(
            '%scatalogsearch/result/?%s=%d',
            rtrim($baseUrl, '/') . '/',
            rawurlencode($attributeCode),
            $optionId
        );
    }
}
