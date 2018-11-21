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
        currency        : CZK
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
Create cart instance and add items.

````php
$cart = new Cart();
$cart->setItem(
        $name,                          
        $quantity,                      
        $amount,                        // item price * quantity in lowest currency unit (1 CZK = 100)
        $description
);
````

Create payment.

````php
$payment = $this->csobPaymentGateway->createPayment(
        $orderNo,          
        $totalAmount,                    // payment price in lowest currency unit (1 CZK = 100)
        $returnUrl,                  
        $cart,                           // cart instace from step above
        $payOperation                    // type of payment operation - default Payment::NORMAL_PAYMENT
);
````

Send payment.

````php
$response = $payment->send();
````

Get payment ID and save it to database.

````php
$payId = $response->getPayId();
````

Redirect to payment gateway.

````php
$this->sendResponse($response->getRedirectResponse());
````

...or get redirect url.

````php
$response->getRedirectUrl();
````
### After return from gateway
Get response and check if payment was successful

````php
$response = $this->csobPaymentGateway->getReturnResponse();

if ($response->isOk()) {
    // do something
}
````

### Reverse payment
Sometimes you must reverse your payment

````php
$response = $this->csobPaymentGateway->reversePayment($payId);

if ($response->isReversed()) {
    // do something
}
````
