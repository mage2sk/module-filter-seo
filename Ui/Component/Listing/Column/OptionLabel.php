<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Ui\Component\Listing\Column;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class OptionLabel extends Column
{
    private array $cache = [];

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly AttributeOptionManagementInterface $optionManagement,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $fieldName = $this->getData('name') ?: 'option_label';

        foreach ($dataSource['data']['items'] as &$item) {
            $attributeCode = (string) ($item['attribute_code'] ?? '');
            $optionId = (string) ($item['option_id'] ?? '');
            $item[$fieldName] = $this->resolveLabel($attributeCode, $optionId);
        }

        return $dataSource;
    }

    private function resolveLabel(string $attributeCode, string $optionId): string
    {
        if ($attributeCode === '' || $optionId === '') {
            return '';
        }

        if (!isset($this->cache[$attributeCode])) {
            $this->cache[$attributeCode] = $this->loadAttributeLabels($attributeCode);
        }

        $label = $this->cache[$attributeCode][$optionId] ?? '';

        return $label !== ''
            ? sprintf('%s (%s)', $label, $optionId)
            : sprintf('(option #%s)', $optionId);
    }

    private function loadAttributeLabels(string $attributeCode): array
    {
        try {
            $options = $this->optionManagement->getItems(
                ProductAttributeInterface::ENTITY_TYPE_CODE,
                $attributeCode
            );
        } catch (\Throwable) {
            return [];
        }

        $map = [];
        foreach ($options as $option) {
            $value = (string) $option->getValue();
            if ($value === '') {
                continue;
            }
            $map[$value] = (string) $option->getLabel();
        }

        return $map;
    }
}
