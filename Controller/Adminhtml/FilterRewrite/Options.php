<?php
/**
 * Panth Filter SEO — admin AJAX: return attribute options as JSON.
 *
 * @copyright Copyright (c) Panth
 */
declare(strict_types=1);

namespace Panth\FilterSeo\Controller\Adminhtml\FilterRewrite;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * GET /admin/panth_filterseo/filterrewrite/options?attribute_code=color
 * Returns [{value:49,label:'Black'}, …] for the given attribute.
 */
class Options extends Action implements HttpGetActionInterface
{
    /**
     * @see Panth_FilterSeo::filter_rewrite
     */
    public const ADMIN_RESOURCE = 'Panth_FilterSeo::filter_rewrite';

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param AttributeOptionManagementInterface $optionManagement
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly AttributeOptionManagementInterface $optionManagement
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();
        $attributeCode = (string) $this->getRequest()->getParam('attribute_code', '');
        $attributeCode = preg_replace('/[^a-z0-9_]/i', '', $attributeCode) ?? '';

        if ($attributeCode === '') {
            return $result->setData(['options' => []]);
        }

        try {
            $options = $this->optionManagement->getItems(
                ProductAttributeInterface::ENTITY_TYPE_CODE,
                $attributeCode
            );
        } catch (\Throwable $e) {
            return $result->setData(['options' => [], 'error' => $e->getMessage()]);
        }

        $out = [];
        foreach ($options as $opt) {
            $value = (string) $opt->getValue();
            $label = (string) $opt->getLabel();
            if ($value === '' || $label === '') {
                continue;
            }
            $out[] = [
                'value' => $value,
                'label' => $label,
                'slug'  => $this->slugify($label),
            ];
        }

        return $result->setData(['options' => $out]);
    }

    /**
     * @param string $value
     * @return string
     */
    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}
