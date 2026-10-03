<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_FILTER_URL_ENABLED   = 'panth_filter_seo/filter_urls/filter_urls_enabled';
    public const XML_FILTER_URL_FORMAT    = 'panth_filter_seo/filter_urls/url_format';
    public const XML_FILTER_URL_SEPARATOR = 'panth_filter_seo/filter_urls/separator';
    public const XML_FILTER_NOINDEX_COMBINATIONS = 'panth_filter_seo/filter_urls/noindex_combinations';
    public const XML_FILTER_NOINDEX_MIN_FACETS   = 'panth_filter_seo/filter_urls/noindex_min_facets';

    public const XML_FILTER_META_ENABLED            = 'panth_filter_seo/filter_meta/filter_meta_enabled';
    public const XML_FILTER_META_INJECT_TITLE       = 'panth_filter_seo/filter_meta/inject_filter_in_title';
    public const XML_FILTER_META_INJECT_DESCRIPTION = 'panth_filter_seo/filter_meta/inject_filter_in_description';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isNoindexCombinationsEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_FILTER_NOINDEX_COMBINATIONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getNoindexMinFacets(?int $storeId = null): int
    {
        $value = (int) $this->scopeConfig->getValue(
            self::XML_FILTER_NOINDEX_MIN_FACETS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value >= 1 ? $value : 2;
    }

    public function getValue(string $path, ?int $storeId = null)
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return true;
    }

    public function isFilterUrlEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_FILTER_URL_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isFilterMetaEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_FILTER_META_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
