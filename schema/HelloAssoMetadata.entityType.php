<?php
use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

return [
  'name' => 'HelloAssoMetadata',
  'table' => 'civicrm_hello_asso_metadata',
  'class' => 'CRM_HelloassoPaymentProcessor_DAO_HelloAssoMetadata',
  'getInfo' => fn() => [
    'title' => E::ts('Hello Asso Metadata'),
    'title_plural' => E::ts('Hello Asso Metadatas'),
    'description' => E::ts('FIXME'),
    'log' => TRUE,
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique HelloassoMetaData ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'contribution_id' => [
      'title' => E::ts('Contribution ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to Contribution'),
      'input_attrs' => [
        'label' => E::ts('Contribution'),
      ],
      'entity_reference' => [
        'entity' => 'Contribution',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'signing_key' => [
      'title' => E::ts('Signing Key'),
      'sql_type' => 'text',
      'input_type' => 'TextArea',
      'required' => TRUE,
      'description' => E::ts('Key used to sign contribution'),
    ],
    'helloasso_ref_cmd_id' => [
      'title' => E::ts('HelloAsso Reference command ID'),
      'sql_type' => 'int',
      'input_type' => 'Number',
      'default' => NULL,
    ],
  ],
];
