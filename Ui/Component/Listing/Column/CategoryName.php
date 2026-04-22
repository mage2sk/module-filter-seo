<?php
/**
 * Panth Filter SEO — resolve category_id to human-readable name column.
 *
 * @copyright Copyright (c) Panth
 */
declare(strict_types=1);

namespace Panth\FilterSeo\Ui\Component\Listing\Column;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Resolves category_id to the category name + id for the Filter Meta grid.
 */
class CategoryName extends Column
{
    /** @var array<int,string> */
    private array $cache = [];

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param CategoryRepositoryInterface $categoryRepository
     * @param array<int,mixed> $components
     * @param array<string,mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly CategoryRepositoryInterface $categoryRepository,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array<string,mixed> $dataSource
     * @return array<string,mixed>
     */
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

    /**
     * @param int $categoryId
     * @return string
     */
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
