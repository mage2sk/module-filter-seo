<?php
/**
 * Panth Filter SEO — View on Storefront button for Filter Meta edit form.
 *
 * @copyright Copyright (c) Panth
 */
declare(strict_types=1);

namespace Panth\FilterSeo\Block\Adminhtml\FilterMeta\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\FilterSeo\Api\CategoryFilterMetaRepositoryInterface;
use Panth\FilterSeo\Helper\Config as SeoConfig;
use Panth\FilterSeo\Model\FilterUrl\RewriteRepository;
use Panth\FilterSeo\Model\FilterUrl\UrlBuilder;

/**
 * @see ButtonProviderInterface
 */
class ViewButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @param Context $context
     * @param CategoryFilterMetaRepositoryInterface $metaRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param RewriteRepository $rewriteRepository
     */
    public function __construct(
        Context $context,
        private readonly CategoryFilterMetaRepositoryInterface $metaRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly RewriteRepository $rewriteRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $id = $this->getId();
        if ($id === null) {
            return [];
        }

        $url = $this->resolveFrontendUrl($id);
        if ($url === '') {
            return [];
        }

        return [
            'label' => __('View on Storefront'),
            'class' => 'view',
            'on_click' => sprintf("window.open('%s', '_blank');", $url),
            'sort_order' => 15,
        ];
    }

    /**
     * @param int $metaId
     * @return string
     */
    private function resolveFrontendUrl(int $metaId): string
    {
        try {
            $row = $this->metaRepository->getById($metaId);
        } catch (\Throwable) {
            return '';
        }

        $categoryId = (int) $row->getData('category_id');
        $attributeCode = (string) $row->getData('attribute_code');
        $optionId = (int) $row->getData('option_id');

        if ($categoryId === 0 || $attributeCode === '' || $optionId === 0) {
            return '';
        }

        $storeId = (int) $row->getData('store_id');
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

        if (preg_match('/^(.*\/)([^\/]+?)(\.[a-z0-9]+)?$/i', $categoryUrl, $m)) {
            $prefix = $m[1];
            $base = $m[2];
            $suffix = $m[3] ?? '';
            return $prefix . $base . '/' . $segment . $suffix;
        }

        return rtrim($categoryUrl, '/') . '/' . $segment;
    }
}
