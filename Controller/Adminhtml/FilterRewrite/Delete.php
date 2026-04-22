<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Controller\Adminhtml\FilterRewrite;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use Panth\FilterSeo\Controller\Adminhtml\AbstractAction;

class Delete extends AbstractAction implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_FilterSeo::filter';

    public function __construct(Context $context, private readonly ResourceConnection $resource)
    {
        parent::__construct($context);
    }

    /**
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('id');
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($id > 0) {
            try {
                $this->resource->getConnection()->delete(
                    $this->resource->getTableName('panth_seo_filter_rewrite'),
                    ['rewrite_id = ?' => $id]
                );
                $this->messageManager->addSuccessMessage(__('Filter rewrite deleted.'));
            } catch (\Throwable $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return $resultRedirect->setPath('*/*/');
    }
}
