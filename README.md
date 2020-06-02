# Magento Limit Sales
A Magento 2.3 module that enables administrators to set maximum limits on purchases of their shop products per user. 
They can also optionally set a time duration for the limits. Currently only works for logged in users.

## Requirements
Magento 2.3+

## Installation
```
composer require vsynch/limit-sales
```
### Run Magento Upgrade
```
php bin/magento setup:upgrade
```
### Disable Guest Checkout
Follow steps detailed here, https://docs.magento.com/user-guide/sales/checkout-guest.html#disable-guest-checkout

## Usage

Step 1: Login in to Admin Panel
Step 2: Browse to Product Add/Edit pages.
Step 3: Set Fields - `Purchases Limited To`  and  `Purchases Limited For` (later is optional)
Step 4: Go To the shop front -> Login as user and see the module in action!
Step 5: Have fun!
