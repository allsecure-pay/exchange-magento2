<?php

namespace Allsecureexchange\Allsecureexchange\Model\Payment;

use Allsecureexchange\Allsecureexchange\Model\Ui\ConfigProvider;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\AbstractMethod;

class CreditCard extends AbstractMethod
{
    protected $_code = ConfigProvider::CREDITCARD_CODE;

    protected $_isGateway = true;

    protected $_canUseInternal = false;

    public function refund(InfoInterface $payment, $amount)
    {

    }
}
