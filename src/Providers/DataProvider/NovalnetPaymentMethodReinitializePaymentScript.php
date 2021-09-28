<?php
/**
 * This module is used for real time processing of
 * Novalnet payment module of customers.
 * This free contribution made by request.
 * 
 * If you have found this script useful a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @author       Novalnet AG
 * @copyright(C) Novalnet 
 * All rights reserved. https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */
 
namespace Novalnet\Providers\DataProvider;

use Plenty\Plugin\Templates\Twig;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Method\Models\PaymentMethod;
use Novalnet\Helper\PaymentHelper;
use Novalnet\Services\PaymentService;
use Plenty\Plugin\ConfigRepository;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Account\Address\Models\Address;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;

class NovalnetPaymentMethodReinitializePaymentScript
{
  public function call(Twig $twig, $arg):string
  {
    $order = $arg[0];
    // Load the all Novalnet payment methods
    $paymentMethodRepository = pluginApp(PaymentMethodRepositoryContract::class);
    $paymentHelper = pluginApp(PaymentHelper::class);
    $paymentService = pluginApp(PaymentService::class);
    $config = pluginApp(ConfigRepository::class);
    $basketRepository = pluginApp(BasketRepositoryContract::class);
    $addressRepository = pluginApp(AddressRepositoryContract::class);
    $address = pluginApp(Address::class);
    $paymentRepository = pluginApp(PaymentRepositoryContract::class);
    $sessionStorage = pluginApp(FrontendSessionStorageFactoryContract::class);
    $payments = $paymentRepository->getPaymentsByOrderId($order['id']);
   
    
   foreach($order['properties'] as $property) {
        if($property['typeId'] == 3)
        {
            $mopId = $property['value'];
        }
    }
    $paymentMethods          = $paymentMethodRepository->allForPlugin('plenty_novalnet');
    if(!is_null($paymentMethods))
    {
       $paymentMethodIds              = [];
        foreach ($paymentMethods as $paymentMethod) {
          if ($paymentMethod instanceof PaymentMethod) {
              $paymentMethodIds[] = $paymentMethod->id;
          }
        }
    }
    $paymentHelper->logger('mop', $mopId);
     // Get transaction status
    foreach($payments as $payment)
    {
        $properties = $payment->properties;
        foreach($properties as $property)
        {
          if ($property->typeId == 30)
          {
          $tid_status = $property->value;
          }
        }
    }
    
    
      $paymentHelper->logger('tid status provider', $tid_status);
      // Changed payment method key
       $paymentKey = $paymentHelper->getPaymentKeyByMop($mopId);
   $paymentHelper->logger('tid status mopid', $mopId);
     $paymentHelper->logger('tid status mop', $paymentKey);
       $name = trim($config->get('Novalnet.' . strtolower($paymentKey) . '_payment_name'));
       $paymentName = ($name ? $name : $paymentHelper->getTranslatedText(strtolower($paymentKey)));
      // Get the orderamount from order object if the basket amount is empty
       $orderAmount = $paymentHelper->ConvertAmountToSmallerUnit($order['amounts'][0]['invoiceTotal']);
      // Form the payment request data 
       $serverRequestData = $paymentService->getRequestParameters($basketRepository->load(), $paymentKey, false, $orderAmount);
       $sessionStorage->getPlugin()->setValue('nnOrderNo', $order['id']);
       $sessionStorage->getPlugin()->setValue('mop', $mopId);
       $sessionStorage->getPlugin()->setValue('paymentKey', $paymentKey);
       
       // Set the request param for redirection payments
      if ($paymentService->isRedirectPayment($paymentKey, false)) {
         $sessionStorage->getPlugin()->setValue('nnPaymentData', $serverRequestData['data']);
         $sessionStorage->getPlugin()->setValue('nnPaymentUrl', $serverRequestData['url']);
      } else { // Set the request param for direct payments
          $sessionStorage->getPlugin()->setValue('nnPaymentData', $serverRequestData);
      }
    $paymentHelper->logger('b load', $basketRepository->load());
    // Get company and birthday values
    $basket = $basketRepository->load();            
    $billingAddressId = $basket->customerInvoiceAddressId;
    $address = $addressRepository->findAddressById($billingAddressId);
    foreach ($address->options as $option) {
      if ($option->typeId == 9) {
          $birthday = $option->value;
      }
    }  
    $paymentHelper->logger('b load 123', $address);
    // Set guarantee status
    $guarantee_status = $paymentService->getGuaranteeStatus($basketRepository->load(), $paymentKey, $orderAmount);
    $show_birthday = (empty($address->companyName) && empty($birthday)) ? $guarantee_status : '';
   
    if ($guarantee_status == 'guarantee' && $show_birthday == '') {
      $sessionStorage->getPlugin()->setValue('nnProcessb2bGuarantee', $guarantee_status);
    }
    
    
      if ($paymentKey == 'NOVALNET_CC') {
         $ccFormDetails = $paymentService->getCreditCardAuthenticationCallData($basketRepository->load(), $paymentKey, $orderAmount);
         $ccCustomFields = $paymentService->getCcFormFields();
      }
       $paymentHelper->logger('b load key', $paymentKey);
       // If the Novalnet payments are rejected, do the reinitialize payment
       if( !in_array($tid_status, [75, 85, 86, 90, 91, 98, 99, 100]) ) {
        $paymentHelper->logger('tid status if', $tid_status);
          return $twig->render('Novalnet::NovalnetPaymentMethodReinitializePaymentScript', [
            'order' => $order, 
            'paymentMethodId' => $mopId,
            'paymentKey' => $paymentKey,
            'isRedirectPayment' => $paymentService->isRedirectPayment($paymentKey, false),
            'redirectUrl' => $paymentService->getRedirectPaymentUrl(),
            'reinit' => 1,
            'nnPaymentProcessUrl' => $paymentService->getProcessPaymentUrl(),
            'paymentMopKey'     =>  $paymentKey,
            'paymentName' => $paymentName,
            'ccFormDetails'  => !empty($ccFormDetails) ? $ccFormDetails : '',
            'ccCustomFields' => !empty($ccCustomFields) ? $ccCustomFields : '',
            'endcustomername'=> $serverRequestData['data']['first_name'] . ' ' . $serverRequestData['data']['last_name'],
            'nnGuaranteeStatus' => $show_birthday,
            'isGuarantee' => $guarantee_status,
            'orderAmount' => $orderAmount,
            'paymentMethodIds' => $paymentMethodIds
          ]);
       } else {
          $paymentHelper->logger('tid status else', $tid_status);
          return '';
      }
  }
}
