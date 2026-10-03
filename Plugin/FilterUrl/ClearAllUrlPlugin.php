<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Plugin\FilterUrl;

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\LayeredNavigation\Block\Navigation\State as StateBlock;
use Magento\Store\Model\StoreManagerInterface;
use Panth\FilterSeo\Helper\Config;

class ClearAllUrlPlugin
{
    public function __construct(
        private readonly Config $config,
        private readonly LayerResolver $layerResolver,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function afterGetClearUrl(StateBlock $subject, $result)
    {
        if (!$this->config->isEnabled() || !$this->config->isFilterUrlEnabled()) {
            return $result;
        }

        try {
            $category = $this->layerResolver->get()->getCurrentCategory();
            if ($category === null || !$category->getId()) {
                return $result;
            }
            return (string) $category->getUrl();
        } catch (\Throwable) {
            return $result;
        }
    }
}
