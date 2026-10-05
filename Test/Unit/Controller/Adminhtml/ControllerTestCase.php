<?php
declare(strict_types=1);

namespace Panth\FilterSeo\Test\Unit\Controller\Adminhtml;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Message\ManagerInterface;
use Panth\FilterSeo\Model\FilterUrl\PageCacheCleaner;
use Panth\FilterSeo\Model\FilterUrl\SlugConflict;
use PHPUnit\Framework\TestCase;

/**
 * Shared wiring for admin controller tests: records redirects and messages.
 */
abstract class ControllerTestCase extends TestCase
{
    protected array $post = [];
    protected array $params = [];
    protected array $messages = [];
    protected ?array $redirect = null;
    protected ?ResultFactory $resultFactory = null;
    protected array $persisted = [];
    protected ?array $conflict = null;
    protected int $cacheCleans = 0;

    protected function cacheCleaner(): PageCacheCleaner
    {
        $cleaner = $this->createStub(PageCacheCleaner::class);
        $cleaner->method('clean')->willReturnCallback(function () {
            $this->cacheCleans++;
        });
        return $cleaner;
    }

    protected function persistor(): DataPersistorInterface
    {
        $persistor = $this->createStub(DataPersistorInterface::class);
        $persistor->method('set')->willReturnCallback(function ($key, $data) {
            $this->persisted[$key] = $data;
        });
        $persistor->method('clear')->willReturnCallback(function ($key) {
            $this->persisted[$key] = 'cleared';
        });
        return $persistor;
    }

    protected function slugConflict(): SlugConflict
    {
        $conflict = $this->createStub(SlugConflict::class);
        $conflict->method('find')->willReturnCallback(fn() => $this->conflict);
        $conflict->method('findForRow')->willReturnCallback(fn() => $this->conflict);
        return $conflict;
    }

    protected function context(): Context
    {
        $request = $this->createStub(Http::class);
        $request->method('getPostValue')->willReturnCallback(fn() => $this->post);
        $request->method('getParam')->willReturnCallback(
            fn($key, $default = null) => $this->params[$key] ?? $default
        );

        $redirect = $this->createStub(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(function ($path, $params = []) use ($redirect) {
            $this->redirect = [$path, $params];
            return $redirect;
        });
        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);

        $messages = $this->createStub(ManagerInterface::class);
        foreach (['addSuccessMessage' => 'success', 'addErrorMessage' => 'error'] as $method => $type) {
            $messages->method($method)->willReturnCallback(function ($message) use ($type, $messages) {
                $this->messages[] = [$type, (string) $message];
                return $messages;
            });
        }

        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($messages);
        if ($this->resultFactory !== null) {
            $context->method('getResultFactory')->willReturn($this->resultFactory);
        }
        return $context;
    }

    protected function resource(AdapterInterface $connection): ResourceConnection
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return $resource;
    }
}
