<?php

declare(strict_types=1);

namespace Byte8\Horizon\Model\Service;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class CategoryService
{
    public function __construct(
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Get category tree.
     * Returns shape matching Rust CategoryTreeResult.
     */
    public function getTree(?int $parentId = null, int $maxDepth = 3): array
    {
        $store = $this->storeManager->getStore();
        $rootCategoryId = (int) $store->getRootCategoryId();

        if ($parentId === null) {
            $parentId = $rootCategoryId;
        }

        try {
            $parentCategory = $this->categoryRepository->get($parentId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return ['categories' => [], 'total_count' => 0];
        }

        $parentLevel = (int) $parentCategory->getLevel();
        $maxLevel = $parentLevel + $maxDepth;

        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'url_path', 'is_active', 'level', 'position']);
        $collection->addAttributeToFilter('is_active', 1);
        $collection->addAttributeToFilter('path', ['like' => $parentCategory->getPath() . '/%']);
        $collection->addAttributeToFilter('level', ['lteq' => $maxLevel]);
        $collection->setOrder('level', 'ASC');
        $collection->setOrder('position', 'ASC');

        // Build flat lookup with product counts
        $productCounts = $this->getProductCounts($collection->getAllIds());
        $flatCategories = [];

        foreach ($collection as $cat) {
            $flatCategories[(int) $cat->getId()] = [
                'id' => (int) $cat->getId(),
                'name' => $cat->getName(),
                'url_path' => $cat->getUrlPath() ?? '',
                'parent_id' => (int) $cat->getParentId() ?: null,
                'level' => (int) $cat->getLevel(),
                'product_count' => $productCounts[(int) $cat->getId()] ?? 0,
                'children' => [],
            ];
        }

        // Build tree from flat list
        $tree = [];
        foreach ($flatCategories as $id => &$cat) {
            $pid = $cat['parent_id'];
            if ($pid !== null && isset($flatCategories[$pid])) {
                $flatCategories[$pid]['children'][] = &$cat;
            } elseif ($pid === $parentId || $pid === null) {
                $tree[] = &$cat;
            }
        }
        unset($cat);

        // Direct children of parent
        $directChildren = array_filter($flatCategories, fn($c) => $c['parent_id'] === $parentId);
        if (empty($tree)) {
            $tree = array_values($directChildren);
        }

        return [
            'categories' => $tree,
            'total_count' => count($flatCategories),
        ];
    }

    private function getProductCounts(array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $collection = $this->categoryCollectionFactory->create();
        $collection->addIdFilter($categoryIds);
        $collection->setProductStoreId($this->storeManager->getStore()->getId());
        $collection->setLoadProductCount(true);

        $counts = [];
        foreach ($collection as $cat) {
            $counts[(int) $cat->getId()] = (int) $cat->getProductCount();
        }
        return $counts;
    }
}
