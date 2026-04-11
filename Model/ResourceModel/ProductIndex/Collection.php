<?php

declare(strict_types=1);

namespace Byte8\Horizon\Model\ResourceModel\ProductIndex;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Byte8\Horizon\Model\ProductIndex as ProductIndexModel;
use Byte8\Horizon\Model\ResourceModel\ProductIndex as ProductIndexResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(ProductIndexModel::class, ProductIndexResource::class);
    }
}
