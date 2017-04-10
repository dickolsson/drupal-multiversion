<?php

namespace Drupal\multiversion\Tests;

use Drupal\simpletest;

/**
 * Test the Integration of LCA library with multiversion module.
 *
 * @group multiversion
 */
class ComplexMergeResolverTest extends MultiversionWebTestBase {

  public static $modules = ['entity_test', 'key_value', 'entity_storage_migrate', 'multiversion', 'conflict', 'serialization'];

  protected $merge_array = [];

  protected $conflictMergeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->conflictMergeManager = $this->container->get('conflict.merge_manager');
    $this->tree = $this->container->get('multiversion.entity_index.rev.tree');
  }

  public function testMergeResolver() {
    $storage = $this->entityManager->getStorage('entity_test');
    $entity = $storage->create(['name' => 'rev 1']);
    $entity->save();

    $entity->name = 'rev 2';
    $entity->save();

    $entity->name = 'rev 1';
    $entity->save();

    $rev1 = $storage->loadRevision(1);
    $rev2 = $storage->loadRevision(2);
    $rev3 = $storage->loadRevision(3);

    $merged = $this->conflictMergeManager->resolveSimpleMerge($rev1,$rev2,$rev3);
    $this->assertEqual($merged->name, 'rev 2');
  }
}
