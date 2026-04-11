<?php

declare(strict_types=1);

namespace Byte8\Horizon\Controller\Api;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Byte8\Horizon\Model\Config;
use Byte8\Horizon\Model\Service\CustomerService;

class Customers extends AbstractAction
{
    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        Config $config,
        private readonly CustomerService $customerService
    ) {
        parent::__construct($request, $jsonFactory, $config);
    }

    protected function handleRequest(Json $result): Json
    {
        $query = $this->getStringParam('query');
        $groupName = $this->getStringParam('group_name');
        $activeOnly = $this->getBoolParam('active_only', false);
        $limit = min($this->getIntParam('limit', 20), 50);
        $page = max($this->getIntParam('page', 1), 1);

        $data = $this->customerService->search(
            $query,
            $groupName,
            $activeOnly,
            $limit,
            $page
        );

        return $result->setData($data);
    }
}
