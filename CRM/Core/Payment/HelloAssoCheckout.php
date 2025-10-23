<?php
  
  use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;
  use Civi\AfformPayment\CheckoutSession;
  use Civi\Payment\Exception\PaymentProcessorException;
  
  class CRM_Core_Payment_HelloAssoCheckout extends CRM_Core_Payment {
    
    public function startCheckout(CheckoutSession $session): void {
      $contributionId = $session->getContributionId();
      
      $currency = \Civi\Api4\Contribution::get(FALSE)
        ->addWhere('id', '=', $contributionId)
        ->addSelect('currency')
        ->execute()
        ->first()['currency'];
      
      $lineItems = (array) \Civi\Api4\LineItem::get(FALSE)
        ->addWhere('contribution_id', '=', $contributionId)
        ->execute();
      
      $orderId = $this->createOrder($currency, $lineItems);
      
      $session->setPaymentParam('order_id', $orderId);
      $session->setResponseItem('paypal', [
        'order_id' => $orderId,
        'continue_url' => $session->getLandingUrl(),
      ]);
    }
    
    public function continueCheckout(CheckoutSession $session): void {
      
    }
    
    
    /**
     * The template just provides some copy.
     */
    public function getAfformConfig(array $processor): array {
      /*/return [
        'template' => '~/afStripe/stripe_checkout.html'
      ];*/
    }
  }