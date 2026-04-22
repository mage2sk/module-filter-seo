<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\FilterMeta;

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\ScopeInterface;
use Panth\FilterSeo\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Applies per-filter meta overrides to the page config when layered navigation filters are active.
 *
 * Resolution order per filter:
 *   1. Exact DB record in panth_seo_category_filter_meta (per category / attribute / option / store).
 *   2. Auto-append filter labels to the page title (when enabled).
 */
class MetaInjector
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly RequestInterface $request,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LayerResolver $layerResolver,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Detect active filters and inject matching meta title/description/keywords into PageConfig.
     *
     * @param PageConfig $pageConfig
     * @param int $categoryId
     * @param int $storeId
     * @return void
     */
    public function inject(PageConfig $pageConfig, int $categoryId, int $storeId): void
    {
        $activeFilters = $this->getActiveFilters();
        if (empty($activeFilters)) {
            return;
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('panth_seo_category_filter_meta');

        $appliedTitle = null;
        $appliedDescription = null;
        $appliedKeywords = null;

        foreach ($activeFilters as $attributeCode => $optionId) {
            try {
                $select = $connection->select()
                    ->from($table)
                    ->where('category_id = ?', $categoryId)
                    ->where('attribute_code = ?', $attributeCode)
                    ->where('option_id = ?', (int) $optionId)
                    ->where('store_id IN (?)', [0, $storeId])
                    ->order('store_id DESC')
                    ->limit(1);

                $row = $connection->fetchRow($select);
            } catch (\Throwable $e) {
                $this->logger->warning('Panth FilterSeo: failed to query filter meta', [
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if ($row === false) {
                continue;
            }

            $metaTitle = !empty($row['meta_title']) ? (string) $row['meta_title'] : null;
            $metaDesc = !empty($row['meta_description']) ? (string) $row['meta_description'] : null;
            $metaKw = !empty($row['meta_keywords']) ? (string) $row['meta_keywords'] : null;

            if ($metaTitle !== null) {
                $appliedTitle = $metaTitle;
            }
            if ($metaDesc !== null) {
                $appliedDescription = $metaDesc;
            }
            if ($metaKw !== null) {
                $appliedKeywords = $metaKw;
            }
        }

        if ($appliedTitle !== null) {
            $pageConfig->getTitle()->set($appliedTitle);
        }
        if ($appliedDescription !== null) {
            $pageConfig->setDescription($appliedDescription);
        }
        if ($appliedKeywords !== null) {
            $pageConfig->setKeywords($appliedKeywords);
        }

        // If inject_filter_in_title is enabled and no specific title override was found,
        // append filter values to the existing page title.
        if ($appliedTitle === null && $this->isInjectFilterInTitleEnabled($storeId)) {
            $this->appendFiltersToTitle($pageConfig, $activeFilters);
        }

        // If inject_filter_in_description is enabled and no specific description
        // override was found, append filter labels to the existing meta description.
        if ($appliedDescription === null && $this->isInjectFilterInDescriptionEnabled($storeId)) {
            $this->appendFiltersToDescription($pageConfig, $activeFilters);
        }
    }

    /**
     * Get active layered navigation filters as [attribute_code => option_id].
     *
     * @return array<string, string>
     */
    private function getActiveFilters(): array
    {
        $filters = [];

        try {
            $layer = $this->layerResolver->get();
            $state = $layer->getState();
            foreach ($state->getFilters() as $filterItem) {
                $filter = $filterItem->getFilter();
                $attributeCode = $filter->getRequestVar();
                $value = $filterItem->getValueString();
                if ($attributeCode !== '' && $value !== '') {
                    $filters[$attributeCode] = $value;
                }
            }
        } catch (\Throwable) {
            // Layer may not be initialised; fall back to request params
            $knownFilterParams = ['color', 'size', 'price', 'material', 'style', 'brand', 'manufacturer'];
            foreach ($knownFilterParams as $param) {
                $val = $this->request->getParam($param);
                if ($val !== null && $val !== '') {
                    $filters[$param] = (string) $val;
                }
            }
        }

        return $filters;
    }

    /**
     * Append active filter labels to the existing page title.
     *
     * Result example: "Shoes | Color: Red, Size: XL"
     *
     * @param PageConfig $pageConfig
     * @param array<string, string> $activeFilters
     * @return void
     */
    private function appendFiltersToTitle(PageConfig $pageConfig, array $activeFilters): void
    {
        $parts = [];
        try {
            $layer = $this->layerResolver->get();
            foreach ($layer->getState()->getFilters() as $filterItem) {
                $code = $filterItem->getFilter()->getRequestVar();
                if (isset($activeFilters[$code])) {
                    $filterName = $this->sanitize((string) $filterItem->getFilter()->getName());
                    $label = $this->sanitize((string) $filterItem->getLabel());
                    $parts[] = $filterName . ': ' . $label;
                }
            }
        } catch (\Throwable) {
            foreach ($activeFilters as $code => $value) {
                $parts[] = ucfirst($this->sanitize((string) $code)) . ': ' . $this->sanitize((string) $value);
            }
        }

        if (empty($parts)) {
            return;
        }

        $currentTitle = $pageConfig->getTitle()->getShort();
        $suffix = implode(', ', $parts);
        $pageConfig->getTitle()->set($currentTitle . ' | ' . $suffix);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    private function isInjectFilterInTitleEnabled(?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            Config::XML_FILTER_META_INJECT_TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Append active filter labels to the existing meta description.
     *
     * Result example: "Shop trendy tops for women. | Color: Red, Size: XL"
     *
     * @param PageConfig $pageConfig
     * @param array<string, string> $activeFilters
     * @return void
     */
    private function appendFiltersToDescription(PageConfig $pageConfig, array $activeFilters): void
    {
        $parts = [];
        try {
            $layer = $this->layerResolver->get();
            foreach ($layer->getState()->getFilters() as $filterItem) {
                $code = $filterItem->getFilter()->getRequestVar();
                if (isset($activeFilters[$code])) {
                    $filterName = $this->sanitize((string) $filterItem->getFilter()->getName());
                    $label = $this->sanitize((string) $filterItem->getLabel());
                    $parts[] = $filterName . ': ' . $label;
                }
            }
        } catch (\Throwable) {
            foreach ($activeFilters as $code => $value) {
                $parts[] = ucfirst($this->sanitize((string) $code)) . ': ' . $this->sanitize((string) $value);
            }
        }

        if (empty($parts)) {
            return;
        }

        $currentDescription = (string) $pageConfig->getDescription();
        $suffix = implode(', ', $parts);
        $pageConfig->setDescription(
            $currentDescription !== ''
                ? $currentDescription . ' | ' . $suffix
                : $suffix
        );
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    private function isInjectFilterInDescriptionEnabled(?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            Config::XML_FILTER_META_INJECT_DESCRIPTION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Defense in depth for values that get concatenated into <title> / meta
     * description. PageConfig output escapes these, but stripping tags at the
     * source means a user-controlled filter value can never carry raw HTML
     * through an upstream bug.
     */
    private function sanitize(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
        return trim($value);
    }
}
