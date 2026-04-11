<?php

declare(strict_types=1);

namespace Byte8\Horizon\Controller\Api;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Byte8\Horizon\Model\Config;

class Health extends AbstractAction
{
    private const EXTENSION_VERSION = '1.0.0';

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        Config $config,
        private readonly ProductMetadataInterface $productMetadata
    ) {
        parent::__construct($request, $jsonFactory, $config);
    }

    protected function handleRequest(Json $result): Json
    {
        return $result->setData([
            'version' => self::EXTENSION_VERSION,
            'magento_version' => $this->productMetadata->getVersion(),
        ]);
    }
}
