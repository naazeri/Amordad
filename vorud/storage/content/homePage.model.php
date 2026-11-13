<?php
 return [
  'name' => 'homePage',
  'label' => 'Home Page',
  'info' => 'Homepage tiles configuration',
  'type' => 'singleton',
  'fields' => [
    0 => [
      'name' => 'tiles',
      'label' => 'Tiles',
      'type' => 'set',
      'opts' => [
        'fields' => [
          0 => [
            'name' => 'label_fa',
            'type' => 'text',
            'label' => 'Label (Persian)',
          ],
          1 => [
            'name' => 'label_en',
            'type' => 'text',
            'label' => 'Label (English)',
          ],
          2 => [
            'name' => 'label_tr',
            'type' => 'text',
            'label' => 'Label (Turkish)',
          ],
          3 => [
            'name' => 'category',
            'type' => 'content-item-link',
            'opts' => [
              'link' => 'categories',
            ],
            'label' => 'Category',
          ],
          4 => [
            'name' => 'images',
            'type' => 'asset',
            'multiple' => true,
            'label' => 'Images',
          ],
        ],
      ],
      'multiple' => true,
    ],
  ],
  'preview' => [
  ],
  'group' => NULL,
  'meta' => NULL,
  '_created' => 1789826508,
  '_modified' => 1789826508,
];