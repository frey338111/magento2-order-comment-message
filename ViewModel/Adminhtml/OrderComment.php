<?php
declare(strict_types=1);

namespace Hmh\OrderCommentMessage\ViewModel\Adminhtml;

use Hmh\OrderCommentMessage\Model\Config\ConfigProvider;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class OrderComment implements ArgumentInterface
{
    public function __construct(
        private readonly ConfigProvider $configProvider
    ) {
    }

    public function isOrderCommentMessageEnabled(): bool
    {
        return $this->configProvider->isEnabled();
    }
}
