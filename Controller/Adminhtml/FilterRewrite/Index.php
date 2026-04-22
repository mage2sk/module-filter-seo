<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Controller\Adminhtml\FilterRewrite;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Panth\FilterSeo\Controller\Adminhtml\AbstractAction;

class Index extends AbstractAction
{
    public const ADMIN_RESOURCE = 'Panth_FilterSeo::filter';

    public function __construct(Context $context, private readonly PageFactory $pageFactory)
    {
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Panth_FilterSeo::filter_rewrite');
        $page->getConfig()->getTitle()->prepend(__('SEO Filter URL Rewrites'));
        return $page;
    }
}
