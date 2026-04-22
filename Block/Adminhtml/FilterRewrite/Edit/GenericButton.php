<?php
/**
 * Panth Filter SEO — base class for Filter Rewrite edit buttons.
 *
 * @copyright Copyright (c) Panth
 */
declare(strict_types=1);

namespace Panth\FilterSeo\Block\Adminhtml\FilterRewrite\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\Request\Http;

/**
 * Base class providing shared URL helpers for Filter URL Rewrite edit toolbar buttons.
 */
class GenericButton
{
    /**
     * @param Context $context
     */
    public function __construct(
        protected readonly Context $context
    ) {
    }

    /**
     * @return int|null
     */
    public function getRewriteId(): ?int
    {
        /** @var Http $request */
        $request = $this->context->getRequest();
        $id = $request->getParam('id');
        return $id !== null ? (int) $id : null;
    }

    /**
     * @param string $route
     * @param array<string, mixed> $params
     * @return string
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
