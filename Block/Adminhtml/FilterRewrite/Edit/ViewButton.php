<?php
/**
 * Panth Filter SEO — View on Storefront button for Filter Rewrite edit form.
 *
 * @copyright Copyright (c) Panth
 */
declare(strict_types=1);

namespace Panth\FilterSeo\Block\Adminhtml\FilterRewrite\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\FilterSeo\Api\FilterRewriteRepositoryInterface;

/**
 * @see ButtonProviderInterface
 */
class ViewButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @param Context $context
     * @param FilterRewriteRepositoryInterface $rewriteRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        private readonly FilterRewriteRepositoryInterface $rewriteRepository,
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
            'on_click' => sprintf("window.open('%s', '_blank');", $url),
            'sort_order' => 15,
        ];
    }

    /**
     * @param int $rewriteId
     * @return string
     */
    private function resolveFrontendUrl(int $rewriteId): string
    {
        try {
            $row = $this->rewriteRepository->getById($rewriteId);
        } catch (\Throwable) {
            return '';
        }

        $attributeCode = (string) $row->getData('attribute_code');
        $optionId = (int) $row->getData('option_id');

        if ($attributeCode === '' || $optionId === 0) {
            return '';
        }

        $storeId = (int) $row->getData('store_id');

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
