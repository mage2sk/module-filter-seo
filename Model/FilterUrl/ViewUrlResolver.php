<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\FilterUrl;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\FilterSeo\Helper\Config as SeoConfig;

/**
 * Resolves a storefront URL that applies a single attribute+option filter.
 *
 * Used by the admin grids and edit-page View buttons so an admin can jump
 * from a Filter Meta / Filter Rewrite record to the corresponding filtered
 * category page on the storefront.
 */
class ViewUrlResolver
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlBuilder $urlBuilder,
        private readonly AttributeRepositoryInterface $attributeRepository
    ) {
    }

    /**
     * Resolve URL when the category is known (Filter Meta).
     */
    public function resolveForCategory(int $categoryId, string $attributeCode, int $optionId, int $storeId): string
    {
        if ($categoryId === 0 || $attributeCode === '' || $optionId === 0) {
            return '';
        }

        $storeId = $this->resolveStoreId($storeId);

        return $this->buildUrl($categoryId, $attributeCode, $optionId, $storeId);
    }

    /**
     * Resolve URL without a known category (Filter Rewrite). Finds a category
     * that either has a Filter Meta record for this filter or contains at
     * least one product matching the attribute option.
     */
    public function resolveWithoutCategory(string $attributeCode, int $optionId, int $storeId): string
    {
        if ($attributeCode === '' || $optionId === 0) {
            return '';
        }

        $storeId = $this->resolveStoreId($storeId);

        $categoryId = $this->findCategoryViaFilterMeta($attributeCode, $optionId)
            ?? $this->findCategoryViaProductIndex($attributeCode, $optionId, $storeId);

        if ($categoryId === null) {
            return '';
        }

        return $this->buildUrl($categoryId, $attributeCode, $optionId, $storeId);
    }

    private function buildUrl(int $categoryId, string $attributeCode, int $optionId, int $storeId): string
    {
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

        return $this->urlBuilder->build(
            $categoryUrl,
            [['attribute_code' => $attributeCode, 'option_id' => $optionId]],
            $storeId
        );
    }

    private function resolveStoreId(int $storeId): int
    {
        if ($storeId !== 0) {
            return $storeId;
        }

        try {
            $default = $this->storeManager->getDefaultStoreView();
            return $default !== null ? (int) $default->getId() : 1;
        } catch (\Throwable) {
            return 1;
        }
    }

    private function findCategoryViaFilterMeta(string $attributeCode, int $optionId): ?int
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('panth_seo_category_filter_meta');

        $id = $connection->fetchOne(
            $connection->select()
                ->from($table, ['category_id'])
                ->where('attribute_code = ?', $attributeCode)
                ->where('option_id = ?', $optionId)
                ->order('category_id ASC')
                ->limit(1)
        );

        return $id !== false && (int) $id > 0 ? (int) $id : null;
    }

    private function findCategoryViaProductIndex(string $attributeCode, int $optionId, int $storeId): ?int
    {
        try {
            $attribute = $this->attributeRepository->get(
                \Magento\Catalog\Api\Data\ProductAttributeInterface::ENTITY_TYPE_CODE,
                $attributeCode
            );
        } catch (NoSuchEntityException) {
            return null;
        }

        $connection = $this->resource->getConnection();
        $eavIndex = $this->resource->getTableName('catalog_product_index_eav');
        $catProduct = $this->resource->getTableName('catalog_category_product');

        $select = $connection->select()
            ->from(['eav' => $eavIndex], [])
            ->join(
                ['ccp' => $catProduct],
                'ccp.product_id = eav.entity_id',
                ['category_id']
            )
            ->where('eav.attribute_id = ?', (int) $attribute->getAttributeId())
            ->where('eav.value = ?', $optionId)
            ->where('eav.store_id = ?', $storeId)
            ->where('ccp.category_id > ?', 2) // skip root categories
            ->order('ccp.category_id ASC')
            ->limit(1);

        $id = $connection->fetchOne($select);

        return $id !== false && (int) $id > 0 ? (int) $id : null;
    }
}
