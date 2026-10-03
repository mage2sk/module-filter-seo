<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Plugin\FilterUrl;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Swatches\Block\LayeredNavigation\RenderLayered;
use Panth\FilterSeo\Helper\Config;
use Panth\FilterSeo\Model\FilterUrl\UrlBuilder;

class SwatchUrlPlugin
{
    public function __construct(
        private readonly UrlBuilder $urlBuilder,
        private readonly Config $config,
        private readonly RequestInterface $request,
        private readonly Registry $registry,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function aroundBuildUrl(RenderLayered $subject, callable $proceed, string $attributeCode, int $optionId): string
    {
        if (!$this->isEnabled()) {
            return $proceed($attributeCode, $optionId);
        }

        $category = $this->registry->registry('current_category');
        if ($category === null) {
            return $proceed($attributeCode, $optionId);
        }

        $storeId     = (int) $this->storeManager->getStore()->getId();
        $categoryUrl = $category->getUrl();

        $activeFilters   = $this->collectActiveFilters();
        $activeFilters[] = [
            'attribute_code' => $attributeCode,
            'option_id'      => $optionId,
        ];

        $url = $this->urlBuilder->build($categoryUrl, $activeFilters, $storeId);
        if ($url === $categoryUrl) {
            return $proceed($attributeCode, $optionId);
        }

        return $url;
    }

    private function collectActiveFilters(): array
    {
        $params  = $this->request->getParams();
        $filters = [];

        $nonFilterKeys = ['p', 'product_list_limit', 'product_list_order', 'product_list_dir',
            'product_list_mode', 'q', 'id', 'cat'];

        foreach ($params as $key => $value) {
            if (in_array($key, $nonFilterKeys, true)
                || !is_scalar($value)
                || $value === ''
                || !preg_match('/^[a-z][a-z0-9_]*$/i', (string) $key)
            ) {
                continue;
            }
            $filters[] = [
                'attribute_code' => (string) $key,
                'option_id'      => (int) $value,
                'value'          => (string) $value,
            ];
        }

        return $filters;
    }

    private function isEnabled(): bool
    {
        return $this->config->isEnabled()
            && $this->config->isFilterUrlEnabled();
    }
}
