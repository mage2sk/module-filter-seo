<?php
/**
 * Panth Filter SEO — Category Filter Meta actions column.
 *
 * @copyright Copyright (c) Panth
 */
declare(strict_types=1);

namespace Panth\FilterSeo\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Panth\FilterSeo\Model\FilterUrl\ViewUrlResolver;

/**
 * Renders Edit / View on Storefront / Delete actions in the Category
 * Filter Meta grid.
 */
class FilterMetaActions extends Column
{
    public const URL_PATH_EDIT = 'panth_filterseo/filtermeta/edit';
    public const URL_PATH_DELETE = 'panth_filterseo/filtermeta/delete';

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly ViewUrlResolver $viewUrlResolver,
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

        $name = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $id = $item['id'] ?? null;
            if ($id === null) {
                continue;
            }

            $item[$name]['edit'] = [
                'href' => $this->urlBuilder->getUrl(self::URL_PATH_EDIT, ['id' => $id]),
                'label' => (string) __('Edit'),
            ];

            $viewUrl = $this->viewUrlResolver->resolveForCategory(
                (int) ($item['category_id'] ?? 0),
                (string) ($item['attribute_code'] ?? ''),
                (int) ($item['option_id'] ?? 0),
                (int) ($item['store_id'] ?? 0)
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
                'confirm' => [
                    'title' => (string) __('Delete filter meta'),
                    'message' => (string) __('Are you sure you want to delete this filter meta record?'),
                ],
            ];
        }

        return $dataSource;
    }
}
