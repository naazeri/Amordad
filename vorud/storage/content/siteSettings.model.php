<?php
 return [
  'name' => 'siteSettings',
  'label' => 'Site Settings',
  'info' => 'Global website settings',
  'type' => 'singleton',
  'fields' => [
    0 => [
      'name' => 'siteName_fa',
      'label' => 'Site Name (Persian)',
      'type' => 'text',
    ],
    1 => [
      'name' => 'siteName_en',
      'label' => 'Site Name (English)',
      'type' => 'text',
    ],
    2 => [
      'name' => 'siteName_tr',
      'label' => 'Site Name (Turkish)',
      'type' => 'text',
    ],
    3 => [
      'name' => 'headerLogo',
      'label' => 'Header Logo',
      'type' => 'asset',
    ],
    4 => [
      'name' => 'footerLogo',
      'label' => 'Footer Logo',
      'type' => 'asset',
    ],
    5 => [
      'name' => 'footerLogoLabel_fa',
      'label' => 'Footer Logo Label (Persian)',
      'type' => 'text',
    ],
    6 => [
      'name' => 'footerLogoLabel_en',
      'label' => 'Footer Logo Label (English)',
      'type' => 'text',
    ],
    7 => [
      'name' => 'footerLogoLabel_tr',
      'label' => 'Footer Logo Label (Turkish)',
      'type' => 'text',
    ],
    8 => [
      'name' => 'copyright_fa',
      'label' => 'Copyright (Persian)',
      'type' => 'text',
    ],
    9 => [
      'name' => 'copyright_en',
      'label' => 'Copyright (English)',
      'type' => 'text',
    ],
    10 => [
      'name' => 'copyright_tr',
      'label' => 'Copyright (Turkish)',
      'type' => 'text',
    ],
    11 => [
      'name' => 'defaultMetaTitle_fa',
      'label' => 'Default Meta Title (Persian)',
      'type' => 'text',
    ],
    12 => [
      'name' => 'defaultMetaTitle_en',
      'label' => 'Default Meta Title (English)',
      'type' => 'text',
    ],
    13 => [
      'name' => 'defaultMetaTitle_tr',
      'label' => 'Default Meta Title (Turkish)',
      'type' => 'text',
    ],
    14 => [
      'name' => 'defaultMetaDescription_fa',
      'label' => 'Default Meta Description (Persian)',
      'type' => 'text',
    ],
    15 => [
      'name' => 'defaultMetaDescription_en',
      'label' => 'Default Meta Description (English)',
      'type' => 'text',
    ],
    16 => [
      'name' => 'defaultMetaDescription_tr',
      'label' => 'Default Meta Description (Turkish)',
      'type' => 'text',
    ],
    17 => [
      'name' => 'defaultOgImage',
      'label' => 'Default OG Image',
      'type' => 'asset',
    ],
    18 => [
      'name' => 'gemini_api_key',
      'label' => 'OpenRouter API Key (Primary — AI translation)',
      'type' => 'secret',
    ],
    19 => [
      'name' => 'gemini_fallback_api_key',
      'label' => 'Google Gemini API Key (Fallback — AI translation)',
      'type' => 'secret',
    ],
    20 => [
      'name' => 'openrouter_model',
      'label' => 'OpenRouter Model (AI translation)',
      'type' => 'openrouter-model',
      'info' => 'Default: openrouter/free (automatic free model). The list is loaded live from OpenRouter.',
    ],
  ],
  'preview' => [
  ],
  'group' => NULL,
  'meta' => NULL,
  '_created' => 1789826508,
  '_modified' => 1789826508,
];