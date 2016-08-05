<?php

/**
 * @file
 * Contains \Drupal\multiversion\Tests\RevisionTreeIndexTest.
 */

namespace Drupal\multiversion\Tests;
use Drupal\multiversion\Entity\Index\RevisionTreeIndex;

/**
 * Test the methods on the RevisionTreeIndex class.
 *
 * @group multiversion
 *
 * @todo: {@link https://www.drupal.org/node/2597486 Test more entity types,
 * like in \Drupal\multiversion\Tests\EntityStorageTest.}
 */
class RevisionTreeIndexTest extends MultiversionWebTestBase {

  public static $modules = ['entity_test', 'multiversion'];

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

  public function testWithoutDelete() {
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

    $expected_tree = array(
      array(
        '#type' => 'rev',
        '#uuid' => $uuid,
        '#rev' => $revs[0],
        '#rev_info' => array(
          'status' => 'available',
          'default' => FALSE,
          'open_rev' => FALSE,
          'conflict' => FALSE,
        ),
        'children' => array(
          array(
            '#type' => 'rev',
            '#uuid' => $uuid,
            '#rev' => $revs[1],
            '#rev_info' => array(
              'status' => 'available',
              'default' => FALSE,
              'open_rev' => FALSE,
              'conflict' => FALSE,
            ),
            'children' => array(
              array(
                '#type' => 'rev',
                '#uuid' => $uuid,
                '#rev' => $revs[2],
                '#rev_info' => array(
                  'status' => 'available',
                  'default' => FALSE,
                  'open_rev' => TRUE,
                  'conflict' => TRUE,
                ),
                'children' => array(),
              ),
              array(
                '#type' => 'rev',
                '#uuid' => $uuid,
                '#rev' => $revs[3],
                '#rev_info' => array(
                  'status' => 'available',
                  'default' => FALSE,
                  'open_rev' => FALSE,
                  'conflict' => FALSE,
                ),
                'children' => array(
                  array(
                    '#type' => 'rev',
                    '#uuid' => $uuid,
                    '#rev' => $revs[4],
                    '#rev_info' => array(
                      'status' => 'available',
                      'default' => TRUE,
                      'open_rev' => TRUE,
                      'conflict' => FALSE,
                    ),
                    'children' => array(),
                  )
                )
              )
            )
          ),
          array(
            '#type' => 'rev',
            '#uuid' => $uuid,
            '#rev' => $revs[5],
            '#rev_info' => array(
              'status' => 'available',
              'default' => FALSE,
              'open_rev' => TRUE,
              'conflict' => TRUE,
            ),
            'children' => array(),
          )
        )
      )
    );
    // Sort the expected tree according to the algorithm.
    RevisionTreeIndex::sortTree($expected_tree);

    $tree = $this->tree->getTree($uuid);
    $this->assertEqual($tree, $expected_tree, 'Tree was correctly parsed.');

    $default_rev = $this->tree->getDefaultRevision($uuid);
    $this->assertEqual($default_rev, $revs[4], 'Default revision is correct.');

    $expected_default_branch = [
      $revs[0] => 'available',
      $revs[1] => 'available',
      $revs[3] => 'available',
      $revs[4] => 'available',
    ];
    $default_branch = $this->tree->getDefaultBranch($uuid);
    $this->assertEqual($default_branch, $expected_default_branch, 'Default branch is correct.');

    $count = $this->tree->countRevs($uuid);
    $this->assertEqual($count, 4, 'Number of revisions is correct.');

    $expected_open_revision = [
      $revs[2] => 'available',
      $revs[4] => 'available',
      $revs[5] => 'available',
    ];
    $open_revisions = $this->tree->getOpenRevisions($uuid);
    $this->assertEqual($open_revisions, $expected_open_revision, 'Open revisions are correct.');

    $expected_conflicts = [
      $revs[2] => 'available',
      $revs[5] => 'available',
    ];
    $conflicts = $this->tree->getConflicts($uuid);
    $this->assertEqual($conflicts, $expected_conflicts, 'Conflicts are correct');
  }

  public function testWithDelete() {
    $storage = $this->entityManager->getStorage('entity_test');
    $entity = $storage->create();
    $uuid = $entity->uuid();

    // Create a conflict scenario to fully test the parsing.

    // Initial revision.
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity->delete();
    $revs[] = $entity->_rev->value;

    $default_branch = $this->revTree->getDefaultBranch($uuid);
    $expected_default_branch = [
      $revs[0] => 'available',
      $revs[1] => 'deleted',
    ];
    $this->assertEqual($default_branch, $expected_default_branch, 'Default branch is corrected when default revision is deleted.');

    $entity->_deleted->value = FALSE;
    $entity->save();
    $revs[] = $leaf_one = $entity->_rev->value;

    $default_branch = $this->revTree->getDefaultBranch($uuid);
    $expected_default_branch = [
      $revs[0] => 'available',
      $revs[1] => 'deleted',
      $revs[2] => 'available',
    ];
    $this->assertEqual($default_branch, $expected_default_branch, 'Default branch is corrected when un-deleting the previous default revision which was deleted.');

    $entity = $storage->load(1);
    $this->assertEqual($entity->getRevisionId(), 3, 'Default revision has been set correctly.');

    // Create a new branch from the second revision.
    $entity = $storage->loadRevision(2);
    $entity->delete();
    $revs[] = $leaf_two = $entity->_rev->value;

    // We now have two leafs at the tip of the tree.
    $leafs = [$leaf_one, $leaf_two];
    sort($leafs);
    $expected_leaf = array_pop($leafs);
    // In this test we actually don't know which revision that became default.
    $entity = $storage->load(1) ?: $storage->loadDeleted(1);
    $this->assertEqual($entity->_rev->value, $expected_leaf, 'The correct revision won while having two open revisions.');

    // Continue the last branch.
    $entity = $storage->loadRevision(4);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadDeleted(1);
    $this->assertEqual($entity->getRevisionId(), 5, 'Default revision has been set correctly.');

    // Create a new branch based on the first revision.
    $entity = $storage->loadRevision(1);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $storage->loadDeleted(1);
    $this->assertEqual($entity->getRevisionId(), 5, 'Default revision has been set correctly.');

    $expected_tree = array(
      array(
        '#type' => 'rev',
        '#uuid' => $uuid,
        '#rev' => $revs[0],
        '#rev_info' => array(
          'status' => 'available',
          'default' => FALSE,
          'open_rev' => FALSE,
          'conflict' => FALSE,
        ),
        'children' => array(
          array(
            '#type' => 'rev',
            '#uuid' => $uuid,
            '#rev' => $revs[1],
            '#rev_info' => array(
              'status' => 'deleted',
              'default' => FALSE,
              'open_rev' => FALSE,
              'conflict' => FALSE,
            ),
            'children' => array(
              array(
                '#type' => 'rev',
                '#uuid' => $uuid,
                '#rev' => $revs[2],
                '#rev_info' => array(
                  'status' => 'available',
                  'default' => TRUE,
                  'open_rev' => TRUE,
                  'conflict' => FALSE,
                ),
                'children' => array(),
              ),
              array(
                '#type' => 'rev',
                '#uuid' => $uuid,
                '#rev' => $revs[3],
                '#rev_info' => array(
                  'status' => 'deleted',
                  'default' => FALSE,
                  'open_rev' => FALSE,
                  'conflict' => FALSE,
                ),
                'children' => array(
                  array(
                    '#type' => 'rev',
                    '#uuid' => $uuid,
                    '#rev' => $revs[4],
                    '#rev_info' => array(
                      'status' => 'deleted',
                      'default' => FALSE,
                      'open_rev' => TRUE,
                      'conflict' => FALSE,
                    ),
                    'children' => array(),
                  )
                )
              )
            )
          ),
          array(
            '#type' => 'rev',
            '#uuid' => $uuid,
            '#rev' => $revs[5],
            '#rev_info' => array(
              'status' => 'available',
              'default' => FALSE,
              'open_rev' => TRUE,
              'conflict' => TRUE,
            ),
            'children' => array(),
          )
        )
      )
    );
    // Sort the expected tree according to the algorithm.
    RevisionTreeIndex::sortTree($expected_tree);

    $tree = $this->tree->getTree($uuid);
    $this->assertEqual($tree, $expected_tree, 'Tree was correctly parsed.');

    $default_rev = $this->tree->getDefaultRevision($uuid);
    $this->assertEqual($default_rev, $revs[2], 'Default revision is correct.');

    $expected_default_branch = [
      $revs[0] => 'available',
      $revs[1] => 'deleted',
      $revs[2] => 'available',
    ];
    $default_branch = $this->tree->getDefaultBranch($uuid);
    $this->assertEqual($default_branch, $expected_default_branch, 'Default branch is correct.');

    $count = $this->tree->countRevs($uuid);
    $this->assertEqual($count, 3, 'Number of revisions is correct.');

    $expected_open_revision = [
      $revs[2] => 'available',
      $revs[4] => 'deleted',
      $revs[5] => 'available',
    ];
    $open_revisions = $this->tree->getOpenRevisions($uuid);
    $this->assertEqual($open_revisions, $expected_open_revision, 'Open revisions are correct.');

    $expected_conflicts = [
      $revs[5] => 'available',
    ];
    $conflicts = $this->tree->getConflicts($uuid);
    $this->assertEqual($conflicts, $expected_conflicts, 'Conflicts are correct');
  }

}
