<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */

declare(strict_types=1);

namespace Panth\FilterSeo\Plugin\FilterUrl;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Controller\Category\View;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Store\Model\StoreManagerInterface;
use Panth\FilterSeo\Helper\Config;
use Panth\FilterSeo\Model\FilterUrl\UrlBuilder;
use Psr\Log\LoggerInterface;

/**
 * Plugin on Magento\Catalog\Controller\Category\View::execute()
 *
 * If the request reached the category controller via plain query-string
 * filters (e.g. /category.html?license_type=12), 301-redirect to the
 * rewritten path-based URL so old links and search-engine results
 * consolidate onto the canonical clean URL.
 *
 * NOTE: we deliberately do NOT read 'current_category' from the registry —
 * it isn't populated until inside View::execute() (via _initCategory()),
 * which is AFTER our aroundExecute fires. Instead, we read the category id
 * from the request (URL rewrite already populated it) and load via the
 * repository. Loading is cheap (in-memory cache hit when execute() does
 * the same load moments later).
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
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlBuilder $urlBuilder,
        private readonly RedirectFactory $redirectFactory,
        private readonly AttributeCollectionFactory $attributeCollectionFactory,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function aroundExecute(View $subject, callable $proceed)
    {
        if (!$this->config->isEnabled() || !$this->config->isFilterUrlEnabled()) {
            return $proceed();
        }

        $categoryId = (int) $this->request->getParam('id');
        if ($categoryId <= 0) {
            return $proceed();
        }

        // Read filter codes from $_GET only — never from $request->getParams().
        // FilterRouter::match() does setParam() for the attribute codes on
        // pretty URLs, so getParams() can't distinguish router-supplied from
        // user-supplied filters; reading it here would 301 every pretty URL
        // back to itself (infinite redirect loop). Reading getQuery() means
        // pretty URLs (empty $_GET) skip the redirect naturally.
        $query = $this->request->getQuery()->toArray();
        $filters = [];
        foreach ($this->getFilterableAttributeCodes() as $code) {
            if (!empty($query[$code])) {
                $filters[] = [
                    'attribute_code' => $code,
                    'option_id' => (int) $query[$code],
                ];
            }
        }
        if ($filters === []) {
            return $proceed();
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            $category = $this->categoryRepository->get($categoryId, $storeId);
            $rewritten = $this->urlBuilder->build($category->getUrl(), $filters, $storeId);
        } catch (\Throwable $e) {
            $this->logger->warning('Panth FilterSeo canonical redirect skipped', ['error' => $e->getMessage()]);
            return $proceed();
        }

        if ($rewritten === '' || $rewritten === $category->getUrl()) {
            return $proceed();
        }

        $carry = [];
        foreach (self::KEEP_PARAMS as $k) {
            if (isset($query[$k]) && $query[$k] !== '') {
                $carry[$k] = $query[$k];
            }
        }
        if ($carry !== []) {
            $rewritten .= (str_contains($rewritten, '?') ? '&' : '?') . http_build_query($carry);
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
