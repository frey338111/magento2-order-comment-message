<?php

declare(strict_types=1);

namespace Hmh\OrderCommentMessage\Plugin\Sales\Controller\Adminhtml;

use Hmh\InternalMessage\Api\Data\InternalMessageDtoInterface;
use Hmh\InternalMessage\Api\InternalMessageManagementInterface;
use Hmh\InternalMessage\Model\Data\InternalMessageDtoFactory;
use Hmh\OrderCommentMessage\Model\Config\ConfigProvider as OrderCommentConfigProvider;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Psr\Log\LoggerInterface;

class AddCommentPlugin
{
    public function __construct(
        private readonly InternalMessageManagementInterface $internalMessageManagement,
        private readonly InternalMessageDtoFactory $internalMessageDtoFactory,
        private readonly RequestInterface $request,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly CreditmemoRepositoryInterface $creditmemoRepository,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly OrderCommentConfigProvider $orderCommentConfigProvider,
        private readonly LoggerInterface $logger
    ) {
    }

    public function afterExecute($subject, ?ResultInterface $result): ?ResultInterface
    {
        $postValue = (array)$this->request->getPost(
            empty((array)$this->request->getPost('history', [])) ? 'comment' : 'history',
            []
        );

        if (!$postValue) {
            return $result;
        }

        if ((int)($postValue['is_add_internal_message'] ?? 0) !== 1) {
            return $result;
        }

        $comment = trim((string)($postValue['comment'] ?? ''));
        if ($comment === '') {
            return $result;
        }

        $commentData = $this->getCommentData();
        if (!$commentData) {
            return $result;
        }

        try {
            $storeId = (int)$commentData['store_id'];

            if (!$this->orderCommentConfigProvider->isEnabled($storeId)) {
                return $result;
            }

            $customerId = (int)$commentData['customer_id'];
            if ($customerId <= 0) {
                return $result;
            }

            $dto = $this->internalMessageDtoFactory->create([
                'data' => [
                    InternalMessageDtoInterface::TITLE => (string)$commentData['title'],
                    InternalMessageDtoInterface::MESSAGE_CONTENT => $comment,
                    InternalMessageDtoInterface::CUSTOMER_ID => $customerId,
                    InternalMessageDtoInterface::STORE_ID => $storeId,
                ],
            ]);

            $this->internalMessageManagement->createMessage($dto);
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Failed to save internal message from admin order comment.',
                ['exception' => $exception]
            );
        }

        return $result;
    }

    private function getCommentData(): array
    {
        $orderId = (int)$this->request->getParam('order_id');
        $invoiceId = (int)$this->request->getParam('invoice_id');
        $creditMemoId = (int)$this->request->getParam('creditmemo_id');
        $shipmentId = (int)($this->request->getParam('shipment_id') ?: $this->request->getParam('id'));

        return match (true) {
            $orderId > 0 => $this->getOrderCommentData($orderId),
            $invoiceId > 0 => $this->getInvoiceCommentData($invoiceId),
            $creditMemoId > 0 => $this->getCreditmemoCommentData($creditMemoId),
            $shipmentId > 0 => $this->getShipmentCommentData($shipmentId),
            default => []
        };
    }

    private function getOrderCommentData(int $orderId): array
    {
        $order = $this->orderRepository->get($orderId);

        return [
            'customer_id' => (int)$order->getCustomerId(),
            'store_id' => (int)$order->getStoreId(),
            'title' => (string)__('new order comment'),
        ];
    }

    private function getInvoiceCommentData(int $invoiceId): array
    {
        $invoice = $this->invoiceRepository->get($invoiceId);
        $order = $invoice->getOrder();

        return [
            'customer_id' => (int)$order->getCustomerId(),
            'store_id' => (int)$order->getStoreId(),
            'title' => (string)__('new order invoice comment'),
        ];
    }

    private function getCreditmemoCommentData(int $creditMemoId): array
    {
        $creditMemo = $this->creditmemoRepository->get($creditMemoId);
        $order = $creditMemo->getOrder();

        return [
            'customer_id' => (int)$order->getCustomerId(),
            'store_id' => (int)$order->getStoreId(),
            'title' => (string)__('new order credit memo comment'),
        ];
    }

    private function getShipmentCommentData(int $shipmentId): array
    {
        $shipment = $this->shipmentRepository->get($shipmentId);
        $order = $shipment->getOrder();

        return [
            'customer_id' => (int)$order->getCustomerId(),
            'store_id' => (int)$order->getStoreId(),
            'title' => (string)__('new order shipment comment'),
        ];
    }
}
