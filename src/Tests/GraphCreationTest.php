<?php

namespace Drupal\multiversion\Tests;
use Fhaculty\Graph\Graph;

/**
 * Test the getGraph method from the RevisionTreeIndex class.
 *
 * @group multiversion
 */
class GraphCreationTest extends MultiversionWebTestBase {

  public static $modules = ['entity_test', 'key_value', 'entity_storage_migrate', 'multiversion'];

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
  public function testGraphCreation() {
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

    // Creating graph from the revision tree.
    $graph = $this->tree->getGraph($uuid);
    
    // Storing the graph's vertices in $vertices array.
    $vertices = $graph->getVertices()->getMap();

    foreach ($vertices[$revs[1]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[0],'node 2\'s parent is 1');
    }
    foreach ($vertices[$revs[2]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[1],'node 3\'s parent is 2');
    }
    foreach ($vertices[$revs[3]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(),  $revs[1],'node 4\'s parent is 2');
    }
    foreach ($vertices[$revs[4]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[3], 'node 5\'s parent is 4');
    }
    foreach ($vertices[$revs[5]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[0], 'node 6\'s parent is 1');
    }
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
  public function testGraphCreation2() {
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
    $vertices = $graph->getVertices()->getMap();

    foreach ($vertices[$revs[1]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[0],'node 2\'s parent is 1');
    }
    foreach ($vertices[$revs[2]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[1],'node 3\'s parent is 2');
    }
    foreach ($vertices[$revs[3]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(),  $revs[2],'node 4\'s parent is 3');
    }
    foreach ($vertices[$revs[4]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[1], 'node 5\'s parent is 2');
    }
    foreach ($vertices[$revs[5]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[0], 'node 6\'s parent is 1');
    }
    foreach ($vertices[$revs[6]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[5],'node 7\'s parent is 6');
    }
    foreach ($vertices[$revs[7]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[5],'node 8\'s parent is 6');
    }
    foreach ($vertices[$revs[8]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(),  $revs[2],'node 9\'s parent is 3');
    }
    foreach ($vertices[$revs[9]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[7], 'node 10\'s parent is 8');
    }
  }

  /**
   * Graph structure defined in /vendor/relaxedws/lca/pictures/simple_graph.png
   */
  public function testGraphCreation3() {
    $storage = $this->entityManager->getStorage('entity_test');
    $entity = $storage->create();
    $uuid = $entity->uuid();

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

    $entity = $storage->loadRevision(3);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(3);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(4);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(7);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(8);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(1);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(12);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(13);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(13);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(14);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(15);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(16);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(16);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(17);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadRevision(5);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $graph = $this->tree->getGraph($uuid);
    $vertices = $graph->getVertices()->getMap();

    foreach ($vertices[$revs[1]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[0],'node 2\'s parent is 1');
    }
    foreach ($vertices[$revs[2]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[1],'node 3\'s parent is 2');
    }
    foreach ($vertices[$revs[3]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(),  $revs[2],'node 4\'s parent is 3');
    }
    foreach ($vertices[$revs[4]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[3], 'node 5\'s parent is 4');
    }
    foreach ($vertices[$revs[5]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[4], 'node 6\'s parent is 5');
    }
    foreach ($vertices[$revs[6]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[2],'node 7\'s parent is 3');
    }
    foreach ($vertices[$revs[7]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[2],'node 8\'s parent is 3');
    }
    foreach ($vertices[$revs[8]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(),  $revs[3],'node 9\'s parent is 4');
    }
    foreach ($vertices[$revs[9]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[6], 'node 10\'s parent is 7');
    }
    foreach ($vertices[$revs[10]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[7],'node 11\'s parent is 8');
    }
    foreach ($vertices[$revs[11]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[0],'node 12\'s parent is 1');
    }
    foreach ($vertices[$revs[12]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(),  $revs[11],'node 13\'s parent is 12');
    }
    foreach ($vertices[$revs[13]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[12], 'node 14\'s parent is 13');
    }
    foreach ($vertices[$revs[14]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[12], 'node 15\'s parent is 13');
    }
    foreach ($vertices[$revs[15]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[13],'node 16\'s parent is 14');
    }
    foreach ($vertices[$revs[16]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[14],'node 17\'s parent is 15');
    }
    foreach ($vertices[$revs[17]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(),  $revs[15],'node 18\'s parent is 16');
    }
    foreach ($vertices[$revs[18]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[15], 'node 19\'s parent is 16');
    }
    foreach ($vertices[$revs[19]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(),  $revs[16],'node 20\'s parent is 17');
    }
    foreach ($vertices[$revs[20]]->getVerticesEdgeFrom() as $parent) {
      $this->assertEqual($parent->getId(), $revs[4], 'node 21\'s parent is 5');
    }
  }
}
