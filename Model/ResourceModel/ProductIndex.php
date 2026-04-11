<?php

declare(strict_types=1);

namespace Byte8\Horizon\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ProductIndex extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('byte8_horizon_product_index', 'product_id');
    }
}
