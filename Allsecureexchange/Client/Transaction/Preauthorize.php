<?php

namespace Allsecureexchange\Client\Transaction;

use Allsecureexchange\Client\Transaction\Base\AbstractTransactionWithReference;
use Allsecureexchange\Client\Transaction\Base\AddToCustomerProfileInterface;
use Allsecureexchange\Client\Transaction\Base\AddToCustomerProfileTrait;
use Allsecureexchange\Client\Transaction\Base\AmountableInterface;
use Allsecureexchange\Client\Transaction\Base\AmountableTrait;
use Allsecureexchange\Client\Transaction\Base\ItemsInterface;
use Allsecureexchange\Client\Transaction\Base\ItemsTrait;
use Allsecureexchange\Client\Transaction\Base\OffsiteInterface;
use Allsecureexchange\Client\Transaction\Base\OffsiteTrait;
use Allsecureexchange\Client\Transaction\Base\ScheduleInterface;
use Allsecureexchange\Client\Transaction\Base\ScheduleTrait;

/**
 * Preauthorize: Reserve a certain amount, which can be captured (=charging) or voided (=revert) later on.
 *
 * @package Allsecureexchange\Client\Transaction
 */
class Preauthorize extends AbstractTransactionWithReference implements AmountableInterface, OffsiteInterface, ItemsInterface, ScheduleInterface, AddToCustomerProfileInterface {
    use OffsiteTrait;
    use AmountableTrait;
    use ItemsTrait;
    use ScheduleTrait;
    use AddToCustomerProfileTrait;

    const TRANSACTION_INDICATOR_SINGLE = 'SINGLE';
    const TRANSACTION_INDICATOR_INITIAL = 'INITIAL';
    const TRANSACTION_INDICATOR_RECURRING = 'RECURRING';
    const TRANSACTION_INDICATOR_CARDONFILE = 'CARDONFILE';
    const TRANSACTION_INDICATOR_CARDONFILE_MERCHANT = 'CARDONFILE_MERCHANT';

    /**
     * @var bool
     */
    protected $withRegister = false;

    /**
     * @var string
     */
    protected $transactionIndicator;

    /**
     * @return boolean
     */
    public function isWithRegister() {
        return $this->withRegister;
    }

    /**
     * set true if you want to register a user vault together with the preauthorize
     *
     * @param boolean $withRegister
     *
     * @return $this
     */
    public function setWithRegister($withRegister) {
        $this->withRegister = $withRegister;
        return $this;
    }

    /**
     * @return string
     */
    public function getTransactionIndicator() {
        return $this->transactionIndicator;
    }

    /**
     * @param string $transactionIndicator
     */
    public function setTransactionIndicator($transactionIndicator) {
        $this->transactionIndicator = $transactionIndicator;
        return $this;
    }
}
