<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Block\Adminhtml;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\UrlInterface;
use Panth\FilterSeo\Block\Adminhtml\FilterMeta\Edit as Meta;
use Panth\FilterSeo\Block\Adminhtml\FilterRewrite\Edit as Rewrite;
use Panth\FilterSeo\Model\FilterUrl\ViewUrlResolver;
use PHPUnit\Framework\TestCase;

class EditButtonsTest extends TestCase
{
    private function context(?string $id): Context
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn($k) => $k === 'id' ? $id : null);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn($route, $params = []) => '/admin/' . $route . ($params ? '?' . http_build_query($params) : '')
        );
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getUrlBuilder')->willReturn($url);
        return $context;
    }

    private function resource($row): ResourceConnection
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchRow')->willReturn($row);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return $resource;
    }

    public function testGenericButtonsReadIdAndBuildUrls(): void
    {
        $meta = new Meta\GenericButton($this->context('7'));
        $rewrite = new Rewrite\GenericButton($this->context(null));

        $this->assertSame(7, $meta->getId());
        $this->assertNull($rewrite->getRewriteId());
        $this->assertSame('/admin/x/y?a=1', $meta->getUrl('x/y', ['a' => 1]));
    }

    public function testDeleteButtonsOnlyForExistingRecords(): void
    {
        $this->assertSame([], (new Meta\DeleteButton($this->context(null)))->getButtonData());
        $this->assertSame([], (new Rewrite\DeleteButton($this->context(null)))->getButtonData());

        $meta = (new Meta\DeleteButton($this->context('3')))->getButtonData();
        $rewrite = (new Rewrite\DeleteButton($this->context('4')))->getButtonData();

        $this->assertStringContainsString('/admin/panth_filterseo/filtermeta/delete?id=3', $meta['on_click']);
        $this->assertStringContainsString('/admin/panth_filterseo/filterrewrite/delete?id=4', $rewrite['on_click']);
        $this->assertSame(20, $meta['sort_order']);
    }

    public function testBackButtonsPointToTheirGrids(): void
    {
        $meta = new Meta\BackButton($this->context(null));
        $rewrite = new Rewrite\BackButton($this->context(null));

        $this->assertSame('/admin/panth_filterseo/filtermeta/index', $meta->getBackUrl());
        $this->assertSame('/admin/panth_filterseo/filterrewrite/index', $rewrite->getBackUrl());
        $this->assertSame("location.href = '/admin/panth_filterseo/filtermeta/index';", $meta->getButtonData()['on_click']);
    }

    public function testSaveButtonsTriggerFormEvents(): void
    {
        $save = (new Meta\SaveButton($this->context(null)))->getButtonData();
        $continue = (new Rewrite\SaveAndContinueButton($this->context(null)))->getButtonData();

        $this->assertSame('save', $save['data_attribute']['mage-init']['button']['event']);
        $this->assertSame('saveAndContinueEdit', $continue['data_attribute']['mage-init']['button']['event']);
        $this->assertGreaterThan($continue['sort_order'], $save['sort_order']);
    }

    public function testMetaViewButtonOpensEscapedStorefrontUrl(): void
    {
        $resolver = $this->createMock(ViewUrlResolver::class);
        $resolver->expects($this->once())->method('resolveForCategory')->with(14, 'color', 5, 1)
            ->willReturn("https://shop.test/a'b<c>.html");

        $data = (new Meta\ViewButton(
            $this->context('2'),
            $this->resource(['category_id' => '14', 'attribute_code' => 'color', 'option_id' => '5', 'store_id' => '1']),
            $resolver
        ))->getButtonData();

        $this->assertSame(
            "window.open(\"https://shop.test/a\\u0027b\\u003Cc\\u003E.html\", '_blank', 'noopener'); return false;",
            $data['on_click']
        );
        $this->assertSame(15, $data['sort_order']);
    }

    public function testRewriteViewButtonUsesCategorylessResolution(): void
    {
        $resolver = $this->createMock(ViewUrlResolver::class);
        $resolver->expects($this->once())->method('resolveWithoutCategory')->with('size', 7, 0)
            ->willReturn('https://shop.test/men/size-xl.html');

        $data = (new Rewrite\ViewButton(
            $this->context('9'),
            $this->resource(['attribute_code' => 'size', 'option_id' => '7', 'store_id' => '0']),
            $resolver
        ))->getButtonData();

        $this->assertStringContainsString('"https://shop.test/men/size-xl.html"', $data['on_click']);
    }

    public function testViewButtonsHiddenWhenNoIdRowOrUrl(): void
    {
        $resolver = $this->createStub(ViewUrlResolver::class);
        $resolver->method('resolveForCategory')->willReturn('');

        $this->assertSame([], (new Meta\ViewButton($this->context(null), $this->resource(false), $resolver))->getButtonData());
        $this->assertSame([], (new Meta\ViewButton($this->context('2'), $this->resource(false), $resolver))->getButtonData());
        $this->assertSame([], (new Meta\ViewButton($this->context('2'), $this->resource(['category_id' => 1]), $resolver))->getButtonData());
        $this->assertSame([], (new Rewrite\ViewButton($this->context(null), $this->resource(false), $resolver))->getButtonData());
    }
}
