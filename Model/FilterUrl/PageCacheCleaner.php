<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Model\FilterUrl;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Indexer\CacheContextFactory;

class PageCacheCleaner
{
    public const CACHE_TAG = 'panth_fseo';

    public function __construct(
        private readonly ManagerInterface $eventManager,
        private readonly CacheContextFactory $cacheContextFactory
    ) {
    }

    public function clean(): void
    {
        $cacheContext = $this->cacheContextFactory->create();
        $cacheContext->registerTags([self::CACHE_TAG]);
        $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $cacheContext]);
    }
}
