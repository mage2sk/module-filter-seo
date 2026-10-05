<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Ui\Component\Listing\Column;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class CategoryName extends Column
{
    private array $cache = [];

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly CategoryRepositoryInterface $categoryRepository,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $fieldName = $this->getData('name') ?: 'category_name';

        foreach ($dataSource['data']['items'] as &$item) {
            $categoryId = (int) ($item['category_id'] ?? 0);
            $item[$fieldName] = $this->resolveName($categoryId);
        }

        return $dataSource;
    }

    private function resolveName(int $categoryId): string
    {
        if ($categoryId === 0) {
            return '';
        }

        if (isset($this->cache[$categoryId])) {
            return $this->cache[$categoryId];
        }

        try {
            $name = (string) $this->categoryRepository->get($categoryId)->getName();
            $this->cache[$categoryId] = sprintf('%s (%d)', $name, $categoryId);
        } catch (NoSuchEntityException) {
            $this->cache[$categoryId] = sprintf('(deleted #%d)', $categoryId);
        }

        return $this->cache[$categoryId];
    }
}
