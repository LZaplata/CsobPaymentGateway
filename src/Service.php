<?php

namespace LZaplata\CsobPaymentGateway;


use Nette\Object;

class Service extends Object
{
    /** @var int */
    public $merchantId;

    /** @var bool */
    public $sandbox;

    /** @var array */
    public $privateKey;

    /** @var string */
    public $publicKey;

    /** @var string */
    public $url;

    /** @var string */
    public $customPaymentUrl;

    /** @var string */
    public $currency;

    /**
     * Service constructor.
     * @param int $merchantId
     * @param bool $sandbox
     * @param array $privateKey
     * @param string $publicKey
     * @param string $currency
     */
    public function __construct($merchantId, $sandbox, $privateKey, $publicKey, $currency)
    {
        $this->setMerchantId($merchantId);
        $this->setSandbox($sandbox);
        $this->setPrivateKey($privateKey);
        $this->setPublicKey($publicKey);
        $this->setCurrency($currency);
    }

    /**
     * @param int $merchantId
     * @return self
     */
    public function setMerchantId($merchantId)
    {
        $this->merchantId = $merchantId;

        return $this;
    }

    /**
     * @return int
     */
    public function getMerchantId()
    {
        return $this->merchantId;
    }

    /**
     * @param int $sandbox
     * @return self
     */
    public function setSandbox($sandbox)
    {
        $this->sandbox = $sandbox;

        if ($sandbox) {
            $this->url = "https://iapi.iplatebnibrana.csob.cz/api/v1.8";
            $this->customPaymentUrl = "https://iplatebnibrana.csob.cz";
        } else {
            $this->url = "https://api.platebnibrana.csob.cz/api/v1.8";
            $this->customPaymentUrl = "https://platebnibrana.csob.cz";
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getCustomPaymentUrl()
    {
        return $this->customPaymentUrl;
    }

    /**
     * @param array $privateKey
     * @return self
     */
    public function setPrivateKey($privateKey)
    {
        $this->privateKey = $privateKey;

        return $this;
    }

    /**
     * @return array
     */
    public function getPrivateKey()
    {
        return $this->privateKey;
    }

    /**
     * @param int $publicKey
     * @return self
     */
    public function setPublicKey($publicKey)
    {
        $this->publicKey = $publicKey;

        return $this;
    }

    /**
     * @return string
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    /**
     * @param string $currency
     * @return $this
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param int $orderNo
     * @param float $totalAmount
     * @param string $returnUrl
     * @param Cart $cart
     * @param string $payOperation
     * @return Payment
     */
    public function createPayment($orderNo, $totalAmount, $returnUrl, Cart $cart, $payOperation = Payment::NORMAL_PAYMENT)
    {
        $payment = new Payment($this);
        $payment->createPayment($orderNo, $totalAmount, $returnUrl, $cart, $payOperation);

        return $payment;
    }

    /**
     * @return Response
     */
    public function getReturnResponse()
    {
        return new Response(null, $this, null);
    }

    /**
     * @return bool
     */
    public function isPaid()
    {
        if (!empty($_POST)) {
            $response = (object)$_POST;

            if ($response->resultCode == 0 && ($response->paymentStatus == 7 || $response->paymentStatus == 4)) {
                return true;
            } else return false;
        } else return false;
    }

    /**
     * @param int $payId
     * @return Response
     * @throws \Nette\Application\BadRequestException
     */
    public function reversePayment($payId)
    {
        $payment = new Payment($this);

        return $payment->reversePayment($payId);
    }
}