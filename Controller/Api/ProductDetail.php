<?php

declare(strict_types=1);

namespace Byte8\Horizon\Controller\Api;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Byte8\Horizon\Model\Config;
use Byte8\Horizon\Model\Service\ProductService;

class ProductDetail extends AbstractAction
{
    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        Config $config,
        private readonly ProductService $productService
    ) {
        parent::__construct($request, $jsonFactory, $config);
    }

    protected function handleRequest(Json $result): Json
    {
        $identifier = $this->request->getParam('identifier');

        if (empty($identifier)) {
            return $result->setHttpResponseCode(400)->setData([
                'error' => 'Product identifier (SKU or ID) is required',
                'code' => 'MISSING_IDENTIFIER',
            ]);
        }

        $product = $this->productService->getDetail($identifier);

        if ($product === null) {
            return $result->setHttpResponseCode(404)->setData([
                'error' => "Product not found: {$identifier}",
                'code' => 'NOT_FOUND',
            ]);
        }

        return $result->setData($product);
    }
}
