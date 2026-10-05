<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Model\FilterUrl;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Panth\FilterSeo\Helper\Config;
use Panth\FilterSeo\Model\FilterUrl\RewriteRepository;
use Panth\FilterSeo\Model\FilterUrl\UrlParser;
use PHPUnit\Framework\TestCase;

class UrlParserTest extends TestCase
{
    private const SLUGS = [
        'color|red'        => 5,
        'color|light-blue' => 6,
        'color|light'      => 3,
        'size|xl'          => 7,
        'shoe_size|42'     => 9,
    ];

    /** @var string[] */
    private array $lookedUpPaths = [];

    private function parser(array $categories, array $config = [], ?RewriteRepository $repo = null): UrlParser
    {
        if ($repo === null) {
            $repo = $this->createStub(RewriteRepository::class);
            $repo->method('getOptionIdBySlug')->willReturnCallback(
                static fn(string $code, string $slug) => self::SLUGS[$code . '|' . $slug] ?? null
            );
        }

        $finder = $this->createStub(UrlFinderInterface::class);
        $finder->method('findOneByData')->willReturnCallback(function (array $data) use ($categories) {
            $path = $data[UrlRewrite::REQUEST_PATH];
            $this->lookedUpPaths[] = $path;
            if (!isset($categories[$path])) {
                return null;
            }
            $rewrite = $this->createStub(UrlRewrite::class);
            $rewrite->method('getEntityId')->willReturn($categories[$path]);
            return $rewrite;
        });

        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $path) => $config[$path] ?? null
        );

        return new UrlParser($repo, $finder, $storeManager, new Config($scopeConfig));
    }

    public function testParsesSingleShortFilter(): void
    {
        $result = $this->parser(['women/tops.html' => 12])->parse('/women/tops/color-red.html');

        $this->assertSame(['category_id' => 12, 'filters' => ['color' => 5]], $result);
    }

    public function testParsesMultipleShortFiltersWithHyphenatedSlug(): void
    {
        $result = $this->parser(['women.html' => 4])->parse('women/color-light-blue-size-xl.html');

        $this->assertSame(['category_id' => 4, 'filters' => ['color' => 6, 'size' => 7]], $result);
    }

    public function testAttributeCodesWithUnderscoreAreMatched(): void
    {
        $result = $this->parser(['shoes.html' => 8])->parse('shoes/shoe_size-42.html');

        $this->assertSame(['category_id' => 8, 'filters' => ['shoe_size' => 9]], $result);
    }

    public function testRejectsSegmentWithUnconsumedTrailingToken(): void
    {
        $this->assertNull($this->parser(['women.html' => 4])->parse('women/color-light-blue-extra.html'));
    }

    public function testCustomSeparatorIsHonoured(): void
    {
        $result = $this->parser(['women.html' => 4], [Config::XML_FILTER_URL_SEPARATOR => '~'])
            ->parse('women/color~red~size~xl.html');

        $this->assertSame(['category_id' => 4, 'filters' => ['color' => 5, 'size' => 7]], $result);
    }

    public function testParsesLongFormat(): void
    {
        $result = $this->parser(['women/tops.html' => 12], [Config::XML_FILTER_URL_FORMAT => 'long'])
            ->parse('women/tops/color/red/size/xl.html');

        $this->assertSame(['category_id' => 12, 'filters' => ['color' => 5, 'size' => 7]], $result);
    }

    public function testLongFormatWithUnknownSlugReturnsNull(): void
    {
        $this->assertNull(
            $this->parser(['women.html' => 4], [Config::XML_FILTER_URL_FORMAT => 'long'])
                ->parse('women/color/purple.html')
        );
    }

    public function testSingleSegmentPathIsNeverAFilterUrl(): void
    {
        $this->assertNull($this->parser(['color-red.html' => 1])->parse('color-red.html'));
        $this->assertSame([], $this->lookedUpPaths);
    }

    public function testUnknownCategoryReturnsNull(): void
    {
        $this->assertNull($this->parser([])->parse('women/color-red.html'));
    }

    public function testFallsBackToCategoryPathWithoutSuffix(): void
    {
        $result = $this->parser(['women/tops' => 12])->parse('women/tops/color-red.html');

        $this->assertSame(12, $result['category_id']);
        $this->assertContains('women/tops.html', $this->lookedUpPaths);
        $this->assertContains('women/tops', $this->lookedUpPaths);
    }

    public function testPathWithoutSuffixIsResolved(): void
    {
        $result = $this->parser(['women' => 4])->parse('women/color-red');

        $this->assertSame(['category_id' => 4, 'filters' => ['color' => 5]], $result);
    }

    public function testShortSegmentNeedsAtLeastTwoTokens(): void
    {
        $this->assertNull($this->parser(['women.html' => 4])->parse('women/red.html'));
    }

    public function testTooManyTokensAreRejectedWithoutLookups(): void
    {
        $repo = $this->createMock(RewriteRepository::class);
        $repo->expects($this->never())->method('getOptionIdBySlug');

        $segment = implode('-', array_fill(0, 33, 'a'));
        $this->assertNull($this->parser(['women.html' => 4], [], $repo)->parse('women/' . $segment . '.html'));
    }
}
