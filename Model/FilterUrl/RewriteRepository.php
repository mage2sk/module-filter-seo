<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\FilterUrl;

use Panth\FilterSeo\Model\ResourceModel\FilterRewrite\CollectionFactory;

class RewriteRepository
{
    private array $slugMap = [];

    private array $reverseMap = [];

    private array $attributeSlugMap = [];

    private array $loaded = [];

    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function getSlug(string $attrCode, int $optionId, int $storeId): ?string
    {
        $this->preload($storeId);
        $key = $attrCode . '|' . $optionId;
        return $this->slugMap[$storeId][$key] ?? null;
    }

    public function getBySlug(string $slug, int $storeId): ?array
    {
        $this->preload($storeId);
        return $this->reverseMap[$storeId][$slug] ?? null;
    }

    public function getOptionIdBySlug(string $attrCode, string $slug, int $storeId): ?int
    {
        $this->preload($storeId);
        $optionId = $this->attributeSlugMap[$storeId][$attrCode . '|' . $slug] ?? null;
        return $optionId === null ? null : (int) $optionId;
    }

    private function preload(int $storeId): void
    {
        if (isset($this->loaded[$storeId])) {
            return;
        }

        $this->slugMap[$storeId] = [];
        $this->reverseMap[$storeId] = [];
        $this->attributeSlugMap[$storeId] = [];

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->addFieldToFilter('store_id', ['in' => [0, $storeId]]);

        $collection->setOrder('store_id', 'ASC');

        foreach ($collection as $item) {
            $key = $item->getAttributeCode() . '|' . $item->getOptionId();
            $slug = $item->getRewriteSlug();

            $this->slugMap[$storeId][$key] = $slug;
            $this->reverseMap[$storeId][$slug] = [
                $item->getAttributeCode(),
                $item->getOptionId(),
            ];
            $this->attributeSlugMap[$storeId][$item->getAttributeCode() . '|' . $slug] = $item->getOptionId();
        }

        $this->loaded[$storeId] = true;
    }

    public function reset(): void
    {
        $this->slugMap = [];
        $this->reverseMap = [];
        $this->attributeSlugMap = [];
        $this->loaded = [];
    }
}
