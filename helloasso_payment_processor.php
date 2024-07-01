<?php

require_once 'helloasso_payment_processor.civix.php';

use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function helloasso_payment_processor_civicrm_config(&$config): void {
  _helloasso_payment_processor_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function helloasso_payment_processor_civicrm_install(): void {
  _helloasso_payment_processor_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function helloasso_payment_processor_civicrm_enable(): void {
  _helloasso_payment_processor_civix_civicrm_enable();
}
