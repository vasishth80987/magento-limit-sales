<?php

namespace Vsynch\LimitSales\Observer\Cart;

use Magento\Checkout\Controller\Cart\UpdatePost as coreUpdatePost;
use Magento\Framework\Json\Helper\Data as coreData;
use Magento\Checkout\Model\Sidebar;
use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Cart;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\ObjectManager;

class UpdatePost
{
    protected $objectManager;
    protected $messageManager;
    protected $quoteItemFactory;
    protected $productFactory;
    protected $cart;
    protected $serializer;


    public function __construct(
        ManagerInterface $messageManager,
        ObjectManager $objectManager,
        Cart $cart,
        SerializerInterface $serializer,
        ProductFactory $productFactory
    )
    {
        $this->objectManager = $objectManager;
        $this->messageManager = $messageManager;
        $this->productFactory = $productFactory;
        $this->serializer = $serializer;
        $this->cart = $cart;
    }

    public function aroundExecute(coreUpdatePost $subject, \Closure $proceed)
    {

        try {

            if(1>0){
                $errorMsg= 'Error Msg';
                $this->messageManager->addErrorMessage(
                    $this->objectManager->get(\Magento\Framework\Escaper::class)->escapeHtml($errorMsg)
                );
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addErrorMessage(
                $this->_objectManager->get(\Magento\Framework\Escaper::class)->escapeHtml($e->getMessage())
            );
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('We can\'t update the shopping cart.'));
            $this->_objectManager->get(\Psr\Log\LoggerInterface::class)->critical($e);
        }
        return $proceed();

    }
}