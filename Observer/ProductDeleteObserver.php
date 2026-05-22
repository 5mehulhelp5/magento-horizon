<?php

declare(strict_types=1);

namespace Byte8\Horizon\Observer;

use Byte8\Horizon\Model\Config;
use Byte8\Horizon\Model\Webhook\Notifier;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductDeleteObserver implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly Notifier $notifier
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $product = $observer->getEvent()->getProduct();
        if (!$product || !$product->getId()) {
            return;
        }

        $this->notifier->notify(Notifier::EVENT_PRODUCT_DELETED, [(int) $product->getId()]);
    }
}
