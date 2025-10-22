<?php
  
  use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;
  use Civi\Payment\PropertyBag;
  use Civi\Payment\Exception\PaymentProcessorException;
  
  class CRM_Core_Payment_HelloAssoCheckout extends CRM_Core_Payment {
    
    /**
     * @inheritdoc
     *
     * For Stripe Checkout this is very easy:
     *  - send the user to the Stripe Checkout url
     *  - tell Stripe where to send the user next if they succeed/cancel
     */
    public function startCheckout(array $paymentParams, string $successUrl, string $failUrl, string $cancelUrl): array {
      Civi::log()->debug('---- startCheckout CRM_Core_Payment_StripeCheckout ----');
      
      Civi::log()->debug('--- startCheckout $paymentParams : ' . print_r($paymentParams,1));
      Civi::log()->debug('--- startCheckout $successUrl : ' . print_r($successUrl,1));
      Civi::log()->debug('--- startCheckout $failUrl : ' . print_r($failUrl,1));
      Civi::log()->debug('--- startCheckout $cancelUrl : ' . print_r($cancelUrl,1));
      
      if (!$successUrl || !$cancelUrl) {
        throw new \CRM_Core_Exception('HelloAsso requires successUrl and failUrl');
      }
      $url = $this->getCheckoutUrl(PropertyBag::cast($paymentParams), $successUrl, $cancelUrl);
      return [
        'redirect' => $url,
      ];
    }
    
    /**
     * @param \Civi\Payment\PropertyBag $paymentParams
     *   Assoc array of input parameters for this transaction.
     * @param string $successUrl where to send the user on success
     * @param string $failUrl where to send the user on fail
     *
     * @return string url to redirect the user to for checkout
     * @throws \CRM_Core_Exception
     * @throws \Civi\Payment\Exception\PaymentProcessorException
     */
    public function getCheckoutUrl(PropertyBag &$paymentParams, string $successUrl, string $cancelUrl): string {
      $paymentParams = $this->beginDoPayment($paymentParams);
      
      $lineItems = $this->calculateLineItems($paymentParams);
      
      Civi::log()->debug('---- getCheckoutUrl : $lineItems ' . print_r($lineItems,1));
      Civi::log()->debug('---- getCheckoutUrl : $paymentParams ' . print_r($paymentParams,1));
      
      $checkoutSession = $this->createCheckoutSession($successUrl, $cancelUrl, $paymentParams, $lineItems);
      
      Civi::log()->debug('---- getCheckoutUrl $checkoutSession : ' . print_r($checkoutSession,1));
      
      return $checkoutSession->url;
    }
    
    /**
     * Process payment
     * Submit a payment
     * Payment processors should set payment_status_id/payment_status.
     *
     * @param array|PropertyBag $paymentParams
     *   Assoc array of input parameters for this transaction.
     * @param string $component
     *
     * @throws \CRM_Core_Exception
     * @throws \Civi\Payment\Exception\PaymentProcessorException
     */
    public function doPayment(&$paymentParams, $component = 'contribute') {
      $propertyBag = \Civi\Payment\PropertyBag::cast($paymentParams);
      
      $zeroAmountPayment = $this->processZeroAmountPayment($propertyBag);
      if ($zeroAmountPayment) {
        return $zeroAmountPayment;
      }
      
      // This is used to generate the return/cancel urls
      $this->_component = $component;
      $successUrl = $this->getReturnSuccessUrl($paymentParams['qfKey']);
      $cancelUrl = $this->getCancelUrl($paymentParams['qfKey'], NULL);
      
      $checkoutUrl = $this->getCheckoutUrl($paymentParams, $successUrl, $cancelUrl);
      // Allow each CMS to do a pre-flight check before redirecting to Stripe.
      CRM_Core_Config::singleton()->userSystem->prePostRedirect();
      
      if ((\CRM_Core_Config::singleton()->userFramework === 'Drupal8') && CRM_Utils_Request::retrieve('_drupal_ajax', 'Boolean', FALSE)) {
        $webformRedirect = new \Drupal\webform\Ajax\WebformRefreshCommand($checkoutUrl);
        CRM_Core_Page_AJAX::returnJsonResponse([$webformRedirect->render()]);
        exit();
      }
      
      CRM_Utils_System::setHttpHeader("HTTP/1.1 303 See Other", '');
      CRM_Utils_System::redirect($checkoutUrl);
    }
    
    protected function buildCheckoutLineItems(array $civicrmLineItems, PropertyBag $propertyBag) {
      foreach ($civicrmLineItems as $priceSetLines) {
        foreach ($priceSetLines as $lineItem) {
          $amount = $lineItem['unit_price'] + ($lineItem['tax_amount'] ?? 0);
          $checkoutLineItem = [
            'price_data' => [
              'currency' => $propertyBag->getCurrency(),
              'unit_amount' => $this->getAmountFormattedForStripeAPI(PropertyBag::cast(['amount' => $amount, 'currency' => $propertyBag->getCurrency()])),
              'product_data' => [
                'name' => $lineItem['field_title'],
                // An empty label on a contribution page amounts configuration gives an empty $lineItem['label']. StripeCheckout needs it set.
                'description' => $lineItem['label'] ?: $lineItem['field_title'],
                //'images' => ['https://example.com/t-shirt.png'],
              ],
            ],
            'quantity' => $lineItem['qty'],
          ];
          if ($propertyBag->getIsRecur()) {
            $checkoutLineItem['price_data']['recurring'] = [
              'interval' => $propertyBag->getRecurFrequencyUnit(),
              'interval_count' => $propertyBag->getRecurFrequencyInterval(),
            ];
          }
          $checkoutLineItems[] = $checkoutLineItem;
        }
      }
      return $checkoutLineItems ?? [];
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