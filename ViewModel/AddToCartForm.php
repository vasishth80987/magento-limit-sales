<?php

namespace Vsynch\LimitSales\ViewModel;


class AddToCartForm implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    protected $_httpContext;
    protected $session;
    protected $cart;

    public function __construct(\Magento\Framework\App\Http\Context $httpContext, \Magento\Customer\Model\Session $session, \Magento\Checkout\Model\Cart $cart)
    {
        $this->_httpContext = $httpContext;
        $this->session = $session;
        $this->cart = $cart;
    }

    public function getCustomerId()
    {
        return $this->session->getCustomerId();
    }
}