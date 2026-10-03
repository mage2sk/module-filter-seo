<?php
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

class CanonicalRedirectPlugin
{
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

        $query = $this->request->getQuery()->toArray();
        $categoryUrl = '';
        $rewritten = '';

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            $filters = [];
            $hasSluggedQueryFilter = false;
            foreach ($this->getFilterableAttributeCodes() as $code) {
                $value = $query[$code] ?? $this->request->getParam($code);
                if (!is_scalar($value)) {
                    continue;
                }
                $value = (string) $value;
                if ($value === '') {
                    continue;
                }
                $filters[] = [
                    'attribute_code' => $code,
                    'option_id' => (int) $value,
                    'value' => $value,
                ];
                if (isset($query[$code])
                    && ctype_digit($value)
                    && $this->urlBuilder->hasSlug($code, (int) $value, $storeId)
                ) {
                    $hasSluggedQueryFilter = true;
                }
            }
            if ($hasSluggedQueryFilter) {
                $category = $this->categoryRepository->get($categoryId, $storeId);
                $categoryUrl = (string) $category->getUrl();
                $rewritten = $this->urlBuilder->build($categoryUrl, $filters, $storeId);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Panth FilterSeo canonical redirect skipped', ['error' => $e->getMessage()]);
            $rewritten = '';
        }

        if ($rewritten === '' || $rewritten === $categoryUrl) {
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
        }
        return $this->filterableAttributeCodes = $codes;
    }
}
