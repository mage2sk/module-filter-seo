<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\FilterUrl;

use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Panth\FilterSeo\Helper\Config;

class UrlParser
{
    private const MAX_SHORT_TOKENS = 32;

    public function __construct(
        private readonly RewriteRepository $rewriteRepository,
        private readonly UrlFinderInterface $urlFinder,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
    }

    public function parse(string $requestPath): ?array
    {
        $requestPath = ltrim($requestPath, '/');

        $suffix = '';
        if (str_ends_with($requestPath, '.html')) {
            $requestPath = substr($requestPath, 0, -5);
            $suffix = '.html';
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        $segments = explode('/', $requestPath);

        $segmentCount = count($segments);

        for ($i = $segmentCount - 1; $i >= 1; $i--) {
            $filterSegments = array_slice($segments, $i);

            $filters = $this->parseFilterSegments($filterSegments, $storeId);
            if ($filters === null || $filters === []) {
                continue;
            }

            $categoryPath = implode('/', array_slice($segments, 0, $i)) . $suffix;
            $categoryId = $this->resolveCategoryId($categoryPath, $storeId);
            if ($categoryId === null) {
                continue;
            }

            return [
                'category_id' => $categoryId,
                'filters'     => $filters,
            ];
        }

        return null;
    }

    private function resolveCategoryId(string $requestPath, int $storeId): ?int
    {
        $rewrite = $this->urlFinder->findOneByData([
            UrlRewrite::REQUEST_PATH => $requestPath,
            UrlRewrite::STORE_ID     => $storeId,
            UrlRewrite::ENTITY_TYPE  => 'category',
        ]);

        if ($rewrite === null) {
            $pathNoSuffix = preg_replace('/\.html$/', '', $requestPath);
            if ($pathNoSuffix !== $requestPath) {
                $rewrite = $this->urlFinder->findOneByData([
                    UrlRewrite::REQUEST_PATH => $pathNoSuffix,
                    UrlRewrite::STORE_ID     => $storeId,
                    UrlRewrite::ENTITY_TYPE  => 'category',
                ]);
            }
        }

        return $rewrite !== null ? (int) $rewrite->getEntityId() : null;
    }

    private function parseFilterSegments(array $segments, int $storeId): ?array
    {
        $format = $this->config->getValue(Config::XML_FILTER_URL_FORMAT, $storeId);

        if ($format === UrlBuilder::FORMAT_LONG) {
            return $this->parseLongFormat($segments, $storeId);
        }

        return $this->parseShortFormat($segments, $storeId);
    }

    private function parseLongFormat(array $segments, int $storeId): ?array
    {
        if (count($segments) % 2 !== 0) {
            return null;
        }

        $filters = [];
        for ($i = 0, $count = count($segments); $i < $count; $i += 2) {
            $attrCode = $segments[$i];
            $optionId = $this->rewriteRepository->getOptionIdBySlug($attrCode, $segments[$i + 1], $storeId);
            if ($optionId === null) {
                return null;
            }

            $filters[$attrCode] = $optionId;
        }

        return $filters;
    }

    private function parseShortFormat(array $segments, int $storeId): ?array
    {
        if (count($segments) !== 1) {
            return null;
        }

        $separator = $this->config->getValue(Config::XML_FILTER_URL_SEPARATOR, $storeId);
        $separator = is_string($separator) && $separator !== '' ? $separator : '-';

        $tokens = explode($separator, $segments[0]);
        $count = count($tokens);
        if ($count < 2 || $count > self::MAX_SHORT_TOKENS) {
            return null;
        }

        $memo = [];
        return $this->matchShortTokens($tokens, 0, $separator, $storeId, $memo);
    }

    private function matchShortTokens(array $tokens, int $start, string $separator, int $storeId, array &$memo): ?array
    {
        $count = count($tokens);
        if ($start === $count) {
            return [];
        }
        if (array_key_exists($start, $memo)) {
            return $memo[$start];
        }
        $memo[$start] = null;

        for ($attrEnd = $start; $attrEnd < $count - 1; $attrEnd++) {
            $attrCode = implode($separator, array_slice($tokens, $start, $attrEnd - $start + 1));
            for ($slugEnd = $count - 1; $slugEnd > $attrEnd; $slugEnd--) {
                $slug = implode($separator, array_slice($tokens, $attrEnd + 1, $slugEnd - $attrEnd));
                $optionId = $this->rewriteRepository->getOptionIdBySlug($attrCode, $slug, $storeId);
                if ($optionId === null) {
                    continue;
                }

                $rest = $this->matchShortTokens($tokens, $slugEnd + 1, $separator, $storeId, $memo);
                if ($rest === null) {
                    continue;
                }

                $filters = [$attrCode => $optionId];
                foreach ($rest as $restCode => $restOptionId) {
                    $filters[$restCode] = $restOptionId;
                }
                $memo[$start] = $filters;
                return $filters;
            }
        }

        return null;
    }
}
