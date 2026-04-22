<?php
/**
 * Panth Filter SEO — View on Storefront button for Filter Meta edit form.
 *
 * @copyright Copyright (c) Panth
 */
declare(strict_types=1);

namespace Panth\FilterSeo\Block\Adminhtml\FilterMeta\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Panth\FilterSeo\Model\FilterUrl\ViewUrlResolver;

/**
 * @see ButtonProviderInterface
 */
class ViewButton extends GenericButton implements ButtonProviderInterface
{
    public function __construct(
        Context $context,
        private readonly ResourceConnection $resource,
        private readonly ViewUrlResolver $viewUrlResolver
    ) {
        parent::__construct($context);
    }

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $id = $this->getId();
        if ($id === null) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('panth_seo_category_filter_meta');
        $row = $connection->fetchRow(
            $connection->select()->from($table)->where('id = ?', $id)
        );
        if (!$row) {
            return [];
        }

        $url = $this->viewUrlResolver->resolveForCategory(
            (int) ($row['category_id'] ?? 0),
            (string) ($row['attribute_code'] ?? ''),
            (int) ($row['option_id'] ?? 0),
            (int) ($row['store_id'] ?? 0)
        );
        if ($url === '') {
            return [];
        }

        return [
            'label' => __('View on Storefront'),
            'class' => 'view',
            'on_click' => sprintf("window.open('%s', '_blank'); return false;", $url),
            'sort_order' => 15,
        ];
    }
}
