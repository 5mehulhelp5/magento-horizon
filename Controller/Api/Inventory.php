<?php

declare(strict_types=1);

namespace Byte8\Horizon\Controller\Api;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Byte8\Horizon\Model\Config;
use Byte8\Horizon\Model\Service\InventoryService;

class Inventory extends AbstractAction
{
    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        Config $config,
        private readonly InventoryService $inventoryService
    ) {
        parent::__construct($request, $jsonFactory, $config);
    }

    protected function handleRequest(Json $result): Json
    {
        $sku = $this->getStringParam('sku');
        $inStockOnly = $this->getBoolParam('in_stock_only', false);
        $sourceCode = $this->getStringParam('source_code');
        $limit = min($this->getIntParam('limit', 20), 50);
        $page = max($this->getIntParam('page', 1), 1);

        $data = $this->inventoryService->getInventory(
            $sku,
            $inStockOnly,
            $sourceCode,
            $limit,
            $page
        );

        return $result->setData($data);
    }
}
