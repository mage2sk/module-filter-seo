<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed accessor for `panth_filter_seo/filter_urls/*` and `panth_filter_seo/filter_meta/*` settings
 * owned by Panth_FilterSeo.
 */
class Config
{
    /** Filter URLs */
    public const XML_FILTER_URL_ENABLED   = 'panth_filter_seo/filter_urls/filter_urls_enabled';
    public const XML_FILTER_URL_FORMAT    = 'panth_filter_seo/filter_urls/url_format';
    public const XML_FILTER_URL_SEPARATOR = 'panth_filter_seo/filter_urls/separator';

    /** Filter Meta */
    public const XML_FILTER_META_ENABLED            = 'panth_filter_seo/filter_meta/filter_meta_enabled';
    public const XML_FILTER_META_INJECT_TITLE       = 'panth_filter_seo/filter_meta/inject_filter_in_title';
    public const XML_FILTER_META_INJECT_DESCRIPTION = 'panth_filter_seo/filter_meta/inject_filter_in_description';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Return the typed value for a config path.
     *
     * @return mixed
     */
    public function getValue(string $path, ?int $storeId = null)
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Module-level enabled flag. For a standalone module, we assume "on" unless
     * every feature flag below is explicitly disabled. This mirrors the boolean
     * the original AdvancedSEO helper returned for `panth_seo/general/enabled`.
     */
    public function isEnabled(?int $storeId = null): bool
    {
        // The feature gates (filter_urls_enabled, filter_meta_enabled) already
        // decide whether any logic runs. This remains true for shared utility
        // calls; features check their own enabled flags below.
        return true;
    }

    /**
     * Whether SEO-friendly filter URLs are enabled for the store.
     */
    public function isFilterUrlEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_FILTER_URL_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Whether filter meta overrides are enabled for the store.
     */
    public function isFilterMetaEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_FILTER_META_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
