<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\FilterMeta;

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\ScopeInterface;
use Panth\FilterSeo\Helper\Config;
use Psr\Log\LoggerInterface;

class MetaInjector
{
    private ?array $filterableAttributeCodes = null;

    private ?string $appendedDescription = null;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly RequestInterface $request,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LayerResolver $layerResolver,
        private readonly LoggerInterface $logger,
        private readonly AttributeCollectionFactory $attributeCollectionFactory,
        private readonly EavConfig $eavConfig
    ) {
    }

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

        if ($appliedTitle === null && $this->isInjectFilterInTitleEnabled($storeId)) {
            $this->appendFiltersToTitle($pageConfig, $activeFilters, $storeId);
        }

        if ($appliedDescription === null && $this->isInjectFilterInDescriptionEnabled($storeId)) {
            $this->appendFiltersToDescription($pageConfig, $activeFilters, $storeId);
        }
    }

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
        }

        if ($filters === []) {
            foreach ($this->getFilterableAttributeCodes() as $code) {
                $val = $this->request->getParam($code);
                if ($val !== null && $val !== '') {
                    $filters[$code] = (string) $val;
                }
            }
        }

        return $filters;
    }

    private function getFilterableAttributeCodes(): array
    {
        if ($this->filterableAttributeCodes !== null) {
            return $this->filterableAttributeCodes;
        }

        $codes = [];
        try {
            $coll = $this->attributeCollectionFactory->create();
            $coll->setEntityTypeFilter(4);
            $coll->addFieldToFilter('is_filterable', ['in' => [1, 2]]);
            foreach ($coll as $attr) {
                $codes[] = (string) $attr->getAttributeCode();
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Panth FilterSeo: failed loading filterable attributes', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->filterableAttributeCodes = $codes;
    }

    private function appendFiltersToTitle(PageConfig $pageConfig, array $activeFilters, int $storeId): void
    {
        $parts = $this->buildFilterParts($activeFilters, $storeId);

        if (empty($parts)) {
            return;
        }

        $currentTitle = $pageConfig->getTitle()->getShort();
        $suffix = implode(', ', $parts);
        $pageConfig->getTitle()->set($currentTitle . ' | ' . $suffix);
    }

    private function isInjectFilterInTitleEnabled(?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            Config::XML_FILTER_META_INJECT_TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    private function appendFiltersToDescription(PageConfig $pageConfig, array $activeFilters, int $storeId): void
    {
        $parts = $this->buildFilterParts($activeFilters, $storeId);

        if (empty($parts)) {
            return;
        }

        $currentDescription = (string) $pageConfig->getDescription();
        if ($this->appendedDescription !== null && $currentDescription === $this->appendedDescription) {
            return;
        }
        $suffix = implode(', ', $parts);
        $this->appendedDescription = $currentDescription !== ''
            ? $currentDescription . ' | ' . $suffix
            : $suffix;
        $pageConfig->setDescription($this->appendedDescription);
    }

    private function isInjectFilterInDescriptionEnabled(?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            Config::XML_FILTER_META_INJECT_DESCRIPTION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    private function buildFilterParts(array $activeFilters, int $storeId): array
    {
        $parts = [];
        try {
            $layer = $this->layerResolver->get();
            foreach ($layer->getState()->getFilters() as $filterItem) {
                $code = $filterItem->getFilter()->getRequestVar();
                if (isset($activeFilters[$code])) {
                    $filterName = $this->sanitize((string) $filterItem->getFilter()->getName());
                    $label = $this->sanitize((string) $filterItem->getLabel());
                    $parts[$code] = $filterName . ': ' . $label;
                }
            }
        } catch (\Throwable) {
        }

        foreach ($activeFilters as $code => $value) {
            if (isset($parts[$code])) {
                continue;
            }
            [$name, $label] = $this->resolveAttributeLabels((string) $code, (string) $value, $storeId);
            if ($name !== '' && $label !== '') {
                $parts[$code] = $name . ': ' . $label;
            }
        }

        return array_values($parts);
    }

    private function resolveAttributeLabels(string $code, string $value, int $storeId): array
    {
        $name = ucfirst($this->sanitize($code));
        $label = $this->sanitize($value);
        try {
            $attribute = $this->eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, $code);
            if (!$attribute || !$attribute->getId()) {
                return [$name, $label];
            }
            $storeLabel = $this->sanitize((string) $attribute->getStoreLabel($storeId));
            if ($storeLabel !== '') {
                $name = $storeLabel;
            }
            if ($attribute->usesSource()) {
                $texts = [];
                foreach (explode(',', $value) as $optionId) {
                    $text = $attribute->getSource()->getOptionText(trim($optionId));
                    if (is_array($text)) {
                        $text = implode(', ', $text);
                    }
                    $text = $this->sanitize(html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if ($text !== '') {
                        $texts[] = $text;
                    }
                }
                if ($texts !== []) {
                    $label = implode(', ', $texts);
                }
            }
        } catch (\Throwable) {
        }

        return [$name, $label];
    }

    private function sanitize(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
        return trim($value);
    }
}
