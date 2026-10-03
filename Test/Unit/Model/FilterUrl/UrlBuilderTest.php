<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Model\FilterUrl;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Panth\FilterSeo\Helper\Config;
use Panth\FilterSeo\Model\FilterUrl\RewriteRepository;
use Panth\FilterSeo\Model\FilterUrl\UrlBuilder;
use PHPUnit\Framework\TestCase;

class UrlBuilderTest extends TestCase
{
    private const SLUGS = [
        'color|5' => 'red',
        'color|6' => 'light-blue',
        'size|7'  => 'xl',
    ];

    private function builder(array $config = [], ?RewriteRepository $repo = null): UrlBuilder
    {
        if ($repo === null) {
            $repo = $this->createStub(RewriteRepository::class);
            $repo->method('getSlug')->willReturnCallback(
                static fn(string $code, int $id) => self::SLUGS[$code . '|' . $id] ?? null
            );
        }
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $path) => $config[$path] ?? null
        );

        return new UrlBuilder($repo, new Config($scopeConfig));
    }

    private static function f(string $code, int $optionId, ?string $value = null): array
    {
        $filter = ['attribute_code' => $code, 'option_id' => $optionId];
        if ($value !== null) {
            $filter['value'] = $value;
        }
        return $filter;
    }

    public function testNoFiltersReturnsCategoryUrlUnchanged(): void
    {
        $this->assertSame(
            'https://shop.test/women.html',
            $this->builder()->build('https://shop.test/women.html', [], 1)
        );
    }

    public function testShortFormatSortsByAttributeAndKeepsHtmlSuffix(): void
    {
        $url = $this->builder()->build(
            'https://shop.test/women/tops.html',
            [self::f('size', 7), self::f('color', 5)],
            1
        );

        $this->assertSame('https://shop.test/women/tops/color-red-size-xl.html', $url);
    }

    public function testLongFormatUsesPathPairs(): void
    {
        $url = $this->builder([Config::XML_FILTER_URL_FORMAT => 'long'])->build(
            'https://shop.test/women.html',
            [self::f('color', 6), self::f('size', 7)],
            1
        );

        $this->assertSame('https://shop.test/women/color/light-blue/size/xl.html', $url);
    }

    public function testCustomSeparatorIsUsedInShortFormat(): void
    {
        $url = $this->builder([Config::XML_FILTER_URL_SEPARATOR => '_'])->build(
            'https://shop.test/women.html',
            [self::f('color', 5), self::f('size', 7)],
            1
        );

        $this->assertSame('https://shop.test/women/color_red_size_xl.html', $url);
    }

    public function testUnknownFormatFallsBackToShort(): void
    {
        $url = $this->builder([Config::XML_FILTER_URL_FORMAT => 'weird'])
            ->build('https://shop.test/women.html', [self::f('color', 5)], 1);

        $this->assertSame('https://shop.test/women/color-red.html', $url);
    }

    public function testFiltersWithoutSlugBecomeSortedQueryParameters(): void
    {
        $url = $this->builder()->build(
            'https://shop.test/women.html',
            [self::f('price', 10, '10-20'), self::f('color', 5), self::f('material', 99)],
            1
        );

        $this->assertSame('https://shop.test/women/color-red.html?material=99&price=10-20', $url);
    }

    public function testMultiValueRawStringIsNotSlugged(): void
    {
        $url = $this->builder()->build(
            'https://shop.test/women.html',
            [self::f('color', 5, '5,6'), self::f('size', 7)],
            1
        );

        $this->assertSame('https://shop.test/women/size-xl.html?color=5%2C6', $url);
    }

    public function testBuildReturnsCategoryUrlWhenNothingIsSluggable(): void
    {
        $this->assertSame(
            'https://shop.test/women.html',
            $this->builder()->build('https://shop.test/women.html', [self::f('price', 0, '10-20')], 1)
        );
    }

    public function testTrailingSlashPathWithoutSuffix(): void
    {
        $this->assertSame(
            'https://shop.test/women/color-red',
            $this->builder()->build('https://shop.test/women/', [self::f('color', 5)], 1)
        );
    }

    public function testSchemeHostAndPortArePreserved(): void
    {
        $this->assertSame(
            'http://shop.test:8080/women/color-red.html',
            $this->builder()->build('http://shop.test:8080/women.html', [self::f('color', 5)], 1)
        );
    }

    public function testBuildWithoutRemovesTheMatchingFilterOnly(): void
    {
        $url = $this->builder()->buildWithout(
            'https://shop.test/women.html',
            [self::f('color', 5), self::f('color', 6), self::f('size', 7)],
            'color',
            5,
            1
        );

        $this->assertSame('https://shop.test/women/color-light-blue-size-xl.html', $url);
    }

    public function testBuildWithoutLastFilterReturnsCategoryUrl(): void
    {
        $this->assertSame(
            'https://shop.test/women.html',
            $this->builder()->buildWithout('https://shop.test/women.html', [self::f('color', 5)], 'color', 5, 1)
        );
    }

    public function testBuildWithoutKeepsRemainingQueryOnlyFilters(): void
    {
        $url = $this->builder()->buildWithout(
            'https://shop.test/women.html',
            [self::f('color', 5), self::f('price', 0, '10-20')],
            'color',
            5,
            1
        );

        $this->assertSame('https://shop.test/women.html?price=10-20', $url);
    }

    public function testHasSlugShortCircuitsForNonPositiveOption(): void
    {
        $repo = $this->createMock(RewriteRepository::class);
        $repo->expects($this->never())->method('getSlug');

        $this->assertFalse($this->builder([], $repo)->hasSlug('color', 0, 1));
    }

    public function testHasSlugReflectsRepository(): void
    {
        $builder = $this->builder();

        $this->assertTrue($builder->hasSlug('color', 5, 1));
        $this->assertFalse($builder->hasSlug('color', 404, 1));
    }
}
