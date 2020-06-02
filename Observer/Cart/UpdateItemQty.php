<?php

namespace Vsynch\LimitSales\Observer\Cart;

use Magento\Checkout\Controller\Cart\UpdateItemQty as coreUpdateItemQty;
use Magento\Framework\Json\Helper\Data as coreData;
use Magento\Checkout\Model\Sidebar;
use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;


class UpdateItemQty
{
    protected $jsonHelper;
    protected $sidebar;
    protected $quoteItemFactory;
    protected $productFactory;
    protected $cart;
    protected $checkoutSession;
    protected $errors;
    protected $messageManager;


    public function __construct(
        coreData $jsonHelper,
        Sidebar $sidebar,
        Cart $cart,\Magento\Framework\Message\ManagerInterface $messageManager,
        CheckoutSession $checkoutSession,
        ProductFactory $productFactory
    )
    {
        $this->jsonHelper = $jsonHelper;
        $this->sidebar = $sidebar;
        $this->messageManager = $messageManager;
        $this->productFactory = $productFactory;
        $this->checkoutSession = $checkoutSession;
        $this->cart = $cart;
        $this->errors = [];
    }

    public function aroundExecute(coreUpdateItemQty $subject, \Closure $proceed)
    {

        try {

            $cartData = $subject->getRequest()->getParam('cart');
            $quote = $this->checkoutSession->getQuote();

            $error= false;
            $errorMsg = '';
            foreach ($cartData as $itemId => $itemInfo) {
                $item = $quote->getItemById($itemId);

                if(!$this->checkSalesLimit($item->getProductId(),$this->cart->getCustomerSession()->getCustomerId(),$itemInfo['qty'])){
                    $errorMsg = 'Could Not Update Cart. Some Items are in violation of Sales Limit Restrictions.';
                    $error = true;
                }
            }

            if($error){
                $this->messageManager->addErrorMessage(implode("\n",$this->errors));
                return $subject->getResponse()->representJson(
                    $this->jsonHelper->jsonEncode($this->sidebar->getResponseData($errorMsg))
                );
            }

        } catch (LocalizedException $e) {
            $subject->jsonResponse($e->getMessage());
        } catch (\Exception $e) {
            //$subject->logger->critical($e->getMessage());
            $subject->jsonResponse('Something went wrong while saving the page. Please refresh the page and try again.');
        }
        return $proceed();

    }

    public function checkSalesLimit($productId,$customerId,$cart_entries){
        if(!$customerId) return true;
        $product = $this->productFactory->create()->load($productId);



        $psl = $product->getData('limit_sales');
        $psltf = $product->getData('limit_sales_time_frame');
        $check_sales_limit = true;
        $check_sales_limit_time_frame = true;
        $allowed_sales = 0;
        $qtf = 0;
        $wait = false;
        $wait_time = 0;

        if (empty($psl)) {
           return true;
        }

        if (empty($psltf)) $check_sales_limit_time_frame = false;

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $connection = $objectManager->get('Magento\Framework\App\ResourceConnection')->getConnection();
        $table = 'limit_sales_instances';

        $query = "SELECT * FROM " . $table . " WHERE product_id = " . $productId . " AND user_id = " . $customerId;
        $result1 = $connection->fetchAll($query);


        if (count($result1) != 0) {
            foreach ($result1 as $result) {
                $sales = json_decode($result['sales']);
                $salestf = [];
                $qtf = 0;

                if ($check_sales_limit_time_frame && $psltf != -1) {
                    foreach ($sales as $sale) {
                        if ($psltf > (time() - $sale->time)) $salestf[] = $sale;
                    }
                } elseif ($psltf == -1) {
                    $check_sales_limit_time_frame = false;
                    $salestf = $sales;
                }

                foreach ($salestf as $sale) $qtf += $sale->quantity;

                $allowed_sales = $psl - $qtf;

                if (count($salestf) > 0) $wait_time = $salestf[0]->time + $psltf - time();

                // Time difference in seconds
                $sec = $wait_time;
                $min = $wait_time / 60;
                $hrs = $wait_time / 3600;
                $days = floor($wait_time / 86400);
                $hrs = floor($hrs - $days * 24);
                $min = floor($min - $hrs * 60 - $days * 24 * 60);
                $sec = floor($sec - $min * 60 - $hrs * 60 * 60 - $days * 24 * 60 * 60);

                if ($days || $hrs || $min || $sec) $wait = true;

                $wait_time = sprintf("%02d", $days) . " Day(s) " . sprintf("%02d", $hrs) . " Hour(s) " . sprintf("%02d", $min) . " Min(s) " . sprintf("%02d", $sec) . " Second(s)";

            }
        } else $allowed_sales = $psl;

        if($allowed_sales<0)$allowed_sales=0;


        if($cart_entries>$allowed_sales){
            $this->errors[] = 'Product Name: '.$product->getName().', Allowed Purchases: '.$allowed_sales;
            return false;
        }
        else return true;
    }
}