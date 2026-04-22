<?php
/**
 * Panth Filter SEO — Category Filter Meta actions column.
 *
 * @copyright Copyright (c) Panth
 */
declare(strict_types=1);

namespace Panth\FilterSeo\Ui\Component\Listing\Column;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Listing\Columns\Column;
use Panth\FilterSeo\Helper\Config as SeoConfig;
use Panth\FilterSeo\Model\FilterUrl\RewriteRepository;
use Panth\FilterSeo\Model\FilterUrl\UrlBuilder;

/**
 * Renders Edit / View on Storefront / Delete actions in the Category
 * Filter Meta grid. The "View on Storefront" link opens the category
 * page with the row's filter applied — useful to verify the meta
 * override renders correctly without hand-building the URL.
 */
class FilterMetaActions extends Column
{
    public const URL_PATH_EDIT = 'panth_filterseo/filtermeta/edit';
    public const URL_PATH_DELETE = 'panth_filterseo/filtermeta/delete';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param CategoryRepositoryInterface $categoryRepository
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param RewriteRepository $rewriteRepository
     * @param array<int,mixed> $components
     * @param array<string,mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly RewriteRepository $rewriteRepository,
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
            $id = $item['id'] ?? null;
            if ($id === null) {
                continue;
            }

            $item[$name]['edit'] = [
                'href' => $this->urlBuilder->getUrl(self::URL_PATH_EDIT, ['id' => $id]),
                'label' => (string) __('Edit'),
            ];

            $viewUrl = $this->buildFrontendUrl($item);
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
                    'title' => (string) __('Delete filter meta'),
                    'message' => (string) __('Are you sure you want to delete this filter meta record?'),
                ],
            ];
        }

        return $dataSource;
    }

    /**
     * Build the storefront URL for a filter-meta row. Uses the module's
     * own UrlBuilder format + separator settings when clean URLs are
     * enabled; falls back to the native query-string URL otherwise.
     *
     * @param array<string,mixed> $row
     * @return string Empty when the category cannot be resolved.
     */
    private function buildFrontendUrl(array $row): string
    {
        $categoryId = (int) ($row['category_id'] ?? 0);
        $attributeCode = (string) ($row['attribute_code'] ?? '');
        $optionId = (int) ($row['option_id'] ?? 0);

        if ($categoryId === 0 || $attributeCode === '' || $optionId === 0) {
            return '';
        }

        $storeId = (int) ($row['store_id'] ?? 0);
        if ($storeId === 0) {
            try {
                $default = $this->storeManager->getDefaultStoreView();
                $storeId = $default !== null ? (int) $default->getId() : 1;
            } catch (\Throwable) {
                $storeId = 1;
            }
        }

        try {
            $category = $this->categoryRepository->get($categoryId, $storeId);
        } catch (NoSuchEntityException) {
            return '';
        }

        $categoryUrl = (string) $category->getUrl();
        if ($categoryUrl === '') {
            return '';
        }

        $filterUrlsEnabled = $this->scopeConfig->isSetFlag(
            SeoConfig::XML_FILTER_URL_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (!$filterUrlsEnabled) {
            return sprintf(
                '%s%s%s=%d',
                $categoryUrl,
                str_contains($categoryUrl, '?') ? '&' : '?',
                rawurlencode($attributeCode),
                $optionId
            );
        }

        $slug = $this->rewriteRepository->getSlug($attributeCode, $optionId, $storeId);
        if ($slug === null || $slug === '') {
            return $categoryUrl;
        }

        $format = (string) $this->scopeConfig->getValue(
            SeoConfig::XML_FILTER_URL_FORMAT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $separator = (string) $this->scopeConfig->getValue(
            SeoConfig::XML_FILTER_URL_SEPARATOR,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($separator === '') {
            $separator = '-';
        }

        $segment = $format === UrlBuilder::FORMAT_LONG
            ? $attributeCode . '/' . $slug
            : $attributeCode . $separator . $slug;

        // Insert the filter segment before the URL suffix (e.g. ".html").
        if (preg_match('/^(.*\/)([^\/]+?)(\.[a-z0-9]+)?$/i', $categoryUrl, $m)) {
            $prefix = $m[1];
            $base = $m[2];
            $suffix = $m[3] ?? '';
            return $prefix . $base . '/' . $segment . $suffix;
        }

        return rtrim($categoryUrl, '/') . '/' . $segment;
    }
}
