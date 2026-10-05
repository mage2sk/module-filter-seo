<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Plugin\FilterMeta;

use Magento\Catalog\Block\Category\View as CategoryViewBlock;
use Magento\Framework\Registry;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\StoreManagerInterface;
use Panth\FilterSeo\Helper\Config as SeoConfig;
use Panth\FilterSeo\Model\FilterUrl\ActiveFacetProvider;
use Psr\Log\LoggerInterface;

class FacetRobotsPlugin
{
    public function __construct(
        private readonly ActiveFacetProvider $activeFacetProvider,
        private readonly Registry $registry,
        private readonly PageConfig $pageConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly SeoConfig $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function afterSetLayout(CategoryViewBlock $subject, $result)
    {
        try {
            $storeId = (int) $this->storeManager->getStore()->getId();

            if (!$this->config->isNoindexCombinationsEnabled($storeId)) {
                return $result;
            }

            $category = $this->registry->registry('current_category');
            if ($category === null || !$category->getId()) {
                return $result;
            }

            if ($this->activeFacetProvider->countActiveFacets() >= $this->config->getNoindexMinFacets($storeId)) {
                $this->pageConfig->setRobots('noindex,follow');
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Panth FilterSeo facet-combination noindex failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }
}
