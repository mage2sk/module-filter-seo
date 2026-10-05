<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Plugin\FilterUrl;

use Magento\Catalog\Block\Category\View;
use Panth\FilterSeo\Model\FilterUrl\PageCacheCleaner;

class CategoryIdentitiesPlugin
{
    public function afterGetIdentities(View $subject, $result): array
    {
        $identities = is_array($result) ? $result : [];
        $identities[] = PageCacheCleaner::CACHE_TAG;

        return array_values(array_unique($identities));
    }
}
