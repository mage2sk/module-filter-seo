<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Controller\Adminhtml\FilterRewrite;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\ResultFactory;
use Panth\FilterSeo\Controller\Adminhtml\AbstractAction;
use Panth\FilterSeo\Model\FilterUrl\PageCacheCleaner;
use Panth\FilterSeo\Model\FilterUrl\SlugConflict;

class Save extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_FilterSeo::filter';

    private const SLUG_PATTERN = '/^[\p{L}\p{N}_-]+$/u';

    public function __construct(
        Context $context,
        private readonly ResourceConnection $resource,
        private readonly SlugConflict $slugConflict,
        private readonly PageCacheCleaner $pageCacheCleaner
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $error      = false;
        $messages   = [];

        $items = $this->getRequest()->getParam('items', []);
        if (!is_array($items) || $items === []) {
            $error      = true;
            $messages[] = __('Please correct the data sent.');
            $resultJson->setData(['messages' => $messages, 'error' => $error]);
            return $resultJson;
        }

        $connection = $this->resource->getConnection();
        $tableName  = $this->resource->getTableName('panth_seo_filter_rewrite');
        $updated    = false;

        foreach ($items as $id => $data) {
            if (!is_array($data)) {
                continue;
            }
            if (array_key_exists('rewrite_slug', $data)
                && (!is_scalar($data['rewrite_slug'])
                    || !preg_match(self::SLUG_PATTERN, (string) $data['rewrite_slug']))
            ) {
                $error      = true;
                $messages[] = '[ID: ' . (int) $id . '] '
                    . __('The URL slug may contain only letters, numbers, hyphens and underscores.');
                continue;
            }
            if (array_key_exists('rewrite_slug', $data)
                && $this->slugConflict->findForRow((int) $id, (string) $data['rewrite_slug']) !== null
            ) {
                $error      = true;
                $messages[] = '[ID: ' . (int) $id . '] '
                    . __('This URL slug is already used by another option of the same attribute.');
                continue;
            }
            try {
                $updateData = [];
                foreach (['rewrite_slug', 'is_active', 'option_label', 'attribute_code', 'store_id'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $updateData[$field] = $data[$field];
                    }
                }
                if ($updateData !== []) {
                    $connection->update(
                        $tableName,
                        $updateData,
                        ['rewrite_id = ?' => (int) $id]
                    );
                    $updated = true;
                }
            } catch (\Throwable $e) {
                $error      = true;
                $messages[] = '[ID: ' . (int) $id . '] Could not save record.';
            }
        }

        if ($updated) {
            $this->pageCacheCleaner->clean();
        }

        $resultJson->setData([
            'messages' => $messages ?: [__('Record(s) saved.')],
            'error'    => $error,
        ]);

        return $resultJson;
    }
}
