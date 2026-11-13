<?php
 return [
  'name' => 'projects',
  'label' => 'Projects',
  'info' => 'Architecture projects',
  'type' => 'collection',
  'fields' => [
    0 => [
      'name' => 'title_fa',
      'label' => 'Title (Persian)',
      'type' => 'text',
      'required' => true,
    ],
    1 => [
      'name' => 'title_en',
      'label' => 'Title (English)',
      'type' => 'text',
    ],
    2 => [
      'name' => 'title_tr',
      'label' => 'Title (Turkish)',
      'type' => 'text',
    ],
    3 => [
      'name' => 'slug',
      'label' => 'Slug',
      'type' => 'text',
    ],
    4 => [
      'name' => 'featuredImage',
      'label' => 'Featured Image',
      'type' => 'asset',
    ],
    5 => [
      'name' => 'categories',
      'label' => 'Categories',
      'type' => 'categories',
      'options' => [
        'model' => 'categories',
        'multiple' => true,
      ],
    ],
    6 => [
      'name' => 'projectInformation_fa',
      'label' => 'Project Information (Persian)',
      'type' => 'wysiwyg',
    ],
    7 => [
      'name' => 'projectInformation_en',
      'label' => 'Project Information (English)',
      'type' => 'wysiwyg',
    ],
    8 => [
      'name' => 'projectInformation_tr',
      'label' => 'Project Information (Turkish)',
      'type' => 'wysiwyg',
    ],
    9 => [
      'name' => 'description_fa',
      'label' => 'Description (Persian)',
      'type' => 'wysiwyg',
    ],
    10 => [
      'name' => 'description_en',
      'label' => 'Description (English)',
      'type' => 'wysiwyg',
    ],
    11 => [
      'name' => 'description_tr',
      'label' => 'Description (Turkish)',
      'type' => 'wysiwyg',
    ],
    12 => [
      'name' => 'content_fa',
      'label' => 'Content (Persian)',
      'type' => 'wysiwyg',
    ],
    13 => [
      'name' => 'content_en',
      'label' => 'Content (English)',
      'type' => 'wysiwyg',
    ],
    14 => [
      'name' => 'content_tr',
      'label' => 'Content (Turkish)',
      'type' => 'wysiwyg',
    ],
    15 => [
      'name' => 'projectGallery',
      'label' => 'Gallery Images',
      'type' => 'asset',
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