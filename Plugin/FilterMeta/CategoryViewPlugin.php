<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Plugin\FilterMeta;

use Magento\Catalog\Block\Category\View as CategoryViewBlock;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\FilterSeo\Helper\Config as SeoConfig;
use Panth\FilterSeo\Model\FilterMeta\MetaInjector;
use Psr\Log\LoggerInterface;

class CategoryViewPlugin
{
    public function __construct(
        private readonly MetaInjector $metaInjector,
        private readonly Registry $registry,
        private readonly PageConfig $pageConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger,
        private readonly SeoConfig $seoConfig
    ) {
    }

    public function afterSetLayout(CategoryViewBlock $subject, $result)
    {
        try {
            if (!$this->isFilterMetaEnabled()) {
                return $result;
            }

            $category = $this->registry->registry('current_category');
            if ($category === null || !$category->getId()) {
                return $result;
            }

            $this->metaInjector->inject(
                $this->pageConfig,
                (int) $category->getId(),
                (int) $this->storeManager->getStore()->getId()
            );
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Panth FilterSeo filter meta injection failed',
                ['error' => $e->getMessage()]
            );
        }

        return $result;
    }

    private function isFilterMetaEnabled(): bool
    {
        return $this->seoConfig->isEnabled()
            && $this->scopeConfig->isSetFlag(
                SeoConfig::XML_FILTER_META_ENABLED,
                ScopeInterface::SCOPE_STORE
            );
    }
}
