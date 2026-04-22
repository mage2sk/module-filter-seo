<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Plugin\FilterUrl;

use Magento\Catalog\Model\Layer\Filter\Item;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Panth\FilterSeo\Helper\Config;
use Panth\FilterSeo\Model\FilterUrl\UrlBuilder;

/**
 * Plugin on Magento\Catalog\Model\Layer\Filter\Item::getRemoveUrl()
 *
 * Generates a SEO-friendly URL that removes this filter's segment.
 */
class FilterItemRemoveUrlPlugin
{
    public function __construct(
        private readonly UrlBuilder $urlBuilder,
        private readonly Config $config,
        private readonly RequestInterface $request,
        private readonly Registry $registry,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param Item $subject
     * @param callable $proceed
     * @return string
     */
    public function aroundGetRemoveUrl(Item $subject, callable $proceed): string
    {
        if (!$this->isEnabled()) {
            return $proceed();
        }

        $category = $this->registry->registry('current_category');
        if ($category === null) {
            return $proceed();
        }

        $storeId     = (int) $this->storeManager->getStore()->getId();
        $categoryUrl = $category->getUrl();

        $activeFilters = $this->collectActiveFilters();
        $filter        = $subject->getFilter();

        $url = $this->urlBuilder->buildWithout(
            $categoryUrl,
            $activeFilters,
            $filter->getRequestVar(),
            (int) $subject->getValue(),
            $storeId
        );

        return $url;
    }

    /**
     * @return array<int, array{attribute_code: string, option_id: int}>
     */
    private function collectActiveFilters(): array
    {
        $params  = $this->request->getParams();
        $filters = [];

        $nonFilterKeys = ['p', 'product_list_limit', 'product_list_order', 'product_list_dir',
            'product_list_mode', 'q', 'id', 'cat'];

        foreach ($params as $key => $value) {
            if (in_array($key, $nonFilterKeys, true) || $value === null || $value === '') {
                continue;
            }
            $filters[] = [
                'attribute_code' => (string) $key,
                'option_id'      => (int) $value,
            ];
        }

        return $filters;
    }

    /**
     * @return bool
     */
    private function isEnabled(): bool
    {
        return $this->config->isEnabled()
            && $this->config->isFilterUrlEnabled();
    }
}
