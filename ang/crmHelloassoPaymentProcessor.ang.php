<?php

// Angular module crmHelloassoPaymentProcessor.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
return [
  'js' => [
    'ang/crmHelloassoPaymentProcessor.js',
    'ang/crmHelloassoPaymentProcessor/*.js',
    'ang/crmHelloassoPaymentProcessor/*/*.js',
  ],
  'css' => [
    'ang/crmHelloassoPaymentProcessor.css',
  ],
  'partials' => [
    'ang/crmHelloassoPaymentProcessor',
  ],
  'requires' => ['crmUi', 'crmUtil', 'ngRoute'],
  'settings' => [],
];
