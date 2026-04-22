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
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Panth\FilterSeo\Helper\Config as SeoConfig;

/**
 * Resolves a storefront URL that applies a single attribute+option filter.
 *
 * Builds the URL explicitly from the target store's base URL + the
 * store-scoped URL rewrite, rather than relying on Category::getUrl()
 * which carries the load-time store context and produces wrong results
 * from adminhtml. This matters on multi-store setups where each store
 * has its own domain (e.g. hyva.test vs luma.test).
 */
class ViewUrlResolver
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlBuilder $urlBuilder,
        private readonly AttributeRepositoryInterface $attributeRepository,
        private readonly UrlFinderInterface $urlFinder
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

        foreach ($this->targetStoreIds($storeId) as $targetStoreId) {
            $url = $this->buildUrl($categoryId, $attributeCode, $optionId, $targetStoreId);
            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    /**
     * Resolve URL without a known category (Filter Rewrite).
     */
    public function resolveWithoutCategory(string $attributeCode, int $optionId, int $storeId): string
    {
        if ($attributeCode === '' || $optionId === 0) {
            return '';
        }

        foreach ($this->targetStoreIds($storeId) as $targetStoreId) {
            $categoryId = $this->findCategoryViaFilterMeta($attributeCode, $optionId, $targetStoreId)
                ?? $this->findCategoryViaProductIndex($attributeCode, $optionId, $targetStoreId)
                ?? $this->findFirstStorefrontCategory($targetStoreId);

            if ($categoryId === null) {
                continue;
            }

            $url = $this->buildUrl($categoryId, $attributeCode, $optionId, $targetStoreId);
            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    /**
     * Expand a record's store_id into the list of concrete storefront
     * stores to try. For store_id=0 (All Store Views) we try every active
     * non-admin store and pick the first one that can produce a URL.
     *
     * @return int[]
     */
    private function targetStoreIds(int $storeId): array
    {
        if ($storeId !== 0) {
            return [$storeId];
        }

        $ids = [];
        foreach ($this->storeManager->getStores(false) as $store) {
            $ids[] = (int) $store->getId();
        }
        return $ids;
    }

    private function buildUrl(int $categoryId, string $attributeCode, int $optionId, int $storeId): string
    {
        try {
            $store = $this->storeManager->getStore($storeId);
        } catch (NoSuchEntityException) {
            return '';
        }

        // The category must belong to the target store's root tree,
        // otherwise the URL won't route on that store's frontend.
        if (!$this->categoryBelongsToStore($categoryId, (int) $store->getRootCategoryId())) {
            return '';
        }

        $rewrite = $this->urlFinder->findOneByData([
            UrlRewrite::ENTITY_ID => $categoryId,
            UrlRewrite::ENTITY_TYPE => 'category',
            UrlRewrite::STORE_ID => $storeId,
        ]);
        if ($rewrite === null) {
            return '';
        }

        $baseUrl = (string) $store->getBaseUrl();
        $categoryUrl = rtrim($baseUrl, '/') . '/' . ltrim($rewrite->getRequestPath(), '/');

        $filterUrlsEnabled = $this->scopeConfig->isSetFlag(
            SeoConfig::XML_FILTER_URL_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($filterUrlsEnabled) {
            $cleanUrl = $this->urlBuilder->build(
                $categoryUrl,
                [['attribute_code' => $attributeCode, 'option_id' => $optionId]],
                $storeId
            );
            // UrlBuilder returns the unchanged category URL when no slug
            // is defined for this store/attribute/option. In that case
            // fall through to the native query-string form so the filter
            // still applies on the storefront.
            if ($cleanUrl !== $categoryUrl) {
                return $cleanUrl;
            }
        }

        return sprintf(
            '%s%s%s=%d',
            $categoryUrl,
            str_contains($categoryUrl, '?') ? '&' : '?',
            rawurlencode($attributeCode),
            $optionId
        );
    }

    private function categoryBelongsToStore(int $categoryId, int $rootCategoryId): bool
    {
        if ($rootCategoryId === 0) {
            return false;
        }

        try {
            $category = $this->categoryRepository->get($categoryId);
        } catch (NoSuchEntityException) {
            return false;
        }

        $path = (string) $category->getPath();
        return $path !== '' && (
            str_contains($path, '/' . $rootCategoryId . '/')
            || str_ends_with($path, '/' . $rootCategoryId)
        );
    }

    private function findCategoryViaFilterMeta(string $attributeCode, int $optionId, int $storeId): ?int
    {
        try {
            $store = $this->storeManager->getStore($storeId);
        } catch (NoSuchEntityException) {
            return null;
        }
        $rootId = (int) $store->getRootCategoryId();
        if ($rootId === 0) {
            return null;
        }

        $connection = $this->resource->getConnection();
        $metaTable = $this->resource->getTableName('panth_seo_category_filter_meta');
        $catEntity = $this->resource->getTableName('catalog_category_entity');

        $select = $connection->select()
            ->from(['m' => $metaTable], ['category_id'])
            ->join(['c' => $catEntity], 'c.entity_id = m.category_id', [])
            ->where('m.attribute_code = ?', $attributeCode)
            ->where('m.option_id = ?', $optionId)
            ->where('c.path LIKE ?', '1/' . $rootId . '/%')
            ->order('m.category_id ASC')
            ->limit(1);

        $id = $connection->fetchOne($select);

        return $id !== false && (int) $id > 0 ? (int) $id : null;
    }

    private function findFirstStorefrontCategory(int $storeId): ?int
    {
        try {
            $store = $this->storeManager->getStore($storeId);
        } catch (NoSuchEntityException) {
            return null;
        }

        $rootId = (int) $store->getRootCategoryId();
        if ($rootId === 0) {
            return null;
        }

        $connection = $this->resource->getConnection();
        $catEntity = $this->resource->getTableName('catalog_category_entity');

        $id = $connection->fetchOne(
            $connection->select()
                ->from(['cat' => $catEntity], ['entity_id'])
                ->where('cat.path LIKE ?', '1/' . $rootId . '/%')
                ->where('cat.level > ?', 1)
                ->order('cat.level ASC')
                ->order('cat.entity_id ASC')
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

        try {
            $store = $this->storeManager->getStore($storeId);
        } catch (NoSuchEntityException) {
            return null;
        }
        $rootId = (int) $store->getRootCategoryId();

        $connection = $this->resource->getConnection();
        $eavIndex = $this->resource->getTableName('catalog_product_index_eav');
        $catProduct = $this->resource->getTableName('catalog_category_product');
        $catEntity = $this->resource->getTableName('catalog_category_entity');

        $select = $connection->select()
            ->from(['eav' => $eavIndex], [])
            ->join(['ccp' => $catProduct], 'ccp.product_id = eav.entity_id', [])
            ->join(['c' => $catEntity], 'c.entity_id = ccp.category_id', ['entity_id'])
            ->where('eav.attribute_id = ?', (int) $attribute->getAttributeId())
            ->where('eav.value = ?', $optionId)
            ->where('eav.store_id = ?', $storeId)
            ->where('ccp.category_id > ?', 2)
            ->where('c.path LIKE ?', '1/' . $rootId . '/%')
            ->order('ccp.category_id ASC')
            ->limit(1);

        $id = $connection->fetchOne($select);

        return $id !== false && (int) $id > 0 ? (int) $id : null;
    }
}
