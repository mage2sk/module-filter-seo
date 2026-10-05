<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\FilterUrl;

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;

class ActiveFacetProvider
{
    private const NON_FACET_PARAMS = [
        'p',
        'product_list_order',
        'product_list_dir',
        'product_list_limit',
        'product_list_mode',
        'cat',
    ];

    private ?array $filterableAttributeCodes = null;

    public function __construct(
        private readonly LayerResolver $layerResolver,
        private readonly RequestInterface $request,
        private readonly AttributeCollectionFactory $attributeCollectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getActiveFacets(): array
    {
        $facets = [];

        try {
            $state = $this->layerResolver->get()->getState();
            foreach ($state->getFilters() as $filterItem) {
                $code = (string) $filterItem->getFilter()->getRequestVar();
                $value = (string) $filterItem->getValueString();
                if ($code !== '' && $value !== '' && !in_array($code, self::NON_FACET_PARAMS, true)) {
                    $facets[$code] = $value;
                }
            }
        } catch (\Throwable) {
        }

        if ($facets === []) {
            foreach ($this->getFilterableAttributeCodes() as $code) {
                $value = $this->request->getParam($code);
                if ($value !== null && $value !== '' && !in_array($code, self::NON_FACET_PARAMS, true)) {
                    $facets[$code] = (string) $value;
                }
            }
        }

        return $facets;
    }

    public function countActiveFacets(): int
    {
        return count($this->getActiveFacets());
    }

    private function getFilterableAttributeCodes(): array
    {
        if ($this->filterableAttributeCodes !== null) {
            return $this->filterableAttributeCodes;
        }

        $codes = [];
        try {
            $collection = $this->attributeCollectionFactory->create();
            $collection->setEntityTypeFilter(4);
            $collection->addFieldToFilter('is_filterable', ['in' => [1, 2]]);
            foreach ($collection as $attribute) {
                $codes[] = (string) $attribute->getAttributeCode();
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Panth FilterSeo: failed loading filterable attributes', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->filterableAttributeCodes = $codes;
    }
}
