<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Ui\Component\Form\DataProvider;

use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Ui\DataProvider\AbstractDataProvider;

class GenericFormDataProvider extends AbstractDataProvider
{
    private ?array $loadedData = null;

    private BackendUrl $backendUrl;

    private DataPersistorInterface $dataPersistor;

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        AbstractCollection $collection,
        array $meta = [],
        array $data = [],
        ?BackendUrl $backendUrl = null,
        ?DataPersistorInterface $dataPersistor = null
    ) {
        $this->collection = $collection;
        $this->backendUrl = $backendUrl ?? ObjectManager::getInstance()->get(BackendUrl::class);
        $this->dataPersistor = $dataPersistor ?? ObjectManager::getInstance()->get(DataPersistorInterface::class);
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getMeta(): array
    {
        $meta = parent::getMeta();
        $url = $this->backendUrl->getUrl('panth_filterseo/filterrewrite/options');

        foreach (array_keys($meta) as $fieldset) {
            $meta[$fieldset]['children']['option_id']['arguments']['data']['config']['optionsEndpoint'] = $url;
        }

        return $meta;
    }

    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }

        $this->loadedData = [];
        $items = $this->collection->getItems();

        foreach ($items as $item) {
            $this->loadedData[$item->getId()] = $item->getData();
        }

        $persisted = $this->dataPersistor->get($this->name);
        if (is_array($persisted) && $persisted !== []) {
            $this->dataPersistor->clear($this->name);
            $id = (int) ($persisted[$this->primaryFieldName] ?? 0);
            if ($id > 0) {
                $this->loadedData[$id] = $persisted;
            } else {
                $this->loadedData[0] = $persisted;
                $this->loadedData[''] = $persisted;
            }
        }

        if (empty($this->loadedData)) {
            $this->loadedData[''] = [];
        }

        return $this->loadedData;
    }
}
