# ČSOB
This is small Nette Framework wrapper for ČSOB payment gateway.

## Installation
The easiest way to install library is via Composer.

````sh
$ composer require lzaplata/csobpaymentgateway: dev-master
````
or edit `composer.json` in your project

````json
"require": {
        "lzaplata/csobpaymentgateway": "dev-master"
}
````

You have to register the library as extension in `config.neon` file.

````neon
extensions:
        csobPaymentGateway: LZaplata\CsobPaymentGateway\DI\Extension
````

Now you can set parameters...

````neon
csobPaymentGateway:
        merchantId      : *
        sandbox         : true
        privateKey:
            path        : *                        
            password    : *
        publicKey       : *                      
````

...and autowire library to presenter

````php
use LZaplata\CsobPaymentGateway\Service;

/** @var Service @inject */
public $csobPaymentGateway;
````
## Usage
In first step you must create order instantion.

````php
$order = $this->csobPaymentGateway->createOrder([
        "orderNo" => $orderNo,          
        "currency" => $currency,                    // CZK
        "totalAmount" => $price,                    // order price in lowest currency unit (1 CZK = 100)
        "returnUrl" => $returnUrl,                  
        "cart" => [
                0 => [
                        "name" => $productName,
                        "quantity" => $quantity,
                        "amount" => $productPrice,  // product price in lowest currency unit (1 CZK = 100)
                        "description" => $productDesc
                ]
        ]
]);
````

Second step decides if creating order is successful...

````php
try {
        $response = $this->csobPaymentGateway->pay($order);
} catch (\OpenPayU_Exception $e) {
        print $e->getMessage();
}
````

...and finally you can redirect to gateway.

````php
$this->sendResponse($response);
````

...or get redirect url.

````php
$response->getUrl();
````
### After return from gateway
You can check if order is paid. If you call function without parameter, 
it automatically handle `$_POST` which returns from gateway.

````php
$this->csobPaymentGateway->isPaid();
````
