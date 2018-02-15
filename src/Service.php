<?php

namespace LZaplata\CsobPaymentGateway;


use Nette\Application\Responses\RedirectResponse;
use Nette\FileNotFoundException;
use Nette\Object;
use Nette\Utils\Json;
use Nette\Utils\Strings;

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

    /** @var array */
    public $orderedParameters = [
        1 => "merchantId",
        2 => "orderNo",
        3 => "dttm",
        4 => "payOperation",
        5 => "payMethod",
        6 => "totalAmount",
        7 => "currency",
        8 => "closePayment",
        9 => "returnUrl",
        10 => "returnMethod",
        11 => "cart",
        12 => "description",
        15 => "language",
        16 => "signature"
    ];

    /**
     * Service constructor.
     * @param int $merchantId
     * @param bool $sandbox
     * @param string $privateKey
     * @param string $publicKey
     */
    public function __construct($merchantId, $sandbox, $privateKey, $publicKey)
    {
        $this->setMerchantId($merchantId);
        $this->setSandbox($sandbox);
        $this->setPrivateKey($privateKey);
        $this->setPublicKey($publicKey);
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
     * @param int $sandbox
     * @return self
     */
    public function setSandbox($sandbox)
    {
        $this->sandbox = $sandbox;

        if ($sandbox) {
            $this->url = "https://iapi.iplatebnibrana.csob.cz/api/v1.5";
        } else {
            $this->url = "https://api.platebnibrana.csob.cz/api/v1.6";
        }

        return $this;
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
     * @param int $publicKey
     * @return self
     */
    public function setPublicKey($publicKey)
    {
        $this->publicKey = $publicKey;

        return $this;
    }

    /**
     * @param $values
     * @return mixed
     * @throws \Nette\Utils\JsonException
     */
    public function createOrder($values)
    {
        foreach ($values["cart"] as $key => $product) {
            foreach ($product as $item => $value) {
                if ($item == "name") {
                    $values["cart"][$key][$item] = Strings::truncate($value, 17);
                }

                if ($item == "description") {
                    $values["cart"][$key][$item] = Strings::truncate($value, 37);
                }
            }
        }

        $values["merchantId"] = htmlspecialchars($this->merchantId);
        $values["payOperation"] = "payment";
        $values["payMethod"] = "card";
        $values["returnMethod"] = "POST";
        $values["closePayment"] = "true";
        $values["language"] = "CZ";
        $values["dttm"] = date("YmdHis");
        $values["description"] = "purchase";
        $values["signature"] = $this->createSignature($values);

        $ch = curl_init($this->url . "/payment/init");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, Json::encode($values));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array (
            'Content-Type: application/json',
            'Accept: application/json;charset=UTF-8'
        ));

        $order = curl_exec($ch);

        curl_close($ch);

        return $order;
    }

    /**
     * @param array $values
     * @return string
     */
    public function createSignature($values)
    {
        unset($this->orderedParameters[16]);

        $parameters = [];

        foreach ($this->orderedParameters as $key => $orderedParameter) {
            if ($orderedParameter == "cart") {
                $cart = array();

                foreach ($values[$orderedParameter] as $product) {
                    $cart[] = implode("|", $product);
                }

                $parameters[$orderedParameter] = implode("|", $cart);
            } else {
                $parameters[$orderedParameter] = $values[$orderedParameter];
            }

        }

        return $this->sign(implode("|", $parameters));
    }

    /**
     * @param string $data
     * @return string
     */
    public function sign($data)
    {
        $handle = fopen($this->privateKey["path"], "r");

        if (!$handle) {
            throw new FileNotFoundException("File " . $this->privateKey["path"] . " not found.");
        }

        $privateKey = fread($handle, filesize($this->privateKey["path"]));

        fclose($handle);

        $privateKeyId = openssl_get_privatekey($privateKey, $this->privateKey["password"]);

        openssl_sign($data, $signature, $privateKeyId);

        $signature = base64_encode($signature);

        openssl_free_key($privateKeyId);

        return $signature;
    }

    /**
     * @param mixed $order
     * @return string
     * @throws \Nette\Utils\JsonException
     */
    public function pay($order)
    {
        $response = Json::decode($order);

        $this->verify($response);

        $data = $this->merchantId . "|" . $response->payId . "|" . $response->dttm;
        $signature = $this->sign($data);

        return new RedirectResponse($this->url. "/payment/process/". $this->merchantId . "/" . $response->payId . "/" . $response->dttm . "/" . urlencode($signature));
    }

    /**
     * @param array $response
     */
    public function verify($response)
    {
        $data = $response->payId . "|" . $response->dttm . "|" . $response->resultCode . "|" . $response->resultMessage;

        if(!is_null($response->paymentStatus)) {
            $data .= "|" . $response->paymentStatus;
        }

        $handle = fopen($this->publicKey, "r");

        if (!$handle) {
            throw new FileNotFoundException("File " . $this->publicKey . " not found.");
        }

        $publicKey = fread($handle, filesize($this->publicKey));

        fclose($handle);

        $publicKeyId = openssl_get_publickey($publicKey);
        $signature = base64_decode($response->signature);
        $result = openssl_verify($data, $signature, $publicKeyId);

        openssl_free_key($publicKeyId);
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
}