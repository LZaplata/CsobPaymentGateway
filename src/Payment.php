<?php

namespace LZaplata\CsobPaymentGateway;


use GuzzleHttp\Client;
use Nette\Application\BadRequestException;
use Nette\Object;
use Nette\Utils\Json;
use Tracy\Debugger;

class Payment extends Object
{
    /** @var string */
    public $orderNo;

    /** @var int */
    public $totalAmount;

    /** @var string */
    public $returnUrl;

    /** @var Cart */
    public $cart;

    /** @var Service */
    public $service;

    /** @var array */
    public $paymentData;

    /** @var string */
    public $payOperation;

    const NORMAL_PAYMENT = "payment",
        CUSTOM_PAYMENT = "customPayment";

    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    public function createPayment($orderNo, $totalAmount, $returnUrl, Cart $cart, $payOperation)
    {
        $this->orderNo = $orderNo;
        $this->totalAmount = $totalAmount;
        $this->returnUrl = $returnUrl;
        $this->cart = $cart;
        $this->payOperation = $payOperation;

        $this->createPaymentData();
    }

    public function createPaymentData()
    {
        $this->paymentData = [
            "merchantId" => $this->service->getMerchantId(),
            "orderNo" => $this->orderNo,
            "dttm" => date("YmdHis"),
            "payOperation" => $this->payOperation,
            "payMethod" => "card",
            "totalAmount" => $this->totalAmount,
            "currency" => $this->service->getCurrency(),
            "closePayment" => "true",
            "returnUrl" => $this->returnUrl,
            "returnMethod" => "POST",
            "cart" => $this->cart->getItems(),
            "description" => "purchase",
            "language" => "CZ"
        ];

        if ($this->payOperation == self::CUSTOM_PAYMENT) {
            $this->paymentData["customExpiry"] = date("YmdHis", strtotime("+7days"));
        }

        $this->paymentData["signature"] = $this->signPaymentData($this->paymentData);
    }

    public function reversePayment($payId)
    {
        $this->paymentData = [
            "merchantId" => $this->service->getMerchantId(),
            "payId" => $payId,
            "dttm" => date("YmdHis")
        ];

        $dataToSign = $this->service->getMerchantId() . "|" . $payId . "|" . $this->paymentData["dttm"];

        $this->paymentData["signature"] = $this->sign($dataToSign);

        $client = new Client();

        $response = $client->put($this->service->getUrl() . "/payment/reverse", [
            "headers" => [
                "Content-Type" => "application/json",
                "Accept" => "application/json;charset=UTF-8"
            ],
            "body" => Json::encode($this->paymentData)
        ]);

        if ($response->getStatusCode() != 200) {
            throw new BadRequestException("Payment init failed. Reason phase: " . $response->getReasonPhrase());
        }

        if ($this->verifyPaymentData($response->json()) == false) {
            throw new BadRequestException("Payment init failed. Unable to verify signature");
        }

        return new Response($response->json(), $this->service, $this);
    }

    /**
     * @param array $data
     * @return string
     */
    public function signPaymentData($data)
    {
        $cartToSign = "";

        foreach ($data["cart"] as $key => $item) {
            $cartToSign .= implode("|", $item);
        }

        $dataToSign = $data["merchantId"] . "|" .  $data["orderNo"] . "|" . $data["dttm"] . "|" . $data["payOperation"] . "|" . $data["payMethod"] . "|" . $data["totalAmount"] ."|". $data["currency"] ."|". $data["closePayment"]  . "|". $data["returnUrl"] ."|". $data["returnMethod"] . "|" . $cartToSign . "|" . $data["description"] . "|" . $data["language"];

        if ($this->payOperation == self::CUSTOM_PAYMENT) {
            $dataToSign .= "|" . $data["customExpiry"];
        }

        return $this->sign($dataToSign);
    }

    /**
     * @param string $dataToSign
     * @return string
     */
    public function sign($dataToSign)
    {
        $handle = fopen($this->service->getPrivateKey()["path"], "r");

        if (!$handle) {
            throw new FileNotFoundException("File " . $this->service->getPrivateKey()["path"] . " not found.");
        }

        $privateKey = fread($handle, filesize($this->service->getPrivateKey()["path"]));

        fclose($handle);

        $privateKeyId = openssl_get_privatekey($privateKey, $this->service->getPrivateKey()["password"]);

        openssl_sign($dataToSign, $signature, $privateKeyId, OPENSSL_ALGO_SHA256);

        $signature = base64_encode($signature);

        openssl_free_key($privateKeyId);

        return $signature;
    }

    public function send()
    {
        $client = new Client();

        $response = $client->post($this->service->getUrl() . "/payment/init", [
            "headers" => [
                "Content-Type" => "application/json",
                "Accept" => "application/json;charset=UTF-8"
            ],
            "body" => Json::encode($this->paymentData)
        ]);

        if ($response->getStatusCode() != 200) {
            throw new BadRequestException("Payment init failed. Reason phase: " . $response->getReasonPhrase());
        }

        if ($this->verifyPaymentData($response->json()) == false) {
            throw new BadRequestException("Payment init failed. Unable to verify signature");
        }

        return new Response($response->json(), $this->service, $this);
    }

    public function verifyPaymentData($data)
    {
        $dataToVerify = $data["payId"] . "|" . $data["dttm"] . "|" . $data["resultCode"] . "|" . $data["resultMessage"];

        if (isset($data["paymentStatus"]) && !is_null($data["paymentStatus"])) {
            $dataToVerify .= "|" . $data["paymentStatus"];
        }

        if (isset($data["authCode"]) && !is_null($data["authCode"])) {
            $dataToVerify .= "|" . $data["authCode"];
        }

        if (isset($data["customerCode"]) && !is_null($data["customerCode"])) {
            $dataToVerify .= "|" . $data["customerCode"];
        }

        return $this->verify($dataToVerify, $data["signature"]);
    }

    public function verify($dataToVerify, $signatureBase64)
    {
        $handle = fopen($this->service->getPublicKey(), "r");

        if (!$handle) {
            throw new FileNotFoundException("File " . $this->service->getPublicKey() . " not found.");
        }

        $publicKey = fread($handle, filesize($this->service->getPublicKey()));

        fclose($handle);

        $publicKeyId = openssl_get_publickey($publicKey);
        $signature = base64_decode($signatureBase64);
        $result = openssl_verify($dataToVerify, $signature, $publicKeyId, OPENSSL_ALGO_SHA256);

        openssl_free_key($publicKeyId);

        return (($result != '1') ? false : true);
    }
}