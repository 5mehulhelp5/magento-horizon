<?php

declare(strict_types=1);

namespace Byte8\Horizon\Model\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Module\Manager as ModuleManager;

class InventoryService
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ModuleManager $moduleManager
    ) {
    }

    /**
     * Get inventory data with MSI source breakdown.
     * Returns shape matching Rust InventoryResult.
     */
    public function getInventory(
        ?string $sku = null,
        bool $inStockOnly = false,
        ?string $sourceCode = null,
        int $limit = 20,
        int $page = 1
    ): array {
        $connection = $this->resourceConnection->getConnection();

        // Check if MSI is available
        if ($this->moduleManager->isEnabled('Magento_InventoryApi')) {
            return $this->getMsiInventory($connection, $sku, $inStockOnly, $sourceCode, $limit, $page);
        }

        return $this->getLegacyInventory($connection, $sku, $inStockOnly, $limit, $page);
    }

    private function getMsiInventory(
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
        ?string $sku,
        bool $inStockOnly,
        ?string $sourceCode,
        int $limit,
        int $page
    ): array {
        $sourceItemTable = $this->resourceConnection->getTableName('inventory_source_item');
        $productTable = $this->resourceConnection->getTableName('catalog_product_entity');

        $select = $connection->select()
            ->from(['si' => $sourceItemTable], ['si.sku', 'si.quantity', 'si.status', 'si.source_code'])
            ->join(['p' => $productTable], 'p.sku = si.sku', ['p.entity_id']);

        // Get product name from EAV
        $nameAttrId = $this->getAttributeId($connection, 'name');
        if ($nameAttrId) {
            $varcharTable = $this->resourceConnection->getTableName('catalog_product_entity_varchar');
            $select->joinLeft(
                ['pn' => $varcharTable],
                "p.entity_id = pn.entity_id AND pn.attribute_id = {$nameAttrId} AND pn.store_id = 0",
                ['product_name' => 'pn.value']
            );
        }

        if ($sku !== null) {
            $select->where('si.sku LIKE ?', '%' . $sku . '%');
        }
        if ($inStockOnly) {
            $select->where('si.status = ?', 1);
        }
        if ($sourceCode !== null) {
            $select->where('si.source_code = ?', $sourceCode);
        }

        // Count total
        $countSelect = clone $select;
        $countSelect->reset(\Magento\Framework\DB\Select::COLUMNS);
        $countSelect->columns(new \Zend_Db_Expr('COUNT(*)'));
        $totalCount = (int) $connection->fetchOne($countSelect);

        // Apply pagination
        $offset = ($page - 1) * $limit;
        $select->limit($limit, $offset);
        $select->order('si.sku ASC');

        $rows = $connection->fetchAll($select);

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'sku' => $row['sku'],
                'product_name' => $row['product_name'] ?? $row['sku'],
                'qty' => (float) $row['quantity'],
                'is_in_stock' => (bool) $row['status'],
                'manage_stock' => true,
                'source_code' => $row['source_code'],
            ];
        }

        return [
            'items' => $items,
            'total_count' => $totalCount,
            'page' => $page,
            'page_size' => $limit,
        ];
    }

    private function getLegacyInventory(
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
        ?string $sku,
        bool $inStockOnly,
        int $limit,
        int $page
    ): array {
        $stockTable = $this->resourceConnection->getTableName('cataloginventory_stock_item');
        $productTable = $this->resourceConnection->getTableName('catalog_product_entity');

        $select = $connection->select()
            ->from(['s' => $stockTable], ['s.qty', 's.is_in_stock', 's.manage_stock'])
            ->join(['p' => $productTable], 'p.entity_id = s.product_id', ['p.sku']);

        $nameAttrId = $this->getAttributeId($connection, 'name');
        if ($nameAttrId) {
            $varcharTable = $this->resourceConnection->getTableName('catalog_product_entity_varchar');
            $select->joinLeft(
                ['pn' => $varcharTable],
                "p.entity_id = pn.entity_id AND pn.attribute_id = {$nameAttrId} AND pn.store_id = 0",
                ['product_name' => 'pn.value']
            );
        }

        if ($sku !== null) {
            $select->where('p.sku LIKE ?', '%' . $sku . '%');
        }
        if ($inStockOnly) {
            $select->where('s.is_in_stock = ?', 1);
        }

        $countSelect = clone $select;
        $countSelect->reset(\Magento\Framework\DB\Select::COLUMNS);
        $countSelect->columns(new \Zend_Db_Expr('COUNT(*)'));
        $totalCount = (int) $connection->fetchOne($countSelect);

        $offset = ($page - 1) * $limit;
        $select->limit($limit, $offset);
        $select->order('p.sku ASC');

        $rows = $connection->fetchAll($select);

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'sku' => $row['sku'],
                'product_name' => $row['product_name'] ?? $row['sku'],
                'qty' => (float) $row['qty'],
                'is_in_stock' => (bool) $row['is_in_stock'],
                'manage_stock' => (bool) $row['manage_stock'],
                'source_code' => 'default',
            ];
        }

        return [
            'items' => $items,
            'total_count' => $totalCount,
            'page' => $page,
            'page_size' => $limit,
        ];
    }

    private function getAttributeId(\Magento\Framework\DB\Adapter\AdapterInterface $connection, string $attributeCode): ?int
    {
        $eavTable = $this->resourceConnection->getTableName('eav_attribute');
        $entityTypeTable = $this->resourceConnection->getTableName('eav_entity_type');

        $select = $connection->select()
            ->from(['a' => $eavTable], ['a.attribute_id'])
            ->join(['et' => $entityTypeTable], 'a.entity_type_id = et.entity_type_id', [])
            ->where('et.entity_type_code = ?', 'catalog_product')
            ->where('a.attribute_code = ?', $attributeCode);

        $result = $connection->fetchOne($select);
        return $result ? (int) $result : null;
    }
}
