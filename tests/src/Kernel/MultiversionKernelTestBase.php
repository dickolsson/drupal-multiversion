<?php

namespace Drupal\Tests\multiversion\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Class MultiversionKernelTestBase
 *
 * @package Drupal\Tests\multiversion\Kernel
 */
abstract class MultiversionKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'multiversion',
    'key_value',
    'serialization',
    'user',
    'system',
    'node'
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('workspace');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig('multiversion');
    $this->installSchema('key_value', 'key_value_sorted');
    $multiversion_manager = $this->container->get('multiversion.manager');
    $multiversion_manager->enableEntityTypes();
  }

}

