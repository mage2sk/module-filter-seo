<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Block\Adminhtml\FilterMeta\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\Request\Http;

class GenericButton
{
    public function __construct(
        protected readonly Context $context
    ) {
    }

    public function getId(): ?int
    {
        $request = $this->context->getRequest();
        $id = $request->getParam('id');
        return $id !== null ? (int) $id : null;
    }

    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
