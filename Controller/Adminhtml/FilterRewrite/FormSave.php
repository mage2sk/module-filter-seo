<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Controller\Adminhtml\FilterRewrite;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\DuplicateException;
use Panth\FilterSeo\Controller\Adminhtml\AbstractAction;
use Panth\FilterSeo\Model\FilterUrl\PageCacheCleaner;
use Panth\FilterSeo\Model\FilterUrl\SlugConflict;

class FormSave extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_FilterSeo::filter';

    private const SLUG_PATTERN = '/^[\p{L}\p{N}_-]+$/u';

    public function __construct(
        Context $context,
        private readonly ResourceConnection $resource,
        private readonly SlugConflict $slugConflict,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly PageCacheCleaner $pageCacheCleaner
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $data = (array) $this->getRequest()->getPostValue();
        $resultRedirect = $this->resultRedirectFactory->create();

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $id = (int) ($data['rewrite_id'] ?? 0);
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('panth_seo_filter_rewrite');

        $row = [
            'attribute_code' => (string) ($data['attribute_code'] ?? ''),
            'option_id'      => (int) ($data['option_id'] ?? 0),
            'option_label'   => (string) ($data['option_label'] ?? ''),
            'rewrite_slug'   => (string) ($data['rewrite_slug'] ?? ''),
            'store_id'       => (int) ($data['store_id'] ?? 0),
            'is_active'      => (int) ($data['is_active'] ?? 1),
        ];

        if (!preg_match(self::SLUG_PATTERN, $row['rewrite_slug'])) {
            $this->messageManager->addErrorMessage(
                __('The URL slug may contain only letters, numbers, hyphens and underscores.')
            );
            return $this->backToForm($resultRedirect, $data, $id);
        }

        $conflict = $this->slugConflict->find(
            $row['attribute_code'],
            $row['rewrite_slug'],
            $row['option_id'],
            $row['store_id'],
            $id
        );
        if ($conflict !== null) {
            $this->messageManager->addErrorMessage(__(
                'The URL slug "%1" is already used by option "%2" of this attribute (rewrite ID %3). '
                . 'Each option needs its own slug so the filter URL can be resolved.',
                $row['rewrite_slug'],
                (string) $conflict['option_label'],
                (int) $conflict['rewrite_id']
            ));
            return $this->backToForm($resultRedirect, $data, $id);
        }

        try {
            if ($id > 0) {
                $connection->update($table, $row, ['rewrite_id = ?' => $id]);
            } else {
                $connection->insert($table, $row);
                $id = (int) $connection->lastInsertId($table);
            }
            $this->messageManager->addSuccessMessage(__('Filter rewrite saved.'));
            $this->pageCacheCleaner->clean();
            $this->dataPersistor->clear('panth_filterseo_filterrewrite_form_data_source');

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
            }
        } catch (DuplicateException) {
            $this->messageManager->addErrorMessage(
                __('A URL slug already exists for this attribute option and store view. Edit the existing row instead.')
            );
            return $this->backToForm($resultRedirect, $data, $id);
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('The record could not be saved.'));
            return $this->backToForm($resultRedirect, $data, $id);
        }

        return $resultRedirect->setPath('*/*/');
    }

    private function backToForm($resultRedirect, array $data, int $id)
    {
        $this->dataPersistor->set('panth_filterseo_filterrewrite_form_data_source', $data);

        return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
    }
}
