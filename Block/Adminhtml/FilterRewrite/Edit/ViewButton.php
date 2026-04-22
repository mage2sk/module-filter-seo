<?php
/**
 * Panth Filter SEO — View on Storefront button for Filter Rewrite edit form.
 *
 * @copyright Copyright (c) Panth
 */
declare(strict_types=1);

namespace Panth\FilterSeo\Block\Adminhtml\FilterRewrite\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @see ButtonProviderInterface
 */
class ViewButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @param Context $context
     * @param ResourceConnection $resource
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        private readonly ResourceConnection $resource,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $id = $this->getRewriteId();
        if ($id === null) {
            return [];
        }

        $url = $this->resolveFrontendUrl($id);
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

    /**
     * @param int $rewriteId
     * @return string
     */
    private function resolveFrontendUrl(int $rewriteId): string
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('panth_seo_filter_rewrite');

        $row = $connection->fetchRow(
            $connection->select()->from($table)->where('rewrite_id = ?', $rewriteId)
        );
        if (!$row) {
            return '';
        }

        $attributeCode = (string) ($row['attribute_code'] ?? '');
        $optionId = (int) ($row['option_id'] ?? 0);
        if ($attributeCode === '' || $optionId === 0) {
            return '';
        }

        $storeId = (int) ($row['store_id'] ?? 0);
        try {
            $store = $storeId === 0
                ? $this->storeManager->getDefaultStoreView()
                : $this->storeManager->getStore($storeId);
        } catch (\Throwable) {
            return '';
        }

        if ($store === null) {
            return '';
        }

        $baseUrl = (string) $store->getBaseUrl();
        if ($baseUrl === '') {
            return '';
        }

        return sprintf(
            '%scatalogsearch/result/?%s=%d',
            rtrim($baseUrl, '/') . '/',
            rawurlencode($attributeCode),
            $optionId
        );
    }
}
