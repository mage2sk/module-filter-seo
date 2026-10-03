<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\FilterUrl;

use Panth\FilterSeo\Helper\Config;

class UrlBuilder
{
    public const FORMAT_SHORT = 'short';
    public const FORMAT_LONG  = 'long';

    public function __construct(
        private readonly RewriteRepository $rewriteRepository,
        private readonly Config $config
    ) {
    }

    public function build(string $categoryUrl, array $activeFilters, int $storeId): string
    {
        return $this->compose($categoryUrl, $activeFilters, $storeId, false);
    }

    public function buildWithout(
        string $categoryUrl,
        array $activeFilters,
        string $removeAttrCode,
        int $removeOptionId,
        int $storeId
    ): string {
        $remaining = array_filter($activeFilters, static function (array $f) use ($removeAttrCode, $removeOptionId) {
            return $f['attribute_code'] !== $removeAttrCode || (int) $f['option_id'] !== $removeOptionId;
        });

        return $this->compose($categoryUrl, array_values($remaining), $storeId, true);
    }

    public function hasSlug(string $attrCode, int $optionId, int $storeId): bool
    {
        return $optionId > 0 && $this->rewriteRepository->getSlug($attrCode, $optionId, $storeId) !== null;
    }

    private function compose(string $categoryUrl, array $activeFilters, int $storeId, bool $keepQueryOnly): string
    {
        if ($activeFilters === []) {
            return $categoryUrl;
        }

        [$slugPairs, $queryParams] = $this->resolveSlugPairs($activeFilters, $storeId);
        if ($slugPairs === []) {
            if ($keepQueryOnly && $queryParams !== []) {
                return $this->appendQuery($categoryUrl, $queryParams);
            }
            return $categoryUrl;
        }

        $format    = $this->getFormat($storeId);
        $separator = $this->getSeparator($storeId);

        $filterSegment = $this->buildFilterSegment($slugPairs, $format, $separator);

        return $this->appendQuery($this->injectSegment($categoryUrl, $filterSegment), $queryParams);
    }

    private function resolveSlugPairs(array $activeFilters, int $storeId): array
    {
        $pairs = [];
        $query = [];
        foreach ($activeFilters as $filter) {
            $attrCode = (string) $filter['attribute_code'];
            $optionId = (int) $filter['option_id'];
            $rawValue = isset($filter['value']) ? (string) $filter['value'] : ($optionId > 0 ? (string) $optionId : '');

            $slug = null;
            if ($optionId > 0 && ctype_digit($rawValue)) {
                $slug = $this->rewriteRepository->getSlug($attrCode, $optionId, $storeId);
            }

            if ($slug !== null) {
                $pairs[] = [$attrCode, $slug];
                continue;
            }

            if ($attrCode !== '' && $rawValue !== '') {
                $query[$attrCode] = $rawValue;
            }
        }

        usort($pairs, static fn(array $a, array $b) => strcmp($a[0], $b[0]));
        ksort($query);

        return [$pairs, $query];
    }

    private function appendQuery(string $url, array $queryParams): string
    {
        if ($queryParams === []) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($queryParams);
    }

    private function buildFilterSegment(array $slugPairs, string $format, string $separator): string
    {
        if ($format === self::FORMAT_LONG) {
            $parts = [];
            foreach ($slugPairs as [$attrCode, $slug]) {
                $parts[] = $attrCode . '/' . $slug;
            }
            return implode('/', $parts);
        }

        $parts = [];
        foreach ($slugPairs as [$attrCode, $slug]) {
            $parts[] = $attrCode . $separator . $slug;
        }
        return implode($separator, $parts);
    }

    private function injectSegment(string $url, string $segment): string
    {
        $parsed = parse_url($url);
        $path   = $parsed['path'] ?? '/';

        $suffix = '';
        if (str_ends_with($path, '.html')) {
            $path   = substr($path, 0, -5);
            $suffix = '.html';
        }

        $path = rtrim($path, '/') . '/' . $segment . $suffix;

        $result = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
        if (isset($parsed['port'])) {
            $result .= ':' . $parsed['port'];
        }
        $result .= $path;

        return $result;
    }

    private function getFormat(?int $storeId): string
    {
        $value = $this->config->getValue(
            Config::XML_FILTER_URL_FORMAT,
            $storeId
        );
        return $value === self::FORMAT_LONG ? self::FORMAT_LONG : self::FORMAT_SHORT;
    }

    private function getSeparator(?int $storeId): string
    {
        $value = $this->config->getValue(
            Config::XML_FILTER_URL_SEPARATOR,
            $storeId
        );
        return is_string($value) && $value !== '' ? $value : '-';
    }
}
