<?php

/**
 * @file
 * Contains \Drupal\multiversion\Tests\ComplexLcaResolverTest.
 */

namespace Drupal\multiversion\Tests;

use Drupal;
use Drupal\multiversion\Entity\Index\RevisionTreeIndex;
use Fhaculty\Graph\Graph;
use Relaxed\LCA\LowestCommonAncestor;
use Relaxed\LCA\LcaException;

/**
 * Test the getGraph method from the RevisionTreeIndex class.
 *
 * @group multiversion
 */
class ComplexLcaResolverTest extends MultiversionWebTestBase
{

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
  protected function setUp()
  {
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

    $graph = $this->tree->getGraph($uuid);
    $vertices = $graph->getVertices()->getMap();

    $lca = new LowestCommonAncestor($graph);

    $manager = Drupal::service('conflict.lca_manager');
    $parent_revision_id1 = $manager->resolveLowestCommonAncestor($vertices[$revs[2]], $vertices[$revs[3]], $graph);
    $revisionLca = Drupal::entityTypeManager()
      ->getStorage('entity_test')
      ->loadRevision($parent_revision_id1);
    $this->assertEqual($revisionLca, $vertices[$revs[1]], "Yes we got it");
  }
}