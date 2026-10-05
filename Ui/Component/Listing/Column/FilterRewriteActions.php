<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Ui\Component\Listing\Column;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Panth\FilterSeo\Model\FilterUrl\ViewUrlResolver;

class FilterRewriteActions extends Column
{
    public const URL_PATH_EDIT = 'panth_filterseo/filterRewrite/edit';
    public const URL_PATH_DELETE = 'panth_filterseo/filterRewrite/delete';

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly ViewUrlResolver $viewUrlResolver,
        private readonly ResourceConnection $resource,
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

        $name = $this->getData('name');
        $rawRows = $this->loadRawRows($dataSource['data']['items']);

        foreach ($dataSource['data']['items'] as &$item) {
            $id = $item['rewrite_id'] ?? null;
            if ($id === null) {
                continue;
            }

            $item[$name]['edit'] = [
                'href' => $this->urlBuilder->getUrl(self::URL_PATH_EDIT, ['id' => $id]),
                'label' => (string) __('Edit'),
            ];

            $raw = $rawRows[(int) $id] ?? [];
            $viewUrl = $this->viewUrlResolver->resolveWithoutCategory(
                (string) ($raw['attribute_code'] ?? ''),
                (int) ($raw['option_id'] ?? 0),
                (int) ($raw['store_id'] ?? 0)
            );
            if ($viewUrl !== '') {
                $item[$name]['view'] = [
                    'href' => $viewUrl,
                    'label' => (string) __('View on Storefront'),
                    'target' => '_blank',
                ];
            }

            $item[$name]['delete'] = [
                'href' => $this->urlBuilder->getUrl(self::URL_PATH_DELETE, ['id' => $id]),
                'label' => (string) __('Delete'),
                'post' => true,
                'confirm' => [
                    'title' => (string) __('Delete rewrite'),
                    'message' => (string) __('Are you sure you want to delete this filter rewrite?'),
                ],
            ];
        }

        return $dataSource;
    }

    private function loadRawRows(array $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            if (isset($item['rewrite_id'])) {
                $ids[] = (int) $item['rewrite_id'];
            }
        }
        if ($ids === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('panth_seo_filter_rewrite'), ['rewrite_id', 'attribute_code', 'option_id', 'store_id'])
            ->where('rewrite_id IN (?)', $ids);
        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            $rows[(int) $row['rewrite_id']] = $row;
        }
        return $rows;
    }
}
