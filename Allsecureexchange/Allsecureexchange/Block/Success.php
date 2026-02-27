<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Allsecureexchange\Allsecureexchange\Block;

require_once __DIR__.'/../Services/initClientAutoload.php';

use Exchange\Client\Client as AllsecureClient;
use Exchange\Client\StatusApi\StatusRequestData;

class Success extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Allsecureexchange\Allsecureexchange\Helper\Data $helper
     */
    protected $helper;
    
    /**
     * @var \Allsecureexchange\Allsecureexchange\Model\Pay $payment
     */
    protected $payment;
    
    /**
     * @var \Magento\Sales\Model\OrderFactory $orderFactory
     */
    protected $orderFactory;
    
    /**
     * @var \Magento\Framework\App\RequestInterface $request
     */
    protected $request;

    /**
     * Constructor
     *
     * @param \Allsecureexchange\Allsecureexchange\Helper\Data $helper
     * @param \Allsecureexchange\Allsecureexchange\Model\Pay $payment
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     */
    public function __construct(
        \Allsecureexchange\Allsecureexchange\Helper\Data $helper,
        \Allsecureexchange\Allsecureexchange\Model\Pay $payment,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\View\Element\Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper = $helper;
        $this->payment = $payment;
        $this->orderFactory = $orderFactory;
        $this->request = $request;
    }
    
    /**
     * Get order object from block parameter
     *
     * return \Magento\Sales\Model\OrderFactory object
     */
    public function getRealOrder()
    {
        $order_id = $this->request->getParam('mage_order_incr_id');
        return $this->orderFactory->create()->loadByIncrementId($order_id);
    }
    
    /**
     * Get Template parameters to the block template
     *
     * return bool|array
     */
    public function getTemplateParams()
    {
        $model = $this->payment;
        $order = $this->getRealOrder();
        if ($model->getConfigValue('transaction_confirmation_page') && $order && $order->getId()) {
            $model = $order->getPayment()->getMethodInstance();
            $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();
            if (preg_match('/allsecureexchange/', $paymentMethod)) {
                $uuid = $this->helper->getTransactionResponseSingle($order->getId(), 'uuid');
                $installment_number = $this->helper->getTransactionResponseSingle($order->getId(), 'installment_number');

                $client = $model->getClient();
                
                $statusRequestData = new StatusRequestData();
		$statusRequestData->setUuid($uuid);
		$statusResult = $client->sendStatusRequest($statusRequestData);
                
                $params = array();
                if ($statusResult->hasErrors()) {
                    $errors = $statusResult->getErrors();
                    $params['response_status'] = 'error';
                    $params['errors'] = $errors;
                } else {
                    $params['response_status'] = 'success';
                    
                    $result = $statusResult->getTransactionStatus();
                    $transactionType = $statusResult->getTransactionType();
                    $amount = $statusResult->getAmount();
                    $currency = $statusResult->getCurrency();
                    $cardData = $statusResult->getreturnData();
                    
                    $binBrand = '';
                    $lastFourDigits = '';

                    if (method_exists($cardData, 'getType')) {
                        $binBrand = strtoupper($cardData->getType());
                    }
                    if (method_exists($cardData, 'getlastFourDigits')) {
                        $lastFourDigits = $cardData->getlastFourDigits();
                    }

                    $bankName = '';
                    $accountOwner = '';
                    $iban = '';

                    if (method_exists($cardData, 'getAccountOwner')) {
                        $accountOwner = strtoupper($cardData->getAccountOwner());
                    }
                    if (method_exists($cardData, 'getBankName')) {
                        $bankName = $cardData->getBankName();
                    }
                    if (method_exists($cardData, 'getIban')) {
                        $iban = $cardData->getIban();
                    }
                    
                    $transactionId = $statusResult->getTransactionUuid() ?? NULL;
                    $extraData = $statusResult->getextraData();

                    if ( isset($extraData['authCode']) ) {
                            $authCode = $extraData['authCode'];		
                    } elseif (isset($extraData['adapterReferenceId']) ) {
                            $authCode = $extraData['adapterReferenceId'];	
                    } else {
                            $authCode = NULL;	
                    }
                    $timestamp = date("Y-m-d H:i:s");
                    
                    $params['lastFourDigits'] = $lastFourDigits;
                    $params['transactionType'] = $transactionType;
                    $params['binBrand'] = $binBrand;
                    $params['authCode'] = $authCode;
                    $params['transactionId'] = $transactionId;
                    $params['timestamp'] = $timestamp;
                    $params['installment_number'] = $installment_number;
                    $params['accountOwner'] = $accountOwner;
                    $params['bankName'] = $bankName;
                    $params['iban'] = $iban;
                    $params['amount'] = $amount;
                    $params['currency'] = $currency;
                }
		return $params;
            }
        }
        return false;
    }
}
