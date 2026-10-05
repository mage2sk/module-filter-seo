<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Controller\Adminhtml\FilterMeta;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\DuplicateException;
use Panth\FilterSeo\Controller\Adminhtml\AbstractAction;
use Panth\FilterSeo\Model\FilterUrl\PageCacheCleaner;

class Save extends AbstractAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_FilterSeo::filter';

    public function __construct(
        Context $context,
        private readonly ResourceConnection $resource,
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

        $id = (int) ($data['id'] ?? 0);
        $row = [
            'category_id'          => (int) ($data['category_id'] ?? 0),
            'attribute_code'       => (string) ($data['attribute_code'] ?? ''),
            'option_id'            => (int) ($data['option_id'] ?? 0),
            'store_id'             => (int) ($data['store_id'] ?? 0),
            'meta_title'           => (string) ($data['meta_title'] ?? ''),
            'meta_description'     => (string) ($data['meta_description'] ?? ''),
            'meta_keywords'        => (string) ($data['meta_keywords'] ?? ''),
            'breadcrumbs_priority' => (int) ($data['breadcrumbs_priority'] ?? 0),
        ];

        if ($row['category_id'] === 0 || $row['attribute_code'] === '') {
            $this->messageManager->addErrorMessage(__('Category ID and Attribute Code are required.'));
            return $this->backToForm($resultRedirect, $data, $id);
        }

        try {
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName('panth_seo_category_filter_meta');
            if ($id > 0) {
                $connection->update($table, $row, ['id = ?' => $id]);
            } else {
                $connection->insert($table, $row);
                $id = (int) $connection->lastInsertId($table);
            }
            $this->messageManager->addSuccessMessage(__('Filter meta saved.'));
            $this->pageCacheCleaner->clean();
            $this->dataPersistor->clear('panth_filterseo_filter_meta_form_data_source');
            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
            }
        } catch (DuplicateException) {
            $this->messageManager->addErrorMessage(
                __('Filter meta already exists for this category, attribute option and store view. Edit the existing row instead.')
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
        $this->dataPersistor->set('panth_filterseo_filter_meta_form_data_source', $data);

        return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
    }
}
