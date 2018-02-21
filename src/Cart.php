<?php

namespace LZaplata\CsobPaymentGateway;


use Nette\Object;
use Nette\Utils\Strings;

class Cart extends Object
{
    /** @var array */
    public $items;

    /**
     * @param string $name
     * @param int $quantity
     * @param float $amount
     * @param string $description
     */
    public function setItem($name, $quantity, $amount, $description)
    {
        $this->items[] = [
            "name" => Strings::truncate($name, 17),
            "quantity" => $quantity,
            "amount" => $amount,
            "description" => Strings::truncate($description, 37)
        ];
    }

    /**
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }
}