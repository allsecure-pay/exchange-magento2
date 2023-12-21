<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Allsecureexchange\Allsecureexchange\Model;

require_once __DIR__.'/../Services/initClientAutoload.php';

use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Framework\DataObject;

use Exchange\Client\Client as AllsecureClient;
use Exchange\Client\Data\Customer as AllsecureCustomer;
use Exchange\Client\Transaction\Debit as AllsecureDebit;
use Exchange\Client\Transaction\Preauthorize as AllsecurePreauthorize;
use Exchange\Client\Transaction\Capture as AllsecureCapture;
use Exchange\Client\Transaction\Refund as AllsecureRefund;
use Exchange\Client\Transaction\VoidTransaction as AllsecureVoidTransaction;
use Exchange\Client\Transaction\Result as AllsecureResult;


class PayAbstract extends \Magento\Payment\Model\Method\AbstractMethod
{

    /**
     * @var bool
     */
    protected $_isGateway = true;
    
    /**
     * @var bool
     */
    protected $_canAuthorize = true;
    
    /**
     * @var bool
     */
    protected $_canCapture = true;
    
    /**
     * @var bool
     */
    protected $_canCapturePartial = false;
    
    /**
     * @var bool
     */
    protected $_canRefund = true;
    
    /**
     * @var bool
     */
    protected $_canVoid = true;
    
    /**
     * @var bool
     */
    protected $_canUseInternal = false;
    
    /**
     * @var bool
     */
    protected $_canUsePay = true;
    
    /**
     * @var bool
     */
    protected $_canUseForMultishipping = false;
    
    /**
     * @var bool
     */
    protected $_isInitializeNeeded = true;
    
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    /**
     * @var Magento\Checkout\Model\Session
     */
    protected $orderSession;
    
    /**
     * @var Allsecureexchange\Allsecureexchange\Helper\Data
     */
    protected $allsecurepayHelper;
    
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;
    
    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryList $directory_list
     */
    protected $directory_list;
    
    /**
     * @var \Magento\Framework\Filesystem\Driver\File $fileDriver
     */
    protected $fileDriver;
    
    /**
     * @var \Magento\Framework\HTTP\Client\Curl $curl
     */
    protected $curl;
    
    /**
     * @var \Magento\Sales\Model\Service\InvoiceService $invoiceService
     */
    protected $invoiceService;
    
    /**
     * @var \Magento\Framework\DB\Transaction $transaction
     */
    protected $transaction;
    
    /**
     * @var \Magento\Framework\Locale\Resolver $locale
     */
    protected $locale;
    
    /**
     * Constructor
     *
     * @param \Allsecureexchange\Allsecureexchange\Helper\Data $allsecurepayHelper
     * @param \Magento\Checkout\Model\Session $orderSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\App\Filesystem\DirectoryList $directory_list
     * @param \Magento\Framework\Filesystem\Driver\File $fileDriver
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Framework\DB\Transaction $transaction
     * @param \Magento\Framework\Locale\Resolver $locale
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param \Magento\Directory\Helper\Data $directory
	 * @param array $data
     *
     */
    public function __construct(
        \Allsecureexchange\Allsecureexchange\Helper\Data $allsecurepayHelper,
        \Magento\Checkout\Model\Session $orderSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\App\Filesystem\DirectoryList $directory_list,
        \Magento\Framework\Filesystem\Driver\File $fileDriver,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
		\Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Framework\Locale\Resolver $locale,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        \Magento\Directory\Helper\Data $directory = null,
		array $data = []
    ) {
        $this->allsecurepayHelper = $allsecurepayHelper;
        $this->orderSession = $orderSession;
        $this->orderFactory = $orderFactory;
        $this->customerSession = $customerSession;
        $this->urlBuilder = $urlBuilder;
        $this->directory_list = $directory_list;
        $this->fileDriver = $fileDriver;
        $this->curl = $curl;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->locale = $locale;

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data,
            $directory
        );
    }

    /**
     * Get instructions text from config
     *
     * @return string
     */
    public function getInstructions()
    {
        return $this->getConfigValue('instructions');
    }
    
    /**
     * Get config value
     *
     * @param string $key
     *
     * @return string
     */
    public function getConfigValue($key)
    {
        $pathConfig = 'payment/' . $this->_code . "/" . $key;
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->_scopeConfig->getValue($pathConfig, $storeScope);
    }
    
    /**
     * Get config value
     *
     * @param string $pathConfig
     *
     * @return string
     */
    public function getConfigValueByPath($pathConfig)
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->_scopeConfig->getValue($pathConfig, $storeScope);
    }
    
    /**
     * Is Initialize Needed
     *
     * @return bool
     */
    public function isInitializeNeeded()
    {
        return true;
    }
    
    /**
     * Initialize Payment
     *
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return bool
     */
    public function initialize($paymentAction, $stateObject)
    {
        $state = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
        $stateObject->setState($state);
        $stateObject->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(false);
    }
    
    /**
     * Get Current Session Checkout Object
     *
     * @return \Magento\Checkout\Model\Session
     */
    public function getCheckout()
    {
        return $this->orderSession;
    }
    
    /**
     * Get Checkout Quote
     *
     * @return \Magento\Quote\Model\Quote
     */
    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }
    
    /**
     * Get Last order
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->getCheckout()->getLastRealOrder();
    }
        
    /**
     * Get Order Placed Redirect Url
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return $this->urlBuilder->getUrl('allsecureexchange/additional/pay');
    }
    
    /**
     * Get Checkout Redirect Url
     *
     * @param array $params
     *
     * @return string
     */
    public function getCheckoutRedirectUrl($params = [])
    {
        return $this->urlBuilder->getUrl('allsecureexchange/additional/pay', $params);
    }
    
    /**
     * Get Webhook Url
     *
     * @param array $params
     *
     * @return string
     */
    public function getWebhookUrl($params = [])
    {
        return $this->urlBuilder->getUrl('allsecureexchange/additional/webhook', $params);
    }
    
    /**
     * Get Callback Url
     *
     * @param array $params
     *
     * @return string
     */
    public function getCallbackUrl($params = [])
    {
        return $this->urlBuilder->getUrl('allsecureexchange/additional/callback', $params);
    }
    
    /**
     * Get Cancel Url
     *
     * @param array $params
     *
     * @return string
     */
    public function getCancelUrl($params = [])
    {
        return $this->urlBuilder->getUrl('allsecureexchange/additional/cancel', $params);
    }
    
    /**
     * Get Cancel Url
     *
     * @param array $params
     *
     * @return string
     */
    public function getErrorUrl($params = [])
    {
        return $this->urlBuilder->getUrl('allsecureexchange/additional/error', $params);
    }
    
    /**
     * Get Checkout Success Url
     *
     * @return string
     */
    public function getCheckoutSuccessUrl($params = [])
    {
        return $this->urlBuilder->getUrl('checkout/onepage/success', $params);
    }
    
    /**
     * Get Store Url
     *
     * @return string
     */
    public function getStoreUrl()
    {
        return $this->urlBuilder->getUrl();
    }
    
    /**
     * Log Message
     *
     * @param mixed $message
     *
     * @return void
     */
    public function log($message)
    {
        $debug = $this->getConfigValueByPath('payment/allsecureexchange/debug');
        if ($debug) {
            $filepath = $this->directory_list->getPath('log') . '/allsecureexchange_additional.log';
            $this->fileDriver->filePutContents($filepath, date("Y-m-d H:i:s").": ", FILE_APPEND);
            $this->fileDriver->filePutContents(
                $filepath,
                print_r(// @codingStandardsIgnoreLine MEQP1.Security.DiscouragedFunction.Found
                    $message,
                    true
                ),
                FILE_APPEND
            );
            $this->fileDriver->filePutContents($filepath, "\n", FILE_APPEND);
        }
    }
    
    /**
     * Get IP Address
     *
     * @return string
     */
    public function getIPAddress()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    /**
     * Get Test API URL
     *
     * @return string
     */
    public function getTestApiUrl()
    {
        return 'https://asxgw.paymentsandbox.cloud/';
    }

    /**
     * Get Live API URL
     *
     * @return string
     */
    public function getLiveApiUrl()
    {
        return 'https://asxgw.com/';
    }
    
    /**
     * Get Allsecureexchange API Client
     *
     * @return AllsecureClient
     */
    
    public function getClient()
    {
        $haystack = $this->locale->getLocale();
        $lang = strstr($haystack, '_', true);
        
        $testMode = false;
        if ($this->getConfigValueByPath('payment/allsecureexchange/operation_mode') == 'test') {
            $testMode = true;
        }
        
        if ($testMode) {
            AllsecureClient::setApiUrl($this->getTestApiUrl());
        } else {
            AllsecureClient::setApiUrl($this->getLiveApiUrl());
        }
        
        $client = new AllsecureClient(
            trim($this->getConfigValueByPath('payment/allsecureexchange/api_user')), 
            trim($this->getConfigValueByPath('payment/allsecureexchange/api_passowrd')), 
            trim($this->getConfigValue('api_key')), 
            trim($this->getConfigValue('shared_secret')), 
            strtoupper($lang),
            $testMode
        );
        
        //$this->log((array)($client));
        
        return $client;
    }

    /**
     * Process Transaction
     *
     * @param $order
     * @param string $token
     * @param string $action
     * @return $this
     */
    public function processTransaction($order, $action)
    {
        $client = $this->getClient();
        
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();
        
        $billingStreet = $billingAddress->getStreet();
        $street1 = $billingStreet[0];
        $street2 = '';
        if (isset($billingStreet[1])) {
            $street2 = $billingStreet[1];
        }
        
        $shippingStreet = $shippingAddress->getStreet();
        $shipstreet1 = $shippingStreet[0];
        $shipstreet2 = '';
        if (isset($shippingStreet[1])) {
            $shipstreet2 = $shippingStreet[1];
        }
        
        $dob = $order->getCustomerDob();
        $gender = $order->getCustomerGender();

        $customer = new AllsecureCustomer();
        $customer->setIdentification($order->getCustomerId())
                ->setFirstName($order->getCustomerFirstname())
                ->setLastName($order->getCustomerLastname())
                ->setEmail($order->getCustomerEmail())
                ->setBillingAddress1($street1)
                ->setBillingCity($billingAddress->getCity())
                ->setBillingPostcode($billingAddress->getPostcode())
                ->setBillingState($billingAddress->getRegion() ? $billingAddress->getRegion() : $billingAddress->getCity())
                ->setBillingCountry($billingAddress->getCountryId())
                ->setBillingPhone($billingAddress->getTelephone())
                ->setShippingFirstName($shippingAddress->getFirstname())
                ->setShippingLastName($shippingAddress->getLastname())
                ->setShippingAddress1($shipstreet1)
                ->setShippingCity($shippingAddress->getCity())
                ->setShippingPostcode($shippingAddress->getPostcode())
                ->setShippingState($shippingAddress->getRegion())
                ->setShippingCountry($shippingAddress->getCountryId())
                ->setShippingPhone($shippingAddress->getTelephone())
                ->setIpAddress($this->getIPAddress());
        
        if (!empty($street2)) {
            $customer->setBillingAddress2($street2);
        }
        if (!empty($shipstreet2)) {
            $customer->setShippingAddress2($shipstreet2);
        }
        if (!empty($billingAddress->getCompany())) {
            $customer->setCompany($billingAddress->getCompany());
        }
        if (!empty($shippingAddress->getCompany())) {
            $customer->setShippingCompany($shippingAddress->getCompany());
        }
        if (!empty($dob)) {
            $customer->setBirthDate($dob);
        }
        if (!empty($gender)) {
            if ($gender == 1) {
                $gender = AllsecureCustomer::GENDER_MALE;
            } else if ($gender == 2) {
                $gender = AllsecureCustomer::GENDER_FEMALE;
            }
            $customer->setGender();
        }
        
        $amount = $order->getGrandTotal();
        $amount = round($amount, 2);
        
        if ($action == 'debit') {
            $transasction = new AllsecureDebit();
        } else {
            $transasction = new AllsecurePreauthorize();
        }
        
        $incrementId = $order->getIncrementId();
        $merchantTransactionId = $this->allsecurepayHelper->encodeOrderId($incrementId);
        
        $transasction->setMerchantTransactionId($merchantTransactionId)
            ->setAmount($amount)
            ->setCurrency($order->getOrderCurrencyCode())
            ->setCustomer($customer)
            ->setCallbackUrl($this->getWebhookUrl(['order_id' => $order->getIncrementId()]))
            ->setCancelUrl($this->getCancelUrl(['order_id' => $order->getIncrementId()]))
            ->setSuccessUrl($this->getCallbackUrl(['order_id' => $order->getIncrementId()]))
            ->setErrorUrl($this->getErrorUrl(['order_id' => $order->getIncrementId()]));

        if ($action == 'debit') {
            $this->log('Debit Transaction');
            $this->log((array)($transasction));
            $result = $client->debit($transasction);
        } else {
            $this->log('PreAuthorize Transaction');
            $this->log((array)($transasction));
            $result = $client->preauthorize($transasction);
        }
        $this->log((array)($result));
        return $result;
    }

    /**
     * Debit Transaction
     *
     * @param $order
     * @param string $token
     * 
     * @return $this
     */
    public function debitTransaction($order)
    {
        return $this->processTransaction($order, 'debit');
    }

    /**
     * Preauthorize Transaction
     *
     * @param $order
     * @param string $token
     * 
     * @return $this
     */
    public function preauthorizeTransaction($order)
    {
        return $this->processTransaction($order, 'preauthorize');
    }

    /**
     * Checking whether payment can be void or not
     *
     * @return bool
     */
    public function canVoid()
    {
        if ($this->getInfoInstance()->getAmountPaid()) {
            $this->_canVoid = false;
        }

        return $this->_canVoid;
    }
    
    /**
     * Create Invoice
     *
     * @param Object $order
     * @param srting $transaction_id
     * @param srting $invoice_type
     * @return void
     */
    public function createInvoice($order, $transaction_id, $invoice_type='offline')
    {
        if(!$order->canInvoice()) {
            //throw new \Exception(__('Cannot create an invoice.'));
        } else {
        
            $invoice = $this->invoiceService->prepareInvoice($order);

            if (!$invoice->getTotalQty()) {
                //throw new \Exception(__('Cannot create an invoice without products.'));
            } else {

                if ($invoice_type == 'offline') {
                    $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                } else {
                    $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::NOT_CAPTURE);
                    $invoice->setCanVoidFlag(1);
                }

                $invoice->register();
                $invoice->setTransactionId($transaction_id);
                $invoice->save();

                $transactionSave = $this->transaction->addObject($invoice);
                $this->transaction->addObject($invoice->getOrder());
                $transactionSave->save();
            }
        }
    }
    
    /**
     * Capture payment
     *
     * @param InfoInterface|Payment|Object $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->log('capture triggered');
        try {
            $order = $payment->getOrder();
            if ($order && $order->getId() > 0) {
                $order_id = $order->getId();
                $status = $this->allsecurepayHelper->getTransactionSingle($order_id, 'status');
                if ( !(($status == 'debited') || ($status == 'captured')) ) {
                    $transaction_id = $this->allsecurepayHelper->getTransactionSingle($order_id, 'transaction_id');
                    $currency = $order->getOrderCurrencyCode();
                    $merchantTransactionId = 'capture-'.$this->allsecurepayHelper->encodeOrderId($order->getIncrementId());

                    $client = $this->getClient();

                    $capture = new AllsecureCapture();
                    $capture->setTransactionId($merchantTransactionId)
                            ->setAmount($amount)
                            ->setCurrency($currency)
                            ->setReferenceTransactionId($transaction_id);
                    $this->log('capture request');
                    $this->log((array)($capture));
                    $result = $client->Capture($capture);
                    $this->log('capture response');
                    $this->log((array)($result));

                    if ($result->getReturnType() == AllsecureResult::RETURN_TYPE_FINISHED) {
                        $gatewayReferenceId = $result->getUuid();
                        $this->allsecurepayHelper->updateTransaction($order_id, 'status', 'captured');
                        $this->allsecurepayHelper->updateTransaction($order_id, 'transaction_id', $gatewayReferenceId);
                        $this->allsecurepayHelper->updateTransactionResponse($order_id, 'Authorized_Id', $transaction_id);
                        $comment1 = __('Allsecureexchange payment is successfully captured. ');
                        $comment2 = __('Transaction ID: '). $gatewayReferenceId;
                        $comment = $comment1.$comment2;
                        $order->addStatusHistoryComment($comment);
                        $order->save();
                        $payment->setTransactionId($gatewayReferenceId);
                    } elseif ($result->getReturnType() == AllsecureResult::RETURN_TYPE_ERROR) {
                        $error = $result->getFirstError();
                        $errorCode = $error->getCode();
                        if (empty($errorCode)) {
                            $errorCode = $error->getAdapterCode();
                        }
                        $errorMessage = self::getErrorMessageByCode($errorCode);

                        $comment1 = __('Allsecureexchange capture request is failed.');
                        $comment = $comment1.$errorMessage;
                        throw new \Magento\Framework\Exception\LocalizedException(__($comment));
                    } else {
                        throw new \Magento\Framework\Exception\LocalizedException(
                            __("Unknown response from gateway.")
                        );
                    }
                }
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->log('capture catch: '.$e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        } catch (\Exception $e) {
            $this->log('capture catch: '.$e->getMessage());
            throw new \Exception($e->getMessage());
        }
        return $this;
    }
    /**
     * Refund payment
     *
     * @param InfoInterface|Payment|Object $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */   
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->log('refund triggered');
        try {
            $order = $payment->getOrder();
            if ($order && $order->getId() > 0) {
                $order_id = $order->getId();
                $status = $this->allsecurepayHelper->getTransactionSingle($order_id, 'status');
                if ( ($status == 'debited') || ($status == 'captured') ) {
                    $transaction_id = $this->allsecurepayHelper->getTransactionSingle($order_id, 'transaction_id');
                    $currency = $order->getOrderCurrencyCode();
                    $merchantTransactionId = 'refund-'.$this->allsecurepayHelper->encodeOrderId($order->getIncrementId());

                    $client = $this->getClient();

                    $refund = new AllsecureRefund();
                    $refund->setMerchantTransactionId($merchantTransactionId)
                            ->setAmount($amount)
                            ->setCurrency($currency)
                            ->setReferenceUuid($transaction_id);
                    
                    $this->log('refund request');
                    $this->log((array)($refund));
                    $result = $client->refund($refund);
                    $this->log('refund response');
                    $this->log((array)($result));

                    if ($result->getReturnType() == AllsecureResult::RETURN_TYPE_FINISHED) {
                        $gatewayReferenceId = $result->getUuid();
                        $this->allsecurepayHelper->updateTransaction($order_id, 'status', 'refunded');
                        $this->allsecurepayHelper->updateTransaction($order_id, 'transaction_id', $gatewayReferenceId);
                        $this->allsecurepayHelper->updateTransactionResponse($order_id, 'Captured_Id', $transaction_id);
                        $comment1 = __('Allsecureexchange payment is successfully refunded. ');
                        $comment2 = __('Transaction ID: '). $gatewayReferenceId;
                        $comment = $comment1.$comment2;
                        $order->addStatusHistoryComment($comment);
                        $order->save();
                        $payment->setTransactionId($gatewayReferenceId);
                        if ($payment->getOrder()->getInvoiceCollection()->count() > 0) {
                            $Authorized_Id = $this->allsecurepayHelper->getTransactionResponseSingle($order_id, 'Authorized_Id');
                            $invoices = $payment->getOrder()->getInvoiceCollection();
                            foreach ($invoices as $invoice) {
                                if ($invoice->getTransactionId() == $transaction_id) {
                                    $this->allsecurepayHelper->updateInvoice($invoice->getId(), 'can_void_flag', 0);
                                }
                            }
                        }
                    } elseif ($result->getReturnType() == AllsecureResult::RETURN_TYPE_ERROR) {
                        $error = $result->getFirstError();
                        $errorCode = $error->getCode();
                        if (empty($errorCode)) {
                            $errorCode = $error->getAdapterCode();
                        }
                        $errorMessage = self::getErrorMessageByCode($errorCode);

                        $comment1 = __('Allsecureexchange refund request is failed.').' ';
                        $comment = $comment1.$errorMessage;
                        throw new \Magento\Framework\Exception\LocalizedException(__($comment));
                    } else {
                        throw new \Magento\Framework\Exception\LocalizedException(__("Unknown response from gateway."));
                    }
                } else {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __("Refund is not possible for this order because order was neither debited nor captured.")
                    );
                }
            } else {
                throw new \Exception(__("Unknown error."));
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->log('refund catch: '.$e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        } catch (\Exception $e) {
            $this->log('refund catch: '.$e->getMessage());
            throw new \Exception($e->getMessage());
        }
        return $this;
    }
    /**
     * Void payment
     *
     * @param InfoInterface|Payment|Object $payment
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */
    public function void(\Magento\Payment\Model\InfoInterface $payment) 
    {
        $this->log('void triggered');
        try {
            $order = $payment->getOrder();
            if ($order && $order->getId() > 0) {
                $order_id = $order->getId();
                $status = $this->allsecurepayHelper->getTransactionSingle($order_id, 'status');
                if ( ($status == 'preauthorized')) {
                    $transaction_id = $this->allsecurepayHelper->getTransactionSingle($order_id, 'transaction_id');
                    $currency = $order->getOrderCurrencyCode();
                    $merchantTransactionId = 'void-'.$this->allsecurepayHelper->encodeOrderId($order->getIncrementId());

                    $client = $this->getClient();

                    $void = new AllsecureVoidTransaction();
                    $void->setMerchantTransactionId($merchantTransactionId)
                            ->setReferenceUuid($transaction_id);
                    
                    $this->log('void request');
                    $this->log((array)($void));
                    $result = $client->void($void);
                    $this->log('void response');
                    $this->log((array)($result));

                    if ($result->getReturnType() == AllsecureResult::RETURN_TYPE_FINISHED) {
                        $gatewayReferenceId = $result->getUuid();
                        $this->allsecurepayHelper->updateTransaction($order_id, 'status', 'voided');
                        $this->allsecurepayHelper->updateTransaction($order_id, 'transaction_id', $gatewayReferenceId);
                        $this->allsecurepayHelper->updateTransactionResponse($order_id, 'Authorized_Id', $transaction_id);
                        $comment1 = __('Allsecureexchange payment authorization is successfully voided. ');
                        $comment2 = __('Transaction ID: '). $gatewayReferenceId;
                        $comment = $comment1.$comment2;
                        $order->addStatusHistoryComment($comment);
                        $order->save();
                        $payment->setTransactionId($gatewayReferenceId);
                        
                        if ($payment->getOrder()->getInvoiceCollection()->count() > 0) {
                            $Authorized_Id = $this->allsecurepayHelper->getTransactionResponseSingle($order_id, 'Authorized_Id');
                            $invoices = $payment->getOrder()->getInvoiceCollection();
                            foreach ($invoices as $invoice) {
                                if ($invoice->getTransactionId() == $Authorized_Id) {
                                    $this->allsecurepayHelper->updateInvoice($invoice->getId(), 'can_void_flag', 0);
                                }
                            }
                        }
                    } elseif ($result->getReturnType() == AllsecureResult::RETURN_TYPE_ERROR) {
                        $error = $result->getFirstError();
                        $errorCode = $error->getCode();
                        if (empty($errorCode)) {
                            $errorCode = $error->getAdapterCode();
                        }
                        $errorMessage = self::getErrorMessageByCode($errorCode);

                        $comment1 = __('Allsecureexchange void request is failed.').' ';
                        $comment = $comment1.$errorMessage;
                        throw new \Magento\Framework\Exception\LocalizedException(__($comment));
                    } else {
                        throw new \Magento\Framework\Exception\LocalizedException(__("Unknown response from gateway."));
                    }
                } else {
                    if ($payment->getOrder()->getInvoiceCollection()->count() > 0) {
                        $Authorized_Id = $this->allsecurepayHelper->getTransactionResponseSingle($order_id, 'Authorized_Id');
                        $invoices = $payment->getOrder()->getInvoiceCollection();
                        foreach ($invoices as $invoice) {
                            if ($invoice->getTransactionId() == $Authorized_Id) {
                                $this->allsecurepayHelper->updateInvoice($invoice->getId(), 'can_void_flag', 0);
                            }
                        }
                    }
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __("Void is not possible for this order because order was neither debited nor captured.")
                    );
                }
            } else {
                throw new \Exception(__("Unknown error."));
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->log('void catch: '.$e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        } catch (\Exception $e) {
            $this->log('void catch: '.$e->getMessage());
            throw new \Exception($e->getMessage());
        }
        return $this;
    }
    
    public static function getErrorMessageByCode($code) 
    {
        $unknownError = __('Unknown error');
        $errors = array(
            '1000' => __('CONFIG ERROR. Some fundamental error in your request'),
            '1001' => __('CONFIG ERROR. The upstream system responded with an unknown response'),
            '1002' => __('CONFIG ERROR. Request data are malformed or missing'),
            '1003' => __('CONFIG ERROR. Transaction could not be processed'),
            '1004' => __('CONFIG ERROR. The request signature you provided was wrong'),
            '1005' => __('CONFIG ERROR. The XML you provided was malformed or invalid'),
            '1006' => __('CONFIG ERROR. Preconditions failed, e.g. capture on a failed authorize'),
            '1007' => __('CONFIG ERROR. Something is wrong your configuration, please contact your integration engineer'),
            '1008' => __('CONFIG ERROR. Unexpected system error'),
            '9999' => __('CONFIG ERROR. We received an error which is not (yet) mapped to a better error code'),
            '2001' => __('Account closed. The customer cancelled permission for his payment instrument externally'),
            '2002' => __('User cancelled. Transaction was cancelled by customer'),
            '2003' => __('Transaction declined. Please try again later or change the card'),
            '2004' => __('Quota regulation. Card limit reached'),
            '2005' => __('Transaction expired. Customer took to long to submit his payment info'),
            '2006' => __('Insufficient funds. Card limit reached'),
            '2007' => __('Incorrect payment info. Double check and try again'),
            '2008' => __('Invalid card. Try with some other card'),
            '2009' => __('Expired card. Try with some other card'),
            '2010' => __('Invalid card. Call your bank immediately'),
            '2011' => __('Unsupported card. Try with some other card'),
            '2012' => __('Transaction cancelled'),
            '2013' => __('Transaction declined. Please try again later or call your bank'),
            '2014' => __('Transaction declined. Please try again later or call your bank'),
            '2015' => __('Transaction declined. Please try again later or call your bank'),
            '2016' => __('Transaction declined. Please try again later or call your bank'),
            '2017' => __('Invalid IBAN. Double check and try again'),
            '2018' => __('Invalid BIC. Double check and try again'),
            '2019' => __('Customer data invalid. Double check and try again'),
            '2020' => __('CVV required. Double check and try again'),
            '2021' => __('3D-Secure Verification failed. Please call your bank or try with a non 3-D Secure card'),
            '3001' => __('COMMUNICATION PROBLEM. Timeout. Try again after a short pause'),
            '3002' => __('COMMUNICATION PROBLEM. Transaction not allowed'),
            '3003' => __('COMMUNICATION PROBLEM. System temporary unavailable. Try again after a short pause'),
            '3004' => __('Duplicate transaction ID'),
            '3005' => __('COMMUNICATION PROBLEM. Try again after a short pause'),
            '7001' => __('Schedule request is invalid'),
            '7002' => __('Schedule request failed'),
            '7005' => __('Schedule action is not valid'),
            '7010' => __('RegistrationId is required'),
            '7020' => __('RegistrationId is not valid'),
            '7030' => __('The registrationId must point to a "register", "debit+register" or "preuth+register"'),
            '7035' => __('Initial transaction is not a "register", "debit+register" or "preuth+register"'),
            '7036' => __('The period between the initial and second transaction must be greater than 24 hours'),
            '7040' => __('The scheduleId is not valid or does not match to the connector'),
            '7050' => __('The startDateTime is invalid or older than 24 hours'),
            '7060' => __('The continueDateTime is invalid or older than 24 hours'),
            '7070' => __('The status of the schedule is not valid for the requested operation')
        );
        
        return isset($errors[$code]) ? $errors[$code] : $unknownError;
    }
}
