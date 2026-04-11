<?php

declare(strict_types=1);

namespace Byte8\Horizon\Observer;

use Byte8\Horizon\Model\Config;
use Byte8\Horizon\Model\Indexer\ProductIndexer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductSaveObserver implements ObserverInterface
{
    public function __construct(
        private readonly ProductIndexer $productIndexer,
        private readonly Config $config
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $product = $observer->getEvent()->getProduct();
        if ($product && $product->getId()) {
            $this->productIndexer->reindexByIds([(int) $product->getId()]);
        }
    }
}
