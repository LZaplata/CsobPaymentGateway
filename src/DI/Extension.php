<?php

namespace LZaplata\CsobPaymentGateway\DI;


use Nette\DI\CompilerExtension;

class Extension extends CompilerExtension
{
    public $defaults = [
        "sandbox" => true
    ];

    public function loadConfiguration()
    {
        $config = $this->getConfig($this->defaults);
        $builder = $this->getContainerBuilder();

        $builder->addDefinition($this->prefix("config"))
            ->setClass("LZaplata\CsobPaymentGateway\Service", [$config["merchantId"], $config["sandbox"], $config["privateKey"], $config["publicKey"]]);
    }
}