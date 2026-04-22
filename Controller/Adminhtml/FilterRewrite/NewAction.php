<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Controller\Adminhtml\FilterRewrite;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Panth\FilterSeo\Controller\Adminhtml\AbstractAction;

class NewAction extends AbstractAction
{
    public const ADMIN_RESOURCE = 'Panth_FilterSeo::filter';

    public function __construct(
        Context $context,
        private readonly ForwardFactory $forwardFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @return \Magento\Backend\Model\View\Result\Forward
     */
    public function execute()
    {
        return $this->forwardFactory->create()->forward('edit');
    }
}
