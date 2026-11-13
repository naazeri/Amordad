<?php
 return [
  'name' => 'categories',
  'label' => 'Categories',
  'info' => 'Project categories',
  'type' => 'collection',
  'fields' => [
    0 => [
      'name' => 'name_fa',
      'label' => 'Name (Persian)',
      'type' => 'text',
      'required' => true,
    ],
    1 => [
      'name' => 'name_en',
      'label' => 'Name (English)',
      'type' => 'text',
    ],
    2 => [
      'name' => 'name_tr',
      'label' => 'Name (Turkish)',
      'type' => 'text',
    ],
    3 => [
      'name' => 'slug',
      'label' => 'Slug',
      'type' => 'text',
      'required' => true,
    ],
    4 => [
      'name' => 'showInNavigation',
      'label' => 'Show in Navigation',
      'type' => 'boolean',
      'opts' => [
        'default' => true,
      ],
    ],
    5 => [
      'name' => 'navigationOrder',
      'label' => 'Navigation Order',
      'type' => 'number',
      'opts' => [
        'default' => 0,
      ],
    ],
  ],
  'preview' => [
  ],
  'group' => NULL,
  'meta' => NULL,
  '_created' => 1789826508,
  '_modified' => 1789826508,
];