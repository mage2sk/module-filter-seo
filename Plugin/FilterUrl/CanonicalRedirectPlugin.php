<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */

declare(strict_types=1);

namespace Panth\FilterSeo\Plugin\FilterUrl;

use Magento\Catalog\Controller\Category\View;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Panth\FilterSeo\Helper\Config;
use Panth\FilterSeo\Model\FilterUrl\UrlBuilder;

/**
 * Plugin on Magento\Catalog\Controller\Category\View::execute()
 *
 * If the request reached the category controller via plain query-string
 * filters (e.g. /category.html?license_type=12), 301-redirect to the
 * rewritten path-based URL so old links and search-engine results
 * consolidate onto the canonical clean URL.
 */
class CanonicalRedirectPlugin
{
    /** @var string[]|null */
    private ?array $filterableAttributeCodes = null;

    private const KEEP_PARAMS = [
        'p',
        'product_list_limit',
        'product_list_order',
        'product_list_dir',
        'product_list_mode',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly Registry $registry,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlBuilder $urlBuilder,
        private readonly RedirectFactory $redirectFactory,
        private readonly AttributeCollectionFactory $attributeCollectionFactory
    ) {
    }

    public function aroundExecute(View $subject, callable $proceed)
    {
        if (!$this->config->isEnabled() || !$this->config->isFilterUrlEnabled()) {
            return $proceed();
        }

        $category = $this->registry->registry('current_category');
        if ($category === null) {
            return $proceed();
        }

        $params = $this->request->getParams();
        $filters = [];
        foreach ($this->getFilterableAttributeCodes() as $code) {
            if (!empty($params[$code])) {
                $filters[] = [
                    'attribute_code' => $code,
                    'option_id' => (int) $params[$code],
                ];
            }
        }
        if ($filters === []) {
            return $proceed();
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            $rewritten = $this->urlBuilder->build($category->getUrl(), $filters, $storeId);
        } catch (\Throwable) {
            return $proceed();
        }

        if ($rewritten === '' || $rewritten === $category->getUrl()) {
            return $proceed();
        }

        $query = [];
        foreach (self::KEEP_PARAMS as $k) {
            if (isset($params[$k]) && $params[$k] !== '') {
                $query[$k] = $params[$k];
            }
        }
        if ($query !== []) {
            $rewritten .= (str_contains($rewritten, '?') ? '&' : '?') . http_build_query($query);
        }

        return $this->redirectFactory->create()->setUrl($rewritten)->setHttpResponseCode(301);
    }

    /**
     * @return string[]
     */
    private function getFilterableAttributeCodes(): array
    {
        if ($this->filterableAttributeCodes !== null) {
            return $this->filterableAttributeCodes;
        }
        $codes = [];
        try {
            $coll = $this->attributeCollectionFactory->create();
            $coll->setEntityTypeFilter(4);
            $coll->addFieldToFilter('is_filterable', ['in' => [1, 2]]);
            foreach ($coll as $attr) {
                $codes[] = (string) $attr->getAttributeCode();
            }
        } catch (\Throwable) {
            // Empty list is safe — plugin will simply pass through.
        }
        return $this->filterableAttributeCodes = $codes;
    }
}
