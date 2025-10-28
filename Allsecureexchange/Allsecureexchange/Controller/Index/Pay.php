<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Allsecureexchange\Allsecureexchange\Controller\Index;

require_once __DIR__.'/../../Services/initClientAutoload.php';

use Exchange\Client\Transaction\Result as AllsecureResult;

class Pay extends \Magento\Framework\App\Action\Action
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
     * @var \Magento\Checkout\Model\Session $checkoutSession
     */
    protected $checkoutSession;
    
    /**
     * @var \Magento\Framework\Controller\Result\Redirect $resultRedirectFactory
     */
    protected $resultRedirectFactory;
    
    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $orderSender;
    
    /**
     * Constructor
     *
     * @param \Allsecureexchange\Allsecureexchange\Helper\Data $helper
     * @param \Allsecureexchange\Allsecureexchange\Model\Pay $payment
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\Controller\Result\Redirect $resultRedirectFactory
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Magento\Framework\App\Action\Context $context
     */
    public function __construct(
        \Allsecureexchange\Allsecureexchange\Helper\Data $helper,
        \Allsecureexchange\Allsecureexchange\Model\Pay $payment,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Controller\Result\Redirect $resultRedirectFactory,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Framework\App\Action\Context $context
    ) {
        $this->helper = $helper;
        $this->payment = $payment;
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->orderSender = $orderSender;
        parent::__construct($context);
    }

    /**
     * Executor
     *
     * @return void
     */
    public function execute()
    {
        try {
            $model = $this->payment;
            $session = $this->checkoutSession;

            $order = $model->getOrder();

            if ($order && $order->getId() > 0) {
                $order_id = $order->getId();
                $payment = $order->getPayment();
                
                $transaction_token = '';
                $installment_number = '';
                
                $action = $model->getConfigValue('payment_action');
                
                if ($model->getConfigValue('checkout_mode') == 'paymentjs') {
                    $transaction_token = $payment->getAdditionalInformation('allsecurepay_transaction_token');
                    $transaction_token  = trim($transaction_token);
                    
                    if (empty($transaction_token)) {
                        throw new \Exception(__('Invalid transaction token.'));
                    }
                    
                    if ($model->getConfigValueByPath('payment/allsecureexchange_installments/active')) {
                        $installment_availed = $payment->getAdditionalInformation('allsecurepay_pay_installment');
                        if ($installment_availed) {
                            $installment_number = $payment->getAdditionalInformation('allsecurepay_installment_number');
                            if (!empty($installment_number)) {
                                $installment_number  = trim($installment_number);
                                $action = 'debit';
                            }
                        }
                    }
                }
                
                
                if ($action == 'debit') {
                    $result = $model->debitTransaction($order, $transaction_token, $installment_number);
                } else {
                    $result = $model->preauthorizeTransaction($order, $transaction_token);
                }

                // handle the result
                if ($result->isSuccess()) {
                    $gatewayReferenceId = $result->getUuid();
                    
                    $this->helper->updateTransaction($order_id, 'transaction_id', $gatewayReferenceId);
                    $this->helper->updateTransaction($order_id, 'transaction_mode', $model->getConfigValue('operation_mode'));
                    $this->helper->updateTransaction($order_id, 'checkout_type', $model->getConfigValue('checkout_mode'));
                    $this->helper->updateTransaction($order_id, 'transaction_type', $action);
                    $this->helper->updateTransaction($order_id, 'response', json_encode($result->toArray()));
                    
                    // handle result based on it's returnType
                    if ($result->getReturnType() == AllsecureResult::RETURN_TYPE_ERROR) {
                        //error handling
                        $this->helper->updateTransaction($order_id, 'status', 'error');
                        $error = $result->getFirstError();
                        $errorCode = $error->getCode();
                        if (empty($errorCode)) {
                            $errorCode = $error->getAdapterCode();
                        }
                        $errorMessage = \Allsecureexchange\Allsecureexchange\Model\Pay::getErrorMessageByCode($errorCode);
                        throw new \Exception($errorMessage);
                    } elseif ($result->getReturnType() == AllsecureResult::RETURN_TYPE_REDIRECT) {
                        //redirect the user
                        if (!empty($installment_number)) {
                            $this->helper->updateTransactionResponse($order_id, 'installment_number', $installment_number);
                        }
                        $this->helper->updateTransaction($order_id, 'status', 'redirected');
                        $resultRedirect = $this->resultRedirectFactory->create();
                        $redirectLink = $result->getRedirectUrl();
                        $resultRedirect->setUrl($redirectLink);
                        return $resultRedirect;
                    } elseif ($result->getReturnType() == AllsecureResult::RETURN_TYPE_PENDING) {
                        //payment is pending, wait for callback to complete
                        if (!empty($installment_number)) {
                            $this->helper->updateTransactionResponse($order_id, 'installment_number', $installment_number);
                        }
                        $this->helper->updateTransaction($order_id, 'status', 'pending');
                        if ($action == 'debit') {
                            $comment1 = __('Allsecureexchange payment request is created successfully and but payment debt status received as pending. ');
                        } else {
                            $comment1 = __('Allsecureexchange payment request is created successfully and but payment preauthorize status received as pending. ');
                        }

                        $comment2 = __('Transaction ID: '). $gatewayReferenceId;
                        $comment = $comment1.$comment2;
 
                        $state = \Magento\Sales\Model\Order::STATE_NEW;
                        $status = 'pending';
                        $order->setState($state);
                        $order->setStatus($status);
                        $order->setPaymentAuthorizationAmount($order->getGrandTotal());
                        $order->addStatusHistoryComment($comment, $status);
                        $order->save();
                        $this->orderSender->send($order, true);
                        return $this->getResponse()->setRedirect($model->getCheckoutSuccessUrl(['mage_order_incr_id' => $order->getIncrementId()]));
                    } elseif ($result->getReturnType() == AllsecureResult::RETURN_TYPE_FINISHED) {
                        //payment is finished, update your cart/payment transaction
                        if ($action == 'debit') {
                            if (!empty($installment_number)) {
                                $this->helper->updateTransactionResponse($order_id, 'installment_number', $installment_number);
                            }
                            $this->helper->updateTransaction($order_id, 'status', 'debited');
                            $comment1 = __('Allsecureexchange payment is successfully debited. ');
                            $state = \Magento\Sales\Model\Order::STATE_PROCESSING;
                            $status = \Magento\Sales\Model\Order::STATE_PROCESSING;
                            $order->setState($state);
                            $order->setStatus($status);
                            $order->setTotalPaid($order->getGrandTotal());
                            $comment2 = __('Transaction ID: '). $gatewayReferenceId;
                            $comment = $comment1.$comment2;
                            $order->addStatusHistoryComment($comment, $status);
                            $order->save();
                            $this->orderSender->send($order, true);
                            $model->createInvoice($order, $gatewayReferenceId);
                        } else {
                            $this->helper->updateTransaction($order_id, 'status', 'preauthorized');
                            $comment1 = __('Allsecureexchange payment is successfully reserved for manual capture. ');
                            $comment2 = __('Transaction ID: '). $gatewayReferenceId;
                            $comment = $comment1.$comment2;
                            $order->addStatusHistoryComment($comment);
                            $order->save();
                            $this->orderSender->send($order, true);
                            $model->createInvoice($order, $gatewayReferenceId, 'not_capture');
                        }
                        return $this->getResponse()->setRedirect($model->getCheckoutSuccessUrl(['mage_order_incr_id' => $order->getIncrementId()]));
                    }
                } else {
                    // handle error
                    $error = $result->getFirstError();
                    $errorCode = $error->getCode();
                    if (empty($errorCode)) {
                        $errorCode = $error->getAdapterCode();
                    }
                    $errorMessage = \Allsecureexchange\Allsecureexchange\Model\Pay::getErrorMessageByCode($errorCode);
                    throw new \Exception($errorMessage);
                }
            } else {
                $this->_redirect('checkout/cart');
            }
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            $model->log('Payment Gateway Request Catch');
            $model->log('Exception:'.$e->getMessage());
            $message = __('Payment is failed. '.$error_message);
            if (isset($order) && $order && $order->getId() > 0) {
                $order->cancel();
                $order->addStatusHistoryComment($message, \Magento\Sales\Model\Order::STATE_CANCELED);
                $order->save();
                $session->restoreQuote();
            }
            $this->messageManager->addError($message);
            $this->_redirect('checkout/cart');
        }
    }
}
