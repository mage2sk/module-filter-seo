<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Controller\Router;

use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Url;
use Panth\FilterSeo\Controller\Router\FilterRouter;
use Panth\FilterSeo\Helper\Config;
use Panth\FilterSeo\Model\FilterUrl\UrlParser;
use PHPUnit\Framework\TestCase;

class FilterRouterTest extends TestCase
{
    private function config(bool $enabled = true): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isFilterUrlEnabled')->willReturn($enabled);
        return $config;
    }

    private function request(string $pathInfo): Http
    {
        $request = $this->createStub(Http::class);
        $request->method('getPathInfo')->willReturn($pathInfo);
        return $request;
    }

    public function testReturnsNullWhenDisabled(): void
    {
        $parser = $this->createMock(UrlParser::class);
        $parser->expects($this->never())->method('parse');

        $router = new FilterRouter($this->createStub(ActionFactory::class), $parser, $this->config(false));

        $this->assertNull($router->match($this->request('/women/color-red.html')));
    }

    public function testIgnoresEmptyAdminAndRestPaths(): void
    {
        $parser = $this->createMock(UrlParser::class);
        $parser->expects($this->never())->method('parse');
        $router = new FilterRouter($this->createStub(ActionFactory::class), $parser, $this->config());

        $this->assertNull($router->match($this->request('/')));
        $this->assertNull($router->match($this->request('/admin/catalog/product')));
        $this->assertNull($router->match($this->request('/rest/V1/products')));
    }

    public function testReturnsNullWhenPathIsNotAFilterUrl(): void
    {
        $parser = $this->createMock(UrlParser::class);
        $parser->expects($this->once())->method('parse')->with('women/unknown.html')->willReturn(null);

        $router = new FilterRouter($this->createStub(ActionFactory::class), $parser, $this->config());

        $this->assertNull($router->match($this->request('/women/unknown.html/')));
    }

    public function testRewritesRequestToCategoryViewAndForwards(): void
    {
        $parser = $this->createStub(UrlParser::class);
        $parser->method('parse')->willReturn(['category_id' => 12, 'filters' => ['color' => 5, 'size' => 7]]);

        $params = [];
        $request = $this->createMock(Http::class);
        $request->method('getPathInfo')->willReturn('/women/color-red-size-xl.html');
        $request->expects($this->once())->method('setModuleName')->with('catalog');
        $request->expects($this->once())->method('setControllerName')->with('category');
        $request->expects($this->once())->method('setActionName')->with('view');
        $request->method('setParam')->willReturnCallback(function ($key, $value) use (&$params, $request) {
            $params[$key] = $value;
            return $request;
        });
        $request->expects($this->once())->method('setAlias')
            ->with(Url::REWRITE_REQUEST_PATH_ALIAS, 'women/color-red-size-xl.html');

        $action = $this->createStub(ActionInterface::class);
        $factory = $this->createMock(ActionFactory::class);
        $factory->expects($this->once())->method('create')->with(Forward::class)->willReturn($action);

        $router = new FilterRouter($factory, $parser, $this->config());

        $this->assertSame($action, $router->match($request));
        $this->assertSame(['id' => 12, 'color' => '5', 'size' => '7'], $params);
    }
}
