<?php
/**
 * Created by PhpStorm.
 * User: vash
 * Date: 2/06/20
 * Time: 10:34 PM
 */
namespace Vsynch\LimitSales\Observer\Sales;

use Magento\Sales\Controller\Order\Reorder as coreReorder;
use Magento\Framework\Json\Helper\Data as coreData;
use Magento\Checkout\Model\Sidebar;
use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;

class Reorder
{
    protected $jsonHelper;
    protected $registry;
    protected $quoteItemFactory;
    protected $productFactory;
    protected $cart;
    protected $modified;
    protected $messageManager;
    protected $resultRedirectFactory;
    protected $orderLoader;
    protected $request;

    public function __construct(
        coreData $jsonHelper,
        \Magento\Framework\Registry $registry,
        Cart $cart,\Magento\Framework\Message\ManagerInterface $messageManager,
        ProductFactory $productFactory,
        \Magento\Framework\Controller\ResultFactory $resultRedirectFactory,
        \Magento\Sales\Controller\AbstractController\OrderLoaderInterface $orderLoader,
        \Magento\Framework\App\Request\Http $request
    )
    {
        $this->jsonHelper = $jsonHelper;
        $this->registry = $registry;
        $this->messageManager = $messageManager;
        $this->productFactory = $productFactory;
        $this->cart = $cart;
        $this->modified = [];
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->orderLoader = $orderLoader;
        $this->request = $request;
    }

    public function aroundExecute(coreReorder $subject, \Closure $proceed)
    {
        $result = $this->orderLoader->load($this->request);
        if ($result instanceof \Magento\Framework\Controller\ResultInterface) {
            return $result;
        }
        $order = $this->registry->registry('current_order');

        $resultRedirect = $this->resultRedirectFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);

        $cart = $this->cart;//_objectManager->get(\Magento\Checkout\Model\Cart::class);
        $items = $order->getItemsCollection();


        foreach ($items as $item) {
            try {
                $item = $this->checkSalesLimit($item->getProductId(),$this->cart->getCustomerSession()->getCustomerId(),$item);
                if($item!==false)$cart->addOrderItem($item);
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                if ($this->_objectManager->get(\Magento\Checkout\Model\Session::class)->getUseNotice(true)) {
                    $this->messageManager->addNoticeMessage($e->getMessage());
                } else {
                    $this->messageManager->addErrorMessage($e->getMessage());
                }
                return $resultRedirect->setPath('*/*/history');
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __('We can\'t add this item to your shopping cart right now.')
                );
                return $resultRedirect->setPath('checkout/cart');
            }
        }

        $cart->save();

        if(count($this->modified)>0)$this->messageManager->addErrorMessage("The following Item quantities were reduced due to Sales Limit Restrictions: ".implode(', ',$this->modified));

        return $resultRedirect->setPath('checkout/cart');
    }

    public function checkSalesLimit($productId,$customerId,$item){

        $cart_entries = intval($item->getQtyOrdered());
        foreach ($this->cart->getQuote()->getAllVisibleItems() as $citem) {
            if ($productId == $citem->getProductId()){
                $cart_entries += $citem->getQty();
            }
        }

        if(!$customerId) return $item;
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
            return $item;
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

        if($allowed_sales<=0) {
            $this->modified[] = $product->getName()."(Sales Limit reached!, Product not added to cart!)";
            return false;
        }
        else if($cart_entries>$allowed_sales){
            $decAmt = intval($item->getQtyOrdered())-($cart_entries-$allowed_sales);
            if($decAmt>0)$item->setQtyOrdered($decAmt);
            else {
                $this->modified[] = $product->getName()."(Sales Limit reached!, Product not added to cart!)";
                return false;
            }
            $this->modified[] = $product->getName()."(Requested: ".$cart_entries.", Allowed: ".$allowed_sales.", Reduced By: ".($cart_entries-$allowed_sales).")";
        }
        return $item;
    }
}