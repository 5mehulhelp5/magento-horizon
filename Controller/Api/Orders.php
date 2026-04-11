<?php

declare(strict_types=1);

namespace Byte8\Horizon\Controller\Api;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Byte8\Horizon\Model\Config;
use Byte8\Horizon\Model\Service\OrderService;

class Orders extends AbstractAction
{
    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        Config $config,
        private readonly OrderService $orderService
    ) {
        parent::__construct($request, $jsonFactory, $config);
    }

    protected function handleRequest(Json $result): Json
    {
        $status = $this->getStringParam('status');
        $customerEmail = $this->getStringParam('customer_email');
        $fromDate = $this->getStringParam('from_date');
        $toDate = $this->getStringParam('to_date');
        $limit = min($this->getIntParam('limit', 20), 50);
        $page = max($this->getIntParam('page', 1), 1);

        $data = $this->orderService->search(
            $status,
            $customerEmail,
            $fromDate,
            $toDate,
            $limit,
            $page
        );

        return $result->setData($data);
    }
}
