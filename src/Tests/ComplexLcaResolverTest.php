<?php

/**
 * @file
 * Contains \Drupal\multiversion\Tests\ComplexLcaResolverTest.
 */

namespace Drupal\multiversion\Tests;

use Drupal;
use Fhaculty\Graph\Graph;
use Relaxed\LCA\LowestCommonAncestor;
use Relaxed\LCA\LcaException;

/**
 * Test the Integration of LCA library with multiversion module.
 *
 * @group multiversion
 */
class ComplexLcaResolverTest extends MultiversionWebTestBase {

  public static $modules = ['entity_test', 'key_value', 'entity_storage_migrate', 'multiversion', 'conflict'];

  /**
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * @var \Drupal\multiversion\Entity\Index\RevisionTreeIndex
   */
  protected $tree;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->tree = $this->container->get('multiversion.entity_index.rev.tree');
  }

  /**
   * Shape of Tree created is:
   *              1
   *            /   \
   *           2     6
   *         /   \
   *        3     4
   *             /
   *            5
   */
  public function testLcaFinder() {
    $storage = $this->entityManager->getStorage('entity_test');
    $entity = $storage->create();
    $uuid = $entity->uuid();

    // Create a conflict scenario to fully test the parsing.

    // Initial revision.
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity->save();
    $revs[] = $leaf_one = $entity->_rev->value;

    $entity = $storage->load(1);
    $this->assertEqual($entity->getRevisionId(), 3, 'Default revision has been set correctly.');

    // Create a new branch from the second revision.
    $entity = $storage->loadRevision(2);
    $entity->save();
    $revs[] = $leaf_two = $entity->_rev->value;

    // We now have two leafs at the tip of the tree.
    $leafs = [$leaf_one, $leaf_two];
    sort($leafs);
    $expected_leaf = array_pop($leafs);
    $entity = $storage->load(1);
    $this->assertEqual($entity->_rev->value, $expected_leaf, 'The correct revision won while having two open revisions.');

    // Continue the last branch.
    $entity = $storage->loadRevision(4);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->load(1);
    $this->assertEqual($entity->getRevisionId(), 5, 'Default revision has been set correctly.');

    // Create a new branch based on the first revision.
    $entity = $storage->loadRevision(1);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->load(1);
    $this->assertEqual($entity->getRevisionId(), 5, 'Default revision has been set correctly.');

    $revision1 = Drupal::entityTypeManager()
      ->getStorage('entity_test')
      ->loadRevision(3);

    $revision2 = Drupal::entityTypeManager()
      ->getStorage('entity_test')
      ->loadRevision(4);

    $graph = $this->tree->getGraph($uuid);

    $manager = Drupal::service('conflict.lca_manager');
    $parent_revision_id1 = $manager->resolveLowestCommonAncestor($revision1,$revision2, $graph);
//    $revisionLca = Drupal::entityTypeManager()
//      ->getStorage('entity_test')
//      ->loadRevision($parent_revision_id1->getId());
    $this->assertEqual($parent_revision_id1->getId(), $revs[1]);
  }

  /**
   *  Shape of tree is:
   *            1
   *          /   \
   *         2     6
   *        / \   / \
   *       3   5 7   8
   *      / \       /
   *     4   9    10
   *
   */
  public function testLcaFinder2() {
    $storage = $this->entityManager->getStorage('entity_test');
    $entity = $storage->create();
    $uuid = $entity->uuid();

    // Create a conflict scenario to fully test the parsing.

    // Initial revision.
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity->save();
    $revs[] = $entity->_rev->value;

    // Create a new branch from the second revision.
    $entity = $storage->loadRevision(2);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(1);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(6);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(6);
    $entity->save();
    $revs[] = $entity->_rev->value;
    // Continue the last branch.
    $entity = $storage->loadRevision(3);
    $entity->save();
    $revs[] = $entity->_rev->value;

    // Create a new branch based on the first revision.
    $entity = $storage->loadRevision(8);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $graph = $this->tree->getGraph($uuid);

    $revision1 = Drupal::entityTypeManager()
      ->getStorage('entity_test')
      ->loadRevision(1);

    $revision2 = Drupal::entityTypeManager()
      ->getStorage('entity_test')
      ->loadRevision(2);

    $revision3 = Drupal::entityTypeManager()
      ->getStorage('entity_test')
      ->loadRevision(3);

    $revision4 = Drupal::entityTypeManager()
      ->getStorage('entity_test')
      ->loadRevision(4);

    $revision5 = Drupal::entityTypeManager()
      ->getStorage('entity_test')
      ->loadRevision(5);

    $revision6 = Drupal::entityTypeManager()
      ->getStorage('entity_test')
      ->loadRevision(6);

    $revision7 = Drupal::entityTypeManager()
      ->getStorage('entity_test')
      ->loadRevision(7);

    $revision8 = Drupal::entityTypeManager()
      ->getStorage('entity_test')
      ->loadRevision(8);

    $revision9 = Drupal::entityTypeManager()
      ->getStorage('entity_test')
      ->loadRevision(9);

    $revision10 = Drupal::entityTypeManager()
      ->getStorage('entity_test')
      ->loadRevision(10);
    $manager = Drupal::service('conflict.lca_manager');
    $parent_revision_id1 = $manager->resolveLowestCommonAncestor($revision1,$revision2, $graph);
    $this->assertEqual($parent_revision_id1->getId(), $revs[0]);

    $parent_revision_id1 = $manager->resolveLowestCommonAncestor($revision2,$revision6, $graph);
    $this->assertEqual($parent_revision_id1->getId(), $revs[0]);

    $parent_revision_id1 = $manager->resolveLowestCommonAncestor($revision3,$revision5, $graph);
    $this->assertEqual($parent_revision_id1->getId(), $revs[1]);

    $parent_revision_id1 = $manager->resolveLowestCommonAncestor($revision2,$revision3, $graph);
    $this->assertEqual($parent_revision_id1->getId(), $revs[1]);

    $parent_revision_id1 = $manager->resolveLowestCommonAncestor($revision4,$revision5, $graph);
    $this->assertEqual($parent_revision_id1->getId(), $revs[1]);

    $parent_revision_id1 = $manager->resolveLowestCommonAncestor($revision4,$revision9, $graph);
    $this->assertEqual($parent_revision_id1->getId(), $revs[2]);

    $parent_revision_id1 = $manager->resolveLowestCommonAncestor($revision7,$revision10, $graph);
    $this->assertEqual($parent_revision_id1->getId(), $revs[5]);

    $parent_revision_id1 = $manager->resolveLowestCommonAncestor($revision6,$revision7, $graph);
    $this->assertEqual($parent_revision_id1->getId(), $revs[5]);

    $parent_revision_id1 = $manager->resolveLowestCommonAncestor($revision7,$revision8, $graph);
    $this->assertEqual($parent_revision_id1->getId(), $revs[5]);
  }
}
