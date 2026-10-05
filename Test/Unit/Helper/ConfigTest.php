<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Panth\FilterSeo\Helper\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private function config(array $values = [], array $flags = []): Config
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $path) => $values[$path] ?? null
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn(string $path) => (bool) ($flags[$path] ?? false)
        );

        return new Config($scopeConfig);
    }

    public function testNoindexMinFacetsDefaultsToTwoWhenUnset(): void
    {
        $this->assertSame(2, $this->config()->getNoindexMinFacets(1));
    }

    public function testNoindexMinFacetsDefaultsToTwoForZeroOrNegative(): void
    {
        $this->assertSame(2, $this->config([Config::XML_FILTER_NOINDEX_MIN_FACETS => '0'])->getNoindexMinFacets());
        $this->assertSame(2, $this->config([Config::XML_FILTER_NOINDEX_MIN_FACETS => '-3'])->getNoindexMinFacets());
    }

    public function testNoindexMinFacetsHonoursConfiguredValue(): void
    {
        $this->assertSame(1, $this->config([Config::XML_FILTER_NOINDEX_MIN_FACETS => '1'])->getNoindexMinFacets());
        $this->assertSame(4, $this->config([Config::XML_FILTER_NOINDEX_MIN_FACETS => '4'])->getNoindexMinFacets());
    }

    public function testFlagsAreReadFromTheirOwnPaths(): void
    {
        $config = $this->config([], [
            Config::XML_FILTER_URL_ENABLED => true,
            Config::XML_FILTER_NOINDEX_COMBINATIONS => false,
            Config::XML_FILTER_META_ENABLED => true,
        ]);

        $this->assertTrue($config->isFilterUrlEnabled(1));
        $this->assertFalse($config->isNoindexCombinationsEnabled(1));
        $this->assertTrue($config->isFilterMetaEnabled(1));
    }

    public function testFlagsAreFalseWhenNothingConfigured(): void
    {
        $config = $this->config();

        $this->assertFalse($config->isFilterUrlEnabled());
        $this->assertFalse($config->isNoindexCombinationsEnabled());
        $this->assertFalse($config->isFilterMetaEnabled());
    }

    public function testIsEnabledHasNoMasterSwitch(): void
    {
        $this->assertTrue($this->config()->isEnabled(3));
    }

    public function testGetValueUsesStoreScopeAndStoreId(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_FILTER_URL_FORMAT, ScopeInterface::SCOPE_STORE, 7)
            ->willReturn('long');

        $this->assertSame('long', (new Config($scopeConfig))->getValue(Config::XML_FILTER_URL_FORMAT, 7));
    }
}
