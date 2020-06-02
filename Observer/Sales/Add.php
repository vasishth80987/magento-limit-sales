<?php
/**
 * Created by PhpStorm.
 * User: vash
 * Date: 2/06/20
 * Time: 10:34 PM
 */
namespace Vsynch\LimitSales\Observer\Sales;

use Magento\Checkout\Controller\Cart\Add as coreAdd;
use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;

class Add
{
    protected $quoteItemFactory;
    protected $productFactory;
    protected $cart;
    protected $checkoutSession;
    protected $errors;
    protected $messageManager;
    protected $resultRedirectFactory;
    protected $storeManager;
    protected $request;
    protected $redirect;
    protected $objectManager;
    protected $url;
    protected $response;
    protected $scopeConfig;

    public function __construct(
        Cart $cart,\Magento\Framework\Message\ManagerInterface $messageManager,
        CheckoutSession $checkoutSession,
        ProductFactory $productFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Controller\ResultFactory $resultRedirectFactory,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\App\Action\Context $context
    )
    {
        $this->messageManager = $messageManager;
        $this->productFactory = $productFactory;
        $this->checkoutSession = $checkoutSession;
        $this->cart = $cart;
        $this->errors = [];
        $this->scopeConfig = $scopeConfig;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->storeManager = $storeManager;
        $this->request = $context->getRequest();
        $this->response = $context->getResponse();
        $this->redirect = $context->getRedirect();
        $this->objectManager = $context->getObjectManager();
        $this->url = $context->getUrl();
    }

    public function aroundExecute(coreAdd $subject, \Closure $proceed)
    {

        $params = $this->request->getParams();

        try {

            $productId = (int)$this->request->getParam('product');
            $customerId = $this->cart->getCustomerSession()->getCustomerId();
            $qty = 1;

            if (isset($params['qty'])) {
                $filter = new \Zend_Filter_LocalizedToNormalized(
                    ['locale' => $this->objectManager->get(
                        \Magento\Framework\Locale\ResolverInterface::class
                    )->getLocale()]
                );
                $qty = $filter->filter($params['qty']);
            }

            $resultRedirect = $this->resultRedirectFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);

            if(!$this->checkSalesLimit($productId,$customerId,$qty)){


                $this->messageManager->addErrorMessage(
                    $this->objectManager->get(\Magento\Framework\Escaper::class)->escapeHtml('Could not Add Product(s) to Cart! '. implode(', ',$this->errors)). '. Reason: Limit Sales Restrictions in place!'
                );

                return $resultRedirect->setPath('/');

            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            if ($this->checkoutSession->getUseNotice(true)) {
                $this->messageManager->addNoticeMessage(
                    $this->objectManager->get(\Magento\Framework\Escaper::class)->escapeHtml($e->getMessage())
                );
            } else {
                $messages = array_unique(explode("\n", $e->getMessage()));
                foreach ($messages as $message) {
                    $this->messageManager->addErrorMessage(
                        $this->objectManager->get(\Magento\Framework\Escaper::class)->escapeHtml($message)
                    );
                }
            }

            $url = $this->checkoutSession->getRedirectUrl(true);

            if (!$url) {
                $url = $this->redirect->getRedirectUrl($this->url->getUrl('checkout/cart', ['_secure' => true]));
            }

            return $this->goBack($url);
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('We can\'t add this item to your shopping cart right now.')
            );
            $this->objectManager->get(\Psr\Log\LoggerInterface::class)->critical($e);
            return $this->goBack();
        }
        return $proceed();
    }

    /**
     * Resolve response
     *
     * @param string $backUrl
     * @param \Magento\Catalog\Model\Product $product
     * @return $this|\Magento\Framework\Controller\Result\Redirect
     */
    protected function goBack($backUrl = null, $product = null)
    {
        if (!$this->getRequest()->isAjax()) {
            return parent::_goBack($backUrl);
        }

        $result = [];

        if ($backUrl || $backUrl = $this->getBackUrl()) {
            $result['backUrl'] = $backUrl;
        } else {
            if ($product && !$product->getIsSalable()) {
                $result['product'] = [
                    'statusText' => __('Out of stock')
                ];
            }
        }

        $this->getResponse()->representJson(
            $this->objectManager->get(\Magento\Framework\Json\Helper\Data::class)->jsonEncode($result)
        );
    }

    /*
    protected function getBackUrl($defaultUrl = null)
    {
        $returnUrl = $this->request->getParam('return_url');
        if ($returnUrl && $this->isInternalUrl($returnUrl)) {
            $this->messageManager->getMessages()->clear();
            return $returnUrl;
        }

        if ($this->shouldRedirectToCart() || $this->request->getParam('in_cart')) {
            if ($this->request->getActionName() == 'add' && !$this->request->getParam('in_cart')) {
                $this->checkoutSession->setContinueShoppingUrl($this->redirect->getRefererUrl());
            }
            return $this->url->getUrl('checkout/cart');
        }

        return $defaultUrl;
    }

    private function shouldRedirectToCart()
    {
        return $this->scopeConfig->isSetFlag(
            'checkout/cart/redirect_to_cart',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    protected function _isInternalUrl($url)
    {
        if (strpos($url, 'http') === false) {
            return false;
        }
        $store = $this->storeManager->getStore();
        $unsecure = strpos($url, (string) $store->getBaseUrl()) === 0;
        $secure = strpos($url, (string) $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK, true)) === 0;
        return $unsecure || $secure;
    }*/

    public function checkSalesLimit($productId,$customerId,$cart_entries){

        if(!$customerId) return true;

        $product = $this->productFactory->create()->load($productId);

        $cart = $this->cart->getQuote();

        foreach ($cart->getAllVisibleItems() as $item) {
            if ($productId == $item->getProductId()){
                $cart_entries += $item->getQty();
            }
        }



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