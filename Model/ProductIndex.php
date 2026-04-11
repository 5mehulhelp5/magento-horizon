<?php

declare(strict_types=1);

namespace Byte8\Horizon\Model;

use Magento\Framework\Model\AbstractModel;

class ProductIndex extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\ProductIndex::class);
    }
}
