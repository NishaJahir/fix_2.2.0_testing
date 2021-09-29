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
use Novalnet\Helper\PaymentHelper;
use Novalnet\Services\PaymentService;
use Plenty\Plugin\ConfigRepository;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;


class NovalnetPaymentMethodReinitializePayment
{
  
  public function call(Twig $twig, $arg):string
  {
     $order = $arg[0];
    $paymentHelper = pluginApp(PaymentHelper::class);
    $paymentService = pluginApp(PaymentService::class);
    $config = pluginApp(ConfigRepository::class);
    $basketRepository = pluginApp(BasketRepositoryContract::class);
    $paymentRepository = pluginApp(PaymentRepositoryContract::class);
    $sessionStorage = pluginApp(FrontendSessionStorageFactoryContract::class);
    $payments = $paymentRepository->getPaymentsByOrderId($order['id']);
    
    
    $paymentHelper->logger('address details', $address);
    // Get payment method Id and status
    foreach($order['properties'] as $property) {
        if($property['typeId'] == 3)
        {
            $mopId = $property['value'];
        }
    }
    
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
    
    
      
      // Changed payment method key
       $paymentKey = $paymentHelper->getPaymentKeyByMop($mopId);
    
       
       // If the Novalnet payments are rejected, do the reinitialize payment
       if( !in_array($tid_status, [75, 85, 86, 90, 91, 98, 99, 100]) ) {
         $paymentHelper->logger('tid status if 123', $tid_status);
          return $twig->render('Novalnet::NovalnetPaymentMethodReinitializePayment', [
            'order' => $order, 
            'paymentMethodId' => $mopId,
            'paymentKey' => $paymentKey,
            
          ]);
       } else {
         $paymentHelper->logger('tid status else 123', $tid_status);
          return '';
      }
  }
}
