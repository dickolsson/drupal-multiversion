<?php

namespace Drupal\multiversion\Tests;

use Drupal\multiversion\Tests\MultiversionWebTestBase;
use Drupal\simpletest;
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
    $this->conflictLcaManager = $this->container->get('conflict.lca_manager');
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

    // Initial revision.
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

    // Continue the last branch.
    $entity = $storage->loadRevision(4);
    $entity->save();
    $revs[] = $entity->_rev->value;

    // Create a new branch based on the first revision.
    $entity = $storage->loadRevision(1);
    $entity->save();
    $revs[] = $entity->_rev->value;

    // Loading and storing revisions in $revision array.
    $revision = [];
    for ($i = 1; $i <= 6; $i++) {
      $revision[$i] = $storage->loadRevision($i);
    }

    // Creating a graph of revision IDs from revision tree.
    $graph = $this->tree->getGraph($uuid);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[1],$revision[2], $graph);
    $this->assertEqual($lca_id->getId(), $revs[0]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[3],$revision[4], $graph);
    $this->assertEqual($lca_id->getId(), $revs[1]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[3],$revision[6], $graph);
    $this->assertEqual($lca_id->getId(), $revs[0]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[2],$revision[6], $graph);
    $this->assertEqual($lca_id->getId(), $revs[0]);
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

    // Create a new branch from the first revision.
    $entity = $storage->loadRevision(1);
    $entity->save();
    $revs[] = $entity->_rev->value;

    // Creating a new branch from sixth revision.
    $entity = $storage->loadRevision(6);
    $entity->save();
    $revs[] = $entity->_rev->value;

    // Creating another revision branch from sixth revision.
    $entity = $storage->loadRevision(6);
    $entity->name = 'Revision6';
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(3);
    $entity->save();
    $revs[] = $entity->_rev->value;

    // Create a new branch based on the first revision.
    $entity = $storage->loadRevision(8);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $revision = [];
    for ($i = 1; $i <= 10; $i++) {
      $revision[$i] = $storage->loadRevision($i);
    }

    $graph = $this->tree->getGraph($uuid);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[1],$revision[2], $graph);
    $this->assertEqual($lca_id->getId(), $revs[0]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[2],$revision[6], $graph);
    $this->assertEqual($lca_id->getId(), $revs[0]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[3],$revision[5], $graph);
    $this->assertEqual($lca_id->getId(), $revs[1]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[2],$revision[3], $graph);
    $this->assertEqual($lca_id->getId(), $revs[1]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[4],$revision[5], $graph);
    $this->assertEqual($lca_id->getId(), $revs[1]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[4],$revision[9], $graph);
    $this->assertEqual($lca_id->getId(), $revs[2]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[7],$revision[10], $graph);
    $this->assertEqual($lca_id->getId(), $revs[5]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[6],$revision[7], $graph);
    $this->assertEqual($lca_id->getId(), $revs[5]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[7],$revision[8], $graph);
    $this->assertEqual($lca_id->getId(), $revs[5]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[8],$revision[9], $graph);
    $this->assertEqual($lca_id->getId(), $revs[0]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[6],$revision[8], $graph);
    $this->assertEqual($lca_id->getId(), $revs[5]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[3],$revision[9], $graph);
    $this->assertEqual($lca_id->getId(), $revs[2]);
  }

  // Graph structure in multiversion/vendor/relaxedws/lca/pictures/simple_graph.png
  public function testLcaFinder3() {
    $storage = $this->entityManager->getStorage('entity_test');
    $entity = $storage->create();
    $uuid = $entity->uuid();

    // Initial revision.
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity->save();
    $revs[] = $entity->_rev->value;

    // Create a new branch from the third revision.
    $entity = $storage->loadRevision(3);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity->save();
    $revs[] = $entity->_rev->value;

    // Create a new branch from the fourth revision.
    $entity = $storage->loadRevision(4);
    $entity->save();
    $revs[] = $entity->_rev->value;

    // Create a new branch from the fifth revision.
    $entity = $storage->loadRevision(5);
    $entity->save();
    $revs[] = $entity->_rev->value;


    $entity = $storage->loadRevision(3);
    $entity->name = 'Revision3';
    $entity->save();
    $revs[] = $entity->_rev->value;
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(1);
    $entity->name = 'Revision6';
    $entity->save();
    $revs[] = $entity->_rev->value;
    $entity->save();
    $revs[] = $entity->_rev->value;
    $entity->save();
    $revs[] = $entity->_rev->value;
    $entity->save();
    $revs[] = $entity->_rev->value;
    $entity->save();
    $revs[] = $entity->_rev->value;
    $entity = $storage->loadRevision(14);
    $entity->name = 'Revision14';
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity->save();
    $revs[] = $entity->_rev->value;
    $entity = $storage->loadRevision(16);
    $entity->name = 'Revision16';
    $entity->save();
    $revs[] = $entity->_rev->value;

    // Creating graph from revision tree.
    $graph = $this->tree->getGraph($uuid);

    // Loading and storing revisions in $revision array.
    $revision = [];
    for ($i = 1; $i <= 21; $i++) {
      $revision[$i] = $storage->loadRevision($i);
    }

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[1],$revision[2], $graph);
    $this->assertEqual($lca_id->getId(), $revs[0]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[2],$revision[3], $graph);
    $this->assertEqual($lca_id->getId(), $revs[1]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[7],$revision[8], $graph);
    $this->assertEqual($lca_id->getId(), $revs[6]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[5],$revision[9], $graph);
    $this->assertEqual($lca_id->getId(), $revs[3]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[10],$revision[11], $graph);
    $this->assertEqual($lca_id->getId(), $revs[2]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[16],$revision[17], $graph);
    $this->assertEqual($lca_id->getId(), $revs[15]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[17],$revision[20], $graph);
    $this->assertEqual($lca_id->getId(), $revs[13]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[6],$revision[21], $graph);
    $this->assertEqual($lca_id->getId(), $revs[0]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[6],$revision[11], $graph);
    $this->assertEqual($lca_id->getId(), $revs[2]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[17],$revision[21], $graph);
    $this->assertEqual($lca_id->getId(), $revs[15]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[15],$revision[18], $graph);
    $this->assertEqual($lca_id->getId(), $revs[13]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[5],$revision[9], $graph);
    $this->assertEqual($lca_id->getId(), $revs[3]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[6],$revision[10], $graph);
    $this->assertEqual($lca_id->getId(), $revs[4]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[7],$revision[11], $graph);
    $this->assertEqual($lca_id->getId(), $revs[2]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[13],$revision[14], $graph);
    $this->assertEqual($lca_id->getId(), $revs[12]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[8],$revision[12], $graph);
    $this->assertEqual($lca_id->getId(), $revs[2]);

    $lca_id = $this->conflictLcaManager->resolveLowestCommonAncestor($revision[4],$revision[8], $graph);
    $this->assertEqual($lca_id->getId(), $revs[2]);
  }
}
