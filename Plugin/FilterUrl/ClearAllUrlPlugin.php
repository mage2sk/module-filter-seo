<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Plugin\FilterUrl;

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\LayeredNavigation\Block\Navigation\State as StateBlock;
use Magento\Store\Model\StoreManagerInterface;
use Panth\FilterSeo\Helper\Config;

/**
 * Plugin on Magento\LayeredNavigation\Block\Navigation\State::getClearUrl()
 *
 * Native getClearUrl() builds the "remove all filters" link by stripping
 * query-string filter params. On pretty FilterSeo URLs the filters live in
 * the path, not the query string — so the native method returns the current
 * request URL unchanged and "Clear All" becomes a no-op. Replace with the
 * bare category URL so the button actually clears all filters.
 */
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
