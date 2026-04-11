<?php

declare(strict_types=1);

namespace Byte8\Horizon\Controller\Api;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Byte8\Horizon\Model\Config;
use Byte8\Horizon\Model\Service\OrderService;

class OrderDetail extends AbstractAction
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
        $identifier = $this->request->getParam('identifier');

        if (empty($identifier)) {
            return $result->setHttpResponseCode(400)->setData([
                'error' => 'Order identifier (increment ID or entity ID) is required',
                'code' => 'MISSING_IDENTIFIER',
            ]);
        }

        $order = $this->orderService->getDetail($identifier);

        if ($order === null) {
            return $result->setHttpResponseCode(404)->setData([
                'error' => "Order not found: {$identifier}",
                'code' => 'NOT_FOUND',
            ]);
        }

        return $result->setData($order);
    }
}
