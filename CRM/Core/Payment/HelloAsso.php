<?php

use Civi\Payment\Exception\PaymentProcessorException;

class CRM_Core_Payment_HelloAsso extends CRM_Core_Payment
{
    /**
     * @var GuzzleHttp\Client
     */
    protected $guzzleClient;

    /**
     * mode of operation: live or test
     *
     * @var object
     * @static
     */
    protected $_mode = null;

    /**
     * is this the testing processor?
     */
    protected $_is_test = false;

    /**
     * Constructor
     *
     * @param string $mode the mode of operation: live or test
     *
     * @return void
     */
    function __construct($mode, &$paymentProcessor)
    {
        $this->_mode = $mode;
        $this->_paymentProcessor = $paymentProcessor;
        $this->_is_test = ($this->_mode == 'test' ? 1 : 0);
    }

    /**
     * @return \GuzzleHttp\Client
     */
    public function getGuzzleClient(): \GuzzleHttp\Client
    {
        return $this->guzzleClient ?? new \GuzzleHttp\Client();
    }

    /**
     * @param \GuzzleHttp\Client $guzzleClient
     */
    public function setGuzzleClient(\GuzzleHttp\Client $guzzleClient)
    {
        $this->guzzleClient = $guzzleClient;
    }

    /**
     * This function checks to see if we have the right config values
     *
     * @return string the error message if any
     * @public
     */
    function checkConfig()
    {
        $error = array();

        if (empty($this->_paymentProcessor['user_name'])) {
            $error[] = ts('Client Id is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.');
        }
        if (empty($this->_paymentProcessor['password'])) {
            $error[] = ts('Client Secret Id is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.');
        }
        if (empty($this->_paymentProcessor['subject'])) {
            $error[] = ts('HelloAsso Organization Name is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.');
        }

        if (!empty($error)) {
            return implode('<p>', $error);
        } else {
            return NULL;
        }
    }

    /**
     * Process payment - this function wraps around both doTransferCheckout and doDirectPayment.
     * Any processor that still implements the deprecated doTransferCheckout() or doDirectPayment() should be updated to use doPayment().
     *
     * This function adds some historical defaults ie. the assumption that if a 'doDirectPayment' processors comes back it completed
     *   the transaction & in fact doTransferCheckout would not traditionally come back.
     * Payment processors should throw exceptions and not return Error objects as they may have done with the old functions.
     *
     * Payment processors should set payment_status_id (which is really contribution_status_id) in the returned array. The default is assumed to be Pending.
     *   In some cases the IPN will set the payment to "Completed" some time later.
     *
     * @fixme Creating a contribution record is inconsistent! We should always create a contribution BEFORE calling doPayment...
     *  For the current status see: https://lab.civicrm.org/dev/financial/issues/53
     * If we DO have a contribution ID, then the payment processor can (and should) update parameters on the contribution record as necessary.
     *
     * @param array|\Civi\Payment\PropertyBag $params
     *
     * @param string $component
     *
     * @return array
     *   Result array (containing at least the key payment_status_id)
     *
     * @throws \Civi\Payment\Exception\PaymentProcessorException
     */
    public function doPayment(&$params, $component = 'contribute')
    {
        if (is_array($params)) {
            $params = $this->chooseBestContactDetails($params);
        }

        $propertyBag = \Civi\Payment\PropertyBag::cast($params);
        $this->_component = $component;

        $result = $this->setStatusPaymentPending([]);

        // If we have a $0 amount, skip call to processor and set payment_status to Completed.
        // Conceivably a processor might override this - perhaps for setting up a token - but we don't
        // have an example of that at the moment.
        if ($propertyBag->getAmount() == 0) {
            return $this->setStatusPaymentCompleted($result);
        }

        // Make Oauth2 login
        $oauth_uri = $this->_paymentProcessor['url_site'] . '/oauth2/token';
        $client_id = $this->_paymentProcessor['user_name'];
        $client_secret = $this->_paymentProcessor['password'];

        $token = CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance()->getToken($this->_is_test, $oauth_uri, $client_id, $client_secret);

        // Init Cart
        $api_uri = $this->_paymentProcessor['url_site'] . '/v5/organizations/' . $this->_paymentProcessor['subject'] . '/checkout-intents';

        $payer = array(
            'email' => $propertyBag->getEmail(),
        );
        if ($propertyBag->has('firstName')) {
            $payer['firstName'] = $propertyBag->getFirstName();
        }
        if ($propertyBag->has('lastName')) {
            $payer['lastName'] = $propertyBag->getLastName();
        }
        if ($propertyBag->has('billingStreetAddress')) {
            $payer['address'] = $propertyBag->getBillingStreetAddress();
        }
        if ($propertyBag->has('billingCity')) {
            $payer['city'] = $propertyBag->getBillingCity();
        }
        if ($propertyBag->has('billingPostalCode')) {
            $payer['zipCode'] = $propertyBag->getBillingPostalCode();
        }
        if (
            $propertyBag->has('billingCountry') &&
            CRM_HelloassoPaymentProcessor_IsoCountryAlpha3::getInstance()->support($propertyBag->getBillingCountry())
        ) {
            $payer['country'] = CRM_HelloassoPaymentProcessor_IsoCountryAlpha3::getInstance()->get($propertyBag->getBillingCountry());
        }
        $key = random_bytes(64);
        $key = hash('sha256', $key);
        $sig = hash_hmac('sha256', $propertyBag->getInvoiceID(), $key);
        $metadata = array(
            'invoiceID' => $propertyBag->getInvoiceID(),
            'sig' => hash_hmac('sha256', $propertyBag->getInvoiceID(), $key) //N'est plus utilisé mais garder pour ceux qui ont lancienne version
        );
        $request = [
            'totalAmount' => round(intval($this->getAmount($params)) * 100),
            'initialAmount' => round(intval($this->getAmount($params)) * 100),
            'itemName' => $this->getPaymentDescription($params, 250),
            'backUrl' => $this->getGoBackUrl($params['qfKey']),
            'errorUrl' => $this->getCancelUrl($params['qfKey']),
            'returnUrl' => $this->getReturnSuccessUrl($params['qfKey']),
            'containsDonation' => FALSE,
            'payer' => $payer,
            'metadata' => $metadata
        ];
        $response = $this->getGuzzleClient()->request('POST', $api_uri, [
            'json' => $request,
            'curl' => [
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_SSL_VERIFYPEER => Civi::settings()->get('verifySSL'),
            ],
            'headers' => [
                'Authorization' => 'Bearer ' . $token->access_token
            ],
            'http_errors' => FALSE,
        ]);
        $status_code = $response->getStatusCode();
        $response = json_decode($response->getBody());
        if ($status_code != 200) {
            if ($status_code == 401) {
                CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance()->invalidateToken($this->_is_test);
            }
            if (isset($response->errors)) {
                throw new PaymentProcessorException(implode(", ", array_map(function ($entry) {
                    return $entry->message;
                }, $response->errors)));
            } else if (isset($response->message)) {
                throw new PaymentProcessorException($response->message);
            } else {
                throw new PaymentProcessorException('Unknown error append');
            }
        }
        if (isset($response->redirectUrl)) {
            // We can store checkout id somewhere
            $contribution = new CRM_Contribute_BAO_Contribution();
            $contribution->invoice_id = $propertyBag->getInvoiceID();
            if ($contribution->find(TRUE)) {
                $contribution->trxn_id = $response->id;
                $contribution->save();

                /** Inserer une ligne dans les metada entity */
                $metadata = new CRM_HelloassoPaymentProcessor_BAO_HelloAssoMetadata();
                $metadata->contribution_id = $contribution->id;
                $metadata->signing_key = $key;
                $metadata->insert();
                // // // [SV] ça semble crasher parfois - est-ce que c'est le fait qu'il n'y a pas d'id auto increment ?
                // // $contributionKey = new CRM_HelloassoPaymentProcessor_BAO_HelloAssoContributionKey();
                // // $contributionKey->contribution_id = $contribution->id;
                // // $contributionKey->signing_key = $key;
                // // $contributionKey->insert();
                // // // Error -> DB Error: constraint violation
                
            } else {
                throw new PaymentProcessorException('Unable to update invoice.');
            }

            // then redirect to HelloAsso
            CRM_Core_Config::singleton()->userSystem->prePostRedirect();
            CRM_Utils_System::redirect($response->redirectUrl);

            // exit called before

            return $result;
        } else {
            throw new PaymentProcessorException('Unknown error append');
        }
    }


    /*
     * Address, phone, email parameters provided by profiles have names like:
     *
     * - email-5 (e.g. 5 is the LocationType ID)
     * - email-Primary (Primary email was selected)
     *
     * We try to pick the billing location types if possible, after that we look
     * for Primary, after that we go with any given.
     */
    public function chooseBestContactDetails(array $params): array
    {

        // We remove all addresses, emails from the $params array, and then re-insert the one we want to use.
        $addresses = [];
        $emails = [];
        $phones = [];
        foreach ($params as $inputKey => $value) {
            if (preg_match('/^(phone|email|street_address|supplemental_address_1|supplemental_address_2|city|postal_code|country|state_province)-(\d+|\w+)$/', $inputKey, $matches)) {

                [, $fieldname, $locType] = $matches;
                if ($fieldname === 'email') {
                    $emails[$locType] = $value;
                } elseif ($fieldname === 'phone') {
                    $phones[$locType] = $value;
                } else {
                    // Index addresses by location type, field.
                    $addresses[$locType][$fieldname] = $value;
                }
                unset($params[$inputKey]);
            }
        }

        $selectedAddress = [];
        // First preference is dedicated billing location types
        $billingLocTypeID = CRM_Core_BAO_LocationType::getBilling();
        if ($billingLocTypeID && isset($addresses[$billingLocTypeID])) {
            // Use this one.
            $selectedAddress = $addresses[$billingLocTypeID];
        } elseif (isset($addresses['Primary'])) {
            $selectedAddress = $addresses['Primary'];
        } elseif ($addresses) {
            $selectedAddress = reset($addresses);
        }

        foreach (["street_address" => 'billingStreetAddress', "supplemental_address_1" => 'billingSupplementalAddress1', "supplemental_address_2" => 'billingSupplementalAddress2', "supplemental_address_3" => 'billingSupplementalAddress3', "city" => 'billingCity', "postal_code" => 'billingPostalCode', "country" => 'billingCountry', "state_province" => 'billingStateProvince',] as $arrayKey => $propertyBagProp) {
            if (!empty($selectedAddress[$arrayKey])) {
                $params[$propertyBagProp] = $selectedAddress[$arrayKey];
            }
        }

        if ($billingLocTypeID && !empty($emails[$billingLocTypeID])) {
            $params['email'] = $emails[$billingLocTypeID];
        } elseif (!empty($emails['Primary'])) {
            $params['email'] = $emails['Primary'];
        } elseif ($emails) {
            $params['email'] = reset($emails);
        }

        if ($billingLocTypeID && !empty($phones[$billingLocTypeID])) {
            $params['phone'] = $phones[$billingLocTypeID];
        } elseif (!empty($phones['Primary'])) {
            $params['phone'] = $phones['Primary'];
        } elseif ($phones) {
            $params['phone'] = reset($phones);
        }

        return $params;
    }


    public function handlePaymentNotification()
    {
        $params = json_decode(file_get_contents('php://input'), true);
        if ($params) {
            $event_type = $params['eventType'] ?? NULL;
            // https://dev.helloasso.com/docs/les-notifications#type-de-notification
            if ($event_type === 'Payment' && $params['metadata']) {
                $invoice_id = $params['metadata']['invoiceID'] ?? NULL;
                $sig = $params['metadata']['sig'] ?? NULL;
                if ($invoice_id && $params['data']) {
                    // TODO : si un payment HelloAsso traite plus d'une contribution

                    $contribution = new CRM_Contribute_BAO_Contribution();
                    $contribution->invoice_id = $invoice_id;
                    if ($contribution->find(TRUE)) {
                        $state = $params['data']['state']; 
                        $helloasso_command_id = $params['data']['order']['id']; 
                        if($helloasso_command_id){
                            $metadata = new CRM_HelloassoPaymentProcessor_BAO_HelloAssoMetadata();
                            $metadata->contribution_id = $contribution->id;
                            if($metadata->find(TRUE)){
                                // AJouter l'identifiant de reference HElloAsso
                                $metadata->helloasso_ref_cmd_id = $helloasso_command_id;
                                $metadata->event_type = $event_type;
                                $metadata->state = $state;
                                $metadata->update();
                            }
                        }

                        switch ( $state) {
                            case 'Authorized':
                            case 'Registered':
                                $completedStatusId = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
                                if (
                                    // $contribution['contribution_status_id'] == $completedStatusId
                                    $contribution->contribution_status_id == $completedStatusId
                                ) {
                                    Civi::log()->debug('HelloAsso: Returning since contribution has already been handled. (ID: ' . $contribution->id . ').');
                                    echo 'Success: Contribution has already been handled<p>';
                                    return;
                                }
                                civicrm_api3('Payment', 'create', [
                                    'trxn_id' =>  $params['data']['id'], // $contribution->trxn_id,
                                    'payment_processor_id' => $this->_paymentProcessor->id,
                                    'contribution_id' => $contribution->id,
                                    'total_amount' => $contribution->total_amount,
                                ]);

                                break;
                            case 'Refused':
                                $completedStatusId = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Failed');
                                if (
                                    $contribution->contribution_status_id == $completedStatusId
                                ) {
                                    Civi::log()->debug('HelloAsso: Returning since contribution has already been handled. (ID: ' . $contribution->id . ').');
                                    echo 'Success: Contribution has already been handled<p>';
                                    return;
                                }
                                \Civi\Api4\Contribution::update(FALSE)
                                    ->addValue('contribution_status_id:name', 'Failed')
                                    ->addValue('cancel_date', 'now')
                                    ->addWhere('id', '=', $contribution->id)
                                    ->execute();
                                break;
                            case 'Refunding':
                                $completedStatusId = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending refund');
                                if (
                                    $contribution->contribution_status_id == $completedStatusId
                                ) {
                                    Civi::log()->debug('HelloAsso: Returning since contribution has already been handled. (ID: ' . $contribution->id . ').');
                                    echo 'Success: Contribution has already been handled<p>';
                                    return;
                                }
                                \Civi\Api4\Contribution::update(FALSE)
                                    ->addValue('contribution_status_id:name', 'Pending refund')
                                    ->addValue('cancel_date', 'now')
                                    ->addWhere('id', '=', $contribution->id)
                                    ->execute();
                                break;
                            case 'Refunded':
                                $completedStatusId = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Refunded');
                                if (
                                    $contribution->contribution_status_id == $completedStatusId
                                ) {
                                    Civi::log()->debug('HelloAsso: Returning since contribution has already been handled. (ID: ' . $contribution->id . ').');
                                    echo 'Success: Contribution has already been handled<p>';
                                    return;
                                }
                                \Civi\Api4\Contribution::update(FALSE)
                                    ->addValue('contribution_status_id:name', 'Refunded')
                                    ->addValue('cancel_date', 'now')
                                    ->addWhere('id', '=', $contribution->id)
                                    ->execute();
                                break;
                            // Do nothing on pending approvals
                            default:
                                // checked dans 5 jours les contribution non traité ?
                                break;
                        }
            
                    }
                }
            }
        }
    }
}