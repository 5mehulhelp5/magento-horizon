<?php

declare(strict_types=1);

namespace Byte8\Horizon\Model\Service;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder
    ) {
    }

    /**
     * Search orders with filters.
     * Returns shape matching Rust OrdersResult.
     */
    public function search(
        ?string $status = null,
        ?string $customerEmail = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        int $limit = 20,
        int $page = 1
    ): array {
        if ($status !== null) {
            $this->searchCriteriaBuilder->addFilter('status', $status);
        }
        if ($customerEmail !== null) {
            $this->searchCriteriaBuilder->addFilter('customer_email', '%' . $customerEmail . '%', 'like');
        }
        if ($fromDate !== null) {
            $this->searchCriteriaBuilder->addFilter('created_at', $fromDate . ' 00:00:00', 'gteq');
        }
        if ($toDate !== null) {
            $this->searchCriteriaBuilder->addFilter('created_at', $toDate . ' 23:59:59', 'lteq');
        }

        $sortOrder = $this->sortOrderBuilder
            ->setField('created_at')
            ->setDescendingDirection()
            ->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->setSortOrders([$sortOrder])
            ->setPageSize($limit)
            ->setCurrentPage($page)
            ->create();

        $result = $this->orderRepository->getList($searchCriteria);

        $orders = [];
        foreach ($result->getItems() as $order) {
            $orders[] = [
                'entity_id' => (int) $order->getEntityId(),
                'increment_id' => $order->getIncrementId() ?? '',
                'status' => $order->getStatus() ?? '',
                'state' => $order->getState() ?? '',
                'grand_total' => (float) $order->getGrandTotal(),
                'currency' => $order->getOrderCurrencyCode() ?? '',
                'customer_email' => $order->getCustomerEmail() ?? '',
                'customer_name' => trim(
                    ($order->getCustomerFirstname() ?? '') . ' ' . ($order->getCustomerLastname() ?? '')
                ),
                'item_count' => (int) $order->getTotalItemCount(),
                'created_at' => $order->getCreatedAt() ?? '',
                'updated_at' => $order->getUpdatedAt() ?? '',
            ];
        }

        return [
            'orders' => $orders,
            'total_count' => (int) $result->getTotalCount(),
            'page' => $page,
            'page_size' => $limit,
        ];
    }

    /**
     * Get order detail by increment ID or entity ID.
     * Returns shape matching Rust OrderDetail.
     */
    public function getDetail(string $identifier): ?array
    {
        $order = null;

        // Try increment_id first (most common), then entity_id
        $this->searchCriteriaBuilder->addFilter('increment_id', $identifier);
        $searchCriteria = $this->searchCriteriaBuilder->setPageSize(1)->create();
        $result = $this->orderRepository->getList($searchCriteria);

        if ($result->getTotalCount() > 0) {
            $items = $result->getItems();
            $order = reset($items);
        }

        // Fall back to entity_id
        if ($order === null && is_numeric($identifier)) {
            try {
                $order = $this->orderRepository->get((int) $identifier);
            } catch (\Exception $e) {
                return null;
            }
        }

        if ($order === null) {
            return null;
        }

        // Items
        $items = [];
        foreach ($order->getItems() as $item) {
            if ($item->getParentItemId()) {
                continue; // Skip child items of configurables
            }
            $items[] = [
                'sku' => $item->getSku(),
                'name' => $item->getName(),
                'qty_ordered' => (float) $item->getQtyOrdered(),
                'price' => (float) $item->getPrice(),
                'row_total' => (float) $item->getRowTotal(),
            ];
        }

        // Addresses
        $shippingAddress = $this->formatAddress($order->getShippingAddress());
        $billingAddress = $this->formatAddress($order->getBillingAddress());

        // Payment
        $payment = $order->getPayment();
        $paymentMethod = $payment ? ($payment->getAdditionalInformation('method_title') ?? $payment->getMethod()) : '';

        return [
            'entity_id' => (int) $order->getEntityId(),
            'increment_id' => $order->getIncrementId() ?? '',
            'status' => $order->getStatus() ?? '',
            'state' => $order->getState() ?? '',
            'grand_total' => (float) $order->getGrandTotal(),
            'subtotal' => (float) $order->getSubtotal(),
            'tax_amount' => (float) $order->getTaxAmount(),
            'shipping_amount' => (float) $order->getShippingAmount(),
            'discount_amount' => (float) ($order->getDiscountAmount() ?? 0),
            'currency' => $order->getOrderCurrencyCode() ?? '',
            'customer_email' => $order->getCustomerEmail() ?? '',
            'customer_name' => trim(
                ($order->getCustomerFirstname() ?? '') . ' ' . ($order->getCustomerLastname() ?? '')
            ),
            'items' => $items,
            'shipping_address' => $shippingAddress,
            'billing_address' => $billingAddress,
            'payment_method' => $paymentMethod,
            'shipping_method' => $order->getShippingDescription() ?? '',
            'created_at' => $order->getCreatedAt() ?? '',
            'updated_at' => $order->getUpdatedAt() ?? '',
        ];
    }

    private function formatAddress(?\Magento\Sales\Api\Data\OrderAddressInterface $address): ?array
    {
        if ($address === null) {
            return null;
        }

        return [
            'firstname' => $address->getFirstname() ?? '',
            'lastname' => $address->getLastname() ?? '',
            'street' => $address->getStreet() ?? [],
            'city' => $address->getCity() ?? '',
            'region' => $address->getRegion() ?? '',
            'postcode' => $address->getPostcode() ?? '',
            'country_id' => $address->getCountryId() ?? '',
            'telephone' => $address->getTelephone() ?? '',
        ];
    }
}
