<?php

namespace allsecureexchange\allsecureexchange\Controller\Payment;

use allsecureexchange\Client\Transaction\Debit;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data;
use Magento\Sales\Model\Order;

class Frontend extends Action
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $session;

    /**
     * @var \Magento\Checkout\Api\PaymentInformationManagementInterface
     */
    private $paymentInformation;

    /**
     * @var Data
     */
    private $paymentHelper;

    /**
     * @var \allsecureexchange\allsecureexchange\Helper\Data
     */
    private $allsecureexchangeHelper;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * Frontend constructor.
     * @param Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Checkout\Api\PaymentInformationManagementInterface $paymentInformation,
        Data $paymentHelper,
        \allsecureexchange\allsecureexchange\Helper\Data $allsecureexchangeHelper,
        UrlInterface $urlBuilder,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->session = $checkoutSession;
        $this->paymentInformation = $paymentInformation;
        $this->paymentHelper = $paymentHelper;
        $this->urlBuilder = $urlBuilder;
        $this->allsecureexchangeHelper = $allsecureexchangeHelper;
        $this->resultJsonFactory = $resultJsonFactory;
    }
	

    public function execute()
    {
        $request = $this->getRequest()->getPost()->toArray();
        $response = $this->resultJsonFactory->create();

        $paymentMethod = 'allsecureexchange_creditcard';
		
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance(); 
		$store = $objectManager->get('Magento\Framework\Locale\Resolver'); 
        

        //TODO: SELECT CORRECT PAYMENT SETTINGS
        \allsecureexchange\Client\Client::setApiUrl($this->allsecureexchangeHelper->getGeneralConfigData('host'));
        $client = new \allsecureexchange\Client\Client(
            $this->allsecureexchangeHelper->getGeneralConfigData('username'),
            $this->allsecureexchangeHelper->getGeneralConfigData('password'),
            $this->allsecureexchangeHelper->getPaymentConfigData('api_key', $paymentMethod, null),
            $this->allsecureexchangeHelper->getPaymentConfigData('shared_secret', $paymentMethod, null),
			strtolower(substr($store->getLocale(), 0, 2 ))
        );

        $order = $this->session->getLastRealOrder();

        $debit = new Debit();
        if ($this->allsecureexchangeHelper->getPaymentConfigDataFlag('seamless', $paymentMethod)) {
            $token = (string) $request['token'];

            if (empty($token)) {
                die('empty token');
            }

            $debit->setTransactionToken($token);
        }
        $debit->addExtraData('3dsecure', 'OPTIONAL');

        $debit->setTransactionId($order->getIncrementId());
        $debit->setAmount(\number_format($order->getGrandTotal(), 2, '.', ''));
        $debit->setCurrency($order->getOrderCurrency()->getCode());

        $customer = new \allsecureexchange\Client\Data\Customer();
        $customer->setFirstName($order->getCustomerFirstname());
        $customer->setLastName($order->getCustomerLastname());
        $customer->setEmail($order->getCustomerEmail());

        $customer->setIpAddress($order->getRemoteIp());

        $billingAddress = $order->getBillingAddress();
        if ($billingAddress !== null) {
            $customer->setBillingAddress1($billingAddress->getStreet()[0]);
            $customer->setBillingPostcode($billingAddress->getPostcode());
            $customer->setBillingCity($billingAddress->getCity());
            $customer->setBillingCountry($billingAddress->getCountryId());
            $customer->setBillingPhone($billingAddress->getTelephone());
        }
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress !== null) {
            $customer->setShippingCompany($shippingAddress->getCompany());
            $customer->setShippingFirstName($shippingAddress->getFirstname());
            $customer->setShippingLastName($shippingAddress->getLastname());
            $customer->setShippingAddress1($shippingAddress->getStreet()[0]);
            $customer->setShippingPostcode($shippingAddress->getPostcode());
            $customer->setShippingCity($shippingAddress->getCity());
            $customer->setShippingCountry($shippingAddress->getCountryId());
        }

        $debit->setCustomer($customer);

        $baseUrl = $this->urlBuilder->getRouteUrl('allsecureexchange');

        $debit->setSuccessUrl($this->urlBuilder->getUrl('checkout/onepage/success'));
        $debit->setCancelUrl($baseUrl . 'payment/redirect?status=cancel');
        $debit->setErrorUrl($baseUrl . 'payment/redirect?status=error');

        $debit->setCallbackUrl($baseUrl . 'payment/callback');

        $this->prepare3dSecure2Data($debit, $order);

        $paymentResult = $client->debit($debit);

        if (!$paymentResult->isSuccess()) {
            $response->setData([
                'type' => 'error',
                'errors' => $paymentResult->getFirstError()->getMessage()
            ]);
            return $response;
        }

        if ($paymentResult->getReturnType() == \allsecureexchange\Client\Transaction\Result::RETURN_TYPE_ERROR) {

            $response->setData([
                'type' => 'error',
                'errors' => $paymentResult->getFirstError()->getMessage()
            ]);
            return $response;

        } elseif ($paymentResult->getReturnType() == \allsecureexchange\Client\Transaction\Result::RETURN_TYPE_REDIRECT) {

            $response->setData([
                'type' => 'redirect',
                'url' => $paymentResult->getRedirectUrl()
            ]);

            return $response;

        } elseif ($paymentResult->getReturnType() == \allsecureexchange\Client\Transaction\Result::RETURN_TYPE_PENDING) {
            //payment is pending, wait for callback to complete

            //setCartToPending();

        } elseif ($paymentResult->getReturnType() == \allsecureexchange\Client\Transaction\Result::RETURN_TYPE_FINISHED) {

            $response->setData([
                'type' => 'finished',
            ]);
        }

        return $response;
    }

    private function prepare3dSecure2Data(Debit $debit, Order $order)
    {
        $debit->addExtraData('3ds:channel', '02'); // Browser
        $debit->addExtraData('3ds:authenticationIndicator ', '01'); // Payment transaction

        if ($order->getCustomerIsGuest()) {
            $debit->addExtraData('3ds:cardholderAuthenticationMethod', '01');
            $debit->addExtraData('3ds:cardholderAccountAgeIndicator', '01');
        } else {
            $debit->addExtraData('3ds:cardholderAuthenticationMethod', '02');
            //$debit->addExtraData('3ds:cardholderAccountDate', \date('Y-m-d', $order->getCustomer()->getCreatedAtTimestamp()));
        }

        //$debit->addExtraData('3ds:shipIndicator', \date('Y-m-d', $order->getCustomer()->getCreatedAtTimestamp()));

        if ($order->getShippigAddressId() == $order->getBillingAddressId()) {
            $debit->addExtraData('3ds:billingShippingAddressMatch ', 'Y');
        } else {
            $debit->addExtraData('3ds:billingShippingAddressMatch ', 'N');
        }

    }
}
