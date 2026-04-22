<?php
/**
 * Panth Filter SEO — mass delete controller for the Category Filter Meta grid.
 *
 * @copyright Copyright (c) Panth
 */
declare(strict_types=1);

namespace Panth\FilterSeo\Controller\Adminhtml\FilterMeta;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Panth\FilterSeo\Model\ResourceModel\CategoryFilterMeta\CollectionFactory;

/**
 * Deletes all rows matched by the listing filter selection.
 */
class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_FilterSeo::filter_meta';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $count = 0;
        foreach ($collection as $item) {
            $item->delete();
            $count++;
        }

        $this->messageManager->addSuccessMessage(
            (string) __('A total of %1 record(s) were deleted.', $count)
        );

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('*/*/index');
    }
}
