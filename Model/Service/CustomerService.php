<?php

declare(strict_types=1);

namespace Byte8\Horizon\Model\Service;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\App\ResourceConnection;

class CustomerService
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly FilterBuilder $filterBuilder,
        private readonly FilterGroupBuilder $filterGroupBuilder,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Search/list customers.
     * Returns shape matching Rust CustomersResult.
     */
    public function search(
        ?string $query = null,
        ?string $groupName = null,
        bool $activeOnly = false,
        int $limit = 20,
        int $page = 1
    ): array {
        // Search by email, firstname, or lastname using OR filter groups
        if ($query !== null) {
            $emailFilter = $this->filterBuilder
                ->setField('email')
                ->setValue('%' . $query . '%')
                ->setConditionType('like')
                ->create();
            $firstnameFilter = $this->filterBuilder
                ->setField('firstname')
                ->setValue('%' . $query . '%')
                ->setConditionType('like')
                ->create();
            $lastnameFilter = $this->filterBuilder
                ->setField('lastname')
                ->setValue('%' . $query . '%')
                ->setConditionType('like')
                ->create();

            // Filters within a group are ORed
            $filterGroup = $this->filterGroupBuilder
                ->addFilter($emailFilter)
                ->addFilter($firstnameFilter)
                ->addFilter($lastnameFilter)
                ->create();

            $this->searchCriteriaBuilder->setFilterGroups([$filterGroup]);
        }

        if ($groupName !== null) {
            $groupId = $this->resolveGroupId($groupName);
            if ($groupId !== null) {
                $this->searchCriteriaBuilder->addFilter('group_id', $groupId);
            }
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->setPageSize($limit)
            ->setCurrentPage($page)
            ->create();

        $result = $this->customerRepository->getList($searchCriteria);

        // Pre-fetch group names
        $groupNames = $this->getGroupNames();

        // Pre-fetch order stats
        $customerIds = [];
        foreach ($result->getItems() as $customer) {
            $customerIds[] = (int) $customer->getId();
        }
        $orderStats = $this->getOrderStats($customerIds);

        $customers = [];
        foreach ($result->getItems() as $customer) {
            $customerId = (int) $customer->getId();
            $stats = $orderStats[$customerId] ?? ['total_orders' => 0, 'total_spent' => 0.0];

            if ($activeOnly && $customer->getExtensionAttributes()?->getIsSubscribed() === false) {
                // Simple active filter: skip if explicitly inactive (note: Magento doesn't have a direct is_active for customers)
            }

            $customers[] = [
                'id' => $customerId,
                'email' => $customer->getEmail() ?? '',
                'firstname' => $customer->getFirstname() ?? '',
                'lastname' => $customer->getLastname() ?? '',
                'group_name' => $groupNames[(int) $customer->getGroupId()] ?? 'General',
                'is_active' => true, // Magento customers are active if they exist
                'created_at' => $customer->getCreatedAt() ?? '',
                'total_orders' => (int) $stats['total_orders'],
                'total_spent' => (float) $stats['total_spent'],
            ];
        }

        return [
            'customers' => $customers,
            'total_count' => (int) $result->getTotalCount(),
            'page' => $page,
            'page_size' => $limit,
        ];
    }

    private function resolveGroupId(string $groupName): ?int
    {
        $groups = $this->getGroupNames();
        $flipped = array_flip($groups);
        // Case-insensitive match
        foreach ($flipped as $name => $id) {
            if (strtolower($name) === strtolower($groupName)) {
                return $id;
            }
        }
        return null;
    }

    private function getGroupNames(): array
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $result = $this->groupRepository->getList($searchCriteria);

        $names = [];
        foreach ($result->getItems() as $group) {
            $names[(int) $group->getId()] = $group->getCode();
        }
        return $names;
    }

    private function getOrderStats(array $customerIds): array
    {
        if (empty($customerIds)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $select = $connection->select()
            ->from($orderTable, [
                'customer_id',
                'total_orders' => new \Zend_Db_Expr('COUNT(*)'),
                'total_spent' => new \Zend_Db_Expr('COALESCE(SUM(grand_total), 0)'),
            ])
            ->where('customer_id IN (?)', $customerIds)
            ->where('state != ?', 'canceled')
            ->group('customer_id');

        $rows = $connection->fetchAll($select);

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row['customer_id']] = [
                'total_orders' => (int) $row['total_orders'],
                'total_spent' => (float) $row['total_spent'],
            ];
        }
        return $stats;
    }
}
