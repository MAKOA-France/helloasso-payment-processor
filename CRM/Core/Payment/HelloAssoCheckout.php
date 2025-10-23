<?php
  
  use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;
  use Civi\AfformPayment\CheckoutSession;
  use Civi\Payment\Exception\PaymentProcessorException;
  
  class CRM_Core_Payment_HelloAssoCheckout extends CRM_Core_Payment {
    
    public function startCheckout(CheckoutSession $session): void {
      $contributionId = $session->getContributionId();
      
    }
    
    public function continueCheckout(CheckoutSession $session): void {
      
    }
    
    
    /**
     * The template just provides some copy.
     */
    public function getAfformConfig(array $processor): array {
      return [
        'template' => '~/crmHelloassoPaymentProcessor/crmHelloassoPaymentProcessor.html'
      ];
    }
  }