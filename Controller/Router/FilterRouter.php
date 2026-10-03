<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Controller\Router;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\Url;
use Panth\FilterSeo\Helper\Config;
use Panth\FilterSeo\Model\FilterUrl\UrlParser;

class FilterRouter implements RouterInterface
{
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly UrlParser $urlParser,
        private readonly Config $config
    ) {
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        if (!$this->config->isEnabled() || !$this->config->isFilterUrlEnabled()) {
            return null;
        }

        $pathInfo = trim((string) $request->getPathInfo(), '/');

        if ($pathInfo === '' || str_starts_with($pathInfo, 'admin') || str_starts_with($pathInfo, 'rest/')) {
            return null;
        }

        $result = $this->urlParser->parse($pathInfo);
        if ($result === null) {
            return null;
        }

        $categoryId = $result['category_id'];
        $filters    = $result['filters'];

        $request->setModuleName('catalog');
        $request->setControllerName('category');
        $request->setActionName('view');
        $request->setParam('id', $categoryId);

        foreach ($filters as $attrCode => $optionId) {
            $request->setParam($attrCode, (string) $optionId);
        }

        $request->setAlias(
            Url::REWRITE_REQUEST_PATH_ALIAS,
            $pathInfo
        );

        return $this->actionFactory->create(
            \Magento\Framework\App\Action\Forward::class
        );
    }
}
