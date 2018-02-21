<?php

namespace LZaplata\CsobPaymentGateway;


use Nette\Application\Responses\RedirectResponse;
use Nette\Object;
use Tracy\Debugger;

class Response extends Object
{
    /** @var array */
    private $response;

    /** @var Service */
    private $service;

    /** @var Payment|null */
    private $payment;

    public function __construct($response, Service $service, $payment)
    {
        if (isset($_POST) && !empty($_POST)) {
            $this->response = $_POST;
        } else {
            $this->response = $response;
        }

        $this->service = $service;
        $this->payment = $payment;
    }

    /**
     * @return string
     */
    public function getPayId()
    {
        return $this->response["payId"];
    }

    /**
     * @return string
     */
    public function getDttm()
    {
        return $this->response["dttm"];
    }

    /**
     * @return string
     */
    public function getRedirectUrl()
    {
        $dataToSign = $this->service->getMerchantId() . "|" . $this->getPayId() . "|" . $this->getDttm();
        $signature = $this->payment->sign($dataToSign);

        return $this->service->getUrl() . "/payment/process/" . $this->service->getMerchantId() . "/" . $this->getPayId() . "/" . $this->getDttm() . "/" . urlencode($signature);
    }

    /**
     * @return RedirectResponse
     */
    public function getRedirectResponse()
    {
        return new RedirectResponse($this->getRedirectUrl());
    }

    public function isOk()
    {
        $payment = new Payment($this->service);

        if (!empty($_POST)) {
            $response = (object)$_POST;

            if ($payment->verifyPaymentData($_POST)) {
                if ($response->resultCode == 0 && ($response->paymentStatus == 7 || $response->paymentStatus == 4)) {
                    return true;
                } else return false;
            } else return false;
        } else return false;
    }
}