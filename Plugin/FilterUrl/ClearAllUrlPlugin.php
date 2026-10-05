<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Plugin\FilterUrl;

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Framework\App\RequestInterface;
use Magento\LayeredNavigation\Block\Navigation\State as StateBlock;
use Magento\Store\Model\StoreManagerInterface;
use Panth\FilterSeo\Helper\Config;

class ClearAllUrlPlugin
{
    public function __construct(
        private readonly Config $config,
        private readonly LayerResolver $layerResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly RequestInterface $request
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
            $url = (string) $category->getUrl();
            $carry = [];
            foreach (['product_list_order', 'product_list_dir', 'product_list_limit', 'product_list_mode'] as $key) {
                $value = $this->request->getParam($key);
                if (is_scalar($value) && (string) $value !== '') {
                    $carry[$key] = (string) $value;
                }
            }
            if ($carry === []) {
                return $url;
            }
            return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($carry);
        } catch (\Throwable) {
            return $result;
        }
    }
}
