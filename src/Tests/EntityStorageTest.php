<?php

/**
 * @file
 * Contains \Drupal\multiversion\Tests\EntityStorageTest.
 */

namespace Drupal\multiversion\Tests;

use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;
use Drupal\multiversion\Entity\Workspace;

/**
 * Test the content entity storage controller.
 *
 * @group multiversion
 */
class EntityStorageTest extends MultiversionWebTestBase {

  /**
   * The entity types to test.
   *
   * @var array
   */
  protected $entityTypes = [
    'entity_test' => [
      'info' => [],
      'data_table' => 'entity_test',
      'revision_table' => 'entity_test_revision',
      'id' => 'id',
    ],
    'entity_test_rev' => [
      'info' => [],
      'data_table' => 'entity_test_rev',
      'revision_table' => 'entity_test_rev_revision',
      'id' => 'id',
    ],
    'entity_test_mul' =>[
      'info' => [],
      'data_table' => 'entity_test_mul_property_data',
      'revision_table' => 'entity_test_mul_field_revision',
      'id' => 'id',
    ],
    'entity_test_mulrev' => [
      'info' => [],
      'data_table' => 'entity_test_mulrev_property_data',
      'revision_table' => 'entity_test_mulrev_property_revision',
      'id' => 'id',
    ],
    'node' => [
      'info' => [
        'type' => 'article',
        'title' => 'New article',
      ],
      'data_table' => 'node_field_data',
      'revision_table' => 'node_field_revision',
      'id' => 'nid',
    ],
    'taxonomy_term' => [
      'info' => [
        'name' => 'A term',
        'vid' => 123,
      ],
      'data_table' => 'taxonomy_term_field_data',
      'revision_table' => 'taxonomy_term_field_revision',
      'id' => 'tid',
    ],
    'comment' => [
      'info' => [
        'entity_type' => 'node',
        'field_name' => 'comment',
        'subject' => 'How much wood would a woodchuck chuck',
        'mail' => 'someone@example.com',
      ],
      'data_table' => 'comment_field_data',
      'revision_table' => 'comment_field_revision',
      'id' => 'cid',
    ],
    'block_content' =>  [
      'info' => [
        'info' => 'New block',
        'type' => 'basic',
      ],
      'data_table' => 'block_content_field_data',
      'revision_table' => 'block_content_field_revision',
      'id' => 'id',
    ],
    'menu_link_content' => [
      'info' => [
        'menu_name' => 'menu_test',
        'bundle' => 'menu_link_content',
        'link' => [['uri' => 'user-path:/']],
      ],
      'data_table' => 'menu_link_content_data',
      'revision_table' => 'menu_link_content_field_revision',
      'id' => 'id',
    ],
    'file' => [
      'info' => [
        'uid' => 1,
        'filename' => 'drupal.txt',
        'uri' => 'public://drupal.txt',
        'filemime' => 'text/plain',
        'status' => FILE_STATUS_PERMANENT,
      ],
      'data_table' => 'file_managed',
      'revision_table' => 'file_revision',
      'id' => 'fid',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    foreach ($this->entityTypes as $entity_type_id => $info) {
      $this->entityTypes[$entity_type_id]['revision_id'] = $entity_type_id == 'node' ? 'vid' : 'revision_id';
      if ($entity_type_id == 'node' || $entity_type_id == 'menu_link_content') {
        $this->entityTypes[$entity_type_id]['name'] = 'title';
      }
      elseif ($entity_type_id == 'block_content') {
        $this->entityTypes[$entity_type_id]['name'] = 'info';
      }
      else {
        $this->entityTypes[$entity_type_id]['name'] = 'name';
      }

      if ($entity_type_id == 'file') {
        file_put_contents($info['info']['uri'], 'Hello world!');
        $this->assertTrue($info['info']['uri'], t('The test file has been created.'));
      }
    }
  }

  public function testEntityStorage() {
    // Test save and load.

    foreach ($this->entityTypes as $entity_type_id => $info) {
      $storage = $this->entityManager->getStorage($entity_type_id);
      $message = "$entity_type_id has the correct storage handler.";
      if ($storage instanceof ContentEntityStorageInterface) {
        $this->pass($message);
      }
      else {
        $this->fail($message);
        // No idea to continue because things will completely blow up.
        continue;
      }

      $ids = [];
      $entity = $storage->create($info['info']);
      $return = $entity->save();
      $this->assertEqual($return, SAVED_NEW, "$entity_type_id was saved.");

      $ids[] = $entity->id();
      $loaded = $storage->load($ids[0]);
      $this->assertEqual($ids[0], $loaded->id(), "Single $entity_type_id was loaded.");

      // Load the entity with EntityRepository::loadEntityByUuid().
      $loaded = \Drupal::service('entity.repository')->loadEntityByUuid($entity_type_id, $entity->uuid());
      $this->assertEqual($ids[0], $loaded->id(), "Single $entity_type_id was loaded with loadEntityByUuid().");

      // Update and save a new revision.
      $entity->{$info['name']} = $this->randomMachineName();
      $entity->save();
      // For user entity type we should have 4 entities: anonymous, root
      // user, test user and the new created user. For other entity types we should have
      // just the new created entity.
      $revision_id = 1;
      /** @var \Drupal\Core\Entity\ContentEntityInterface $revision */
      $revision = $storage->loadRevision($revision_id);
      $this->assertTrue(($revision->getRevisionId() == $revision_id && !$revision->isDefaultRevision()), "Old revision of $entity_type_id was loaded.");

      $entity = $storage->create($info['info']);
      $entity->save();
      $ids[] = $entity->id();

      $entities = $storage->loadMultiple($ids);
      $this->assertEqual(count($entities), 2, "Multiple $entity_type_id was loaded.");

      // Test delete.

      $entity = $storage->create($info['info']);
      $entity->save();
      $id = $entity->id();
      $revision_id = $entity->getRevisionId();
      $entities = $storage->loadMultiple([$id]);
      $storage->delete($entities);

      $record = db_select($info['revision_table'], 'e')
        ->fields('e')
        ->condition('e.' . $info['id'], $id)
        ->condition('e.' . $info['revision_id'], $revision_id + 1)
        ->execute()
        ->fetchObject();

      $this->assertEqual($record->_deleted, 1, "Deleted $entity_type_id is still stored but flagged as deleted");
      $entity = $storage->load($id);
      $this->assertTrue(empty($entity), "Deleted $entity_type_id did not load with entity_load() function.");

      $entity = $storage->loadDeleted($id);
      $this->assertTrue(!empty($entity), "Deleted $entity_type_id loaded with loadDeleted() method.");
      $this->assertNotEqual($revision_id, $entity->getRevisionId(), "New revision was generated when deleting $entity_type_id.");

      $entities = $storage->loadMultipleDeleted([$id]);
      $this->assertTrue(!empty($entities), "Deleted $entity_type_id loaded with loadMultipleDeleted() method.");

      // Test revisions.

      $entity = $storage->create($info['info']);
      $entity->save();
      $id = $entity->id();
      $revision_id = $entity->getRevisionId();
      $revision = $storage->loadRevision($revision_id);

      $this->assertEqual($revision_id, $revision->getRevisionId(), "$entity_type_id revision was loaded");

      $entities = $storage->loadMultiple([$id]);
      $storage->delete($entities);
      $new_revision_id = ($revision_id + 1);
      $revision = $storage->loadRevision($new_revision_id);
      $this->assertTrue(($revision->_deleted->value == TRUE && $revision->getRevisionId() == $new_revision_id), "Deleted $entity_type_id was loaded.");

      // Test exceptions.

      $entity_type = $this->entityManager->getDefinition($entity_type_id);
      $id_key = $entity_type->getKey('id');
      // Test with exception upon first save.
      $entity = $storage->create($info['info']);
      $uuid = $entity->uuid->value;
      try {
        // Trigger an error by setting the ID too large.
        $entity->{$id_key}->value = PHP_INT_MAX;
        $entity->save();
        $this->fail('Exception was not generated.');
      }
      catch(\Exception $e) {
        $first_rev = $entity->_rev->value;
        $rev_info = $this->revIndex->get("$uuid:$first_rev");
        $this->assertEqual($rev_info['status'], 'indexed', 'First revision was indexed after exception on first save.');
      }
      // Re-save the same entity with a valid ID.
      $entity->{$id_key}->value = NULL;
      $entity->save();
      $second_rev = $entity->_rev->value;
      $this->assertEqual($first_rev, $second_rev, 'New revision was not generated after first re-save.');

      $rev_info = $this->revIndex->get("$uuid:$first_rev");
      $this->assertEqual($rev_info['status'], 'available', 'First revision is available after first re-save.');
      $default_branch = $this->revTree->getDefaultBranch($uuid);
      $expected_default_branch = [
        $first_rev => 'available',
      ];
      $this->assertEqual($default_branch, $expected_default_branch, 'Default branch was built after exception on first save followed by re-save.');

      // Test with exception upon second save.
      $entity = $storage->create($info['info']);
      $uuid = $entity->uuid->value;
      $entity->save();
      $first_id = $entity->id();
      $first_rev = $entity->_rev->value;
      try {
        // Trigger an error by setting the ID too large.
        $entity->{$id_key}->value = PHP_INT_MAX;
        $entity->save();
        $this->fail('Exception was not generated.');
      }
      catch(\Exception $e) {
        $second_rev = $entity->_rev->value;
        $rev_info = $this->revIndex->get("$uuid:$second_rev");
        $this->assertEqual($rev_info['status'], 'indexed', 'Second revision was indexed after exception on second save.');
      }
      // Re-save the same entity with a valid ID.
      $entity->{$id_key}->value = $first_id;
      $entity->save();
      $third_rev = $entity->_rev->value;
      $this->assertEqual($second_rev, $third_rev, 'New revision was not generated after second re-save.');

      $rev_info = $this->revIndex->get("$uuid:$second_rev");
      $this->assertEqual($rev_info['status'], 'available', 'Third revision is available after second re-save.');
      $default_branch = $this->revTree->getDefaultBranch($uuid);
      $expected_default_branch = [
        $first_rev => 'available',
        $second_rev => 'available',
      ];
      $this->assertEqual($default_branch, $expected_default_branch, 'Default branch was built after exception on second save followed by re-save.');

      // Test workspace.

      if ($entity_type->get('workspace') !== FALSE) {
        $entity = $storage->create($info['info']);
        $entity->save();
        $entity_id = $entity->id();
        $this->assertEqual($entity->workspace->target_id, 1, "The workspace reference was saved for $entity_type_id.");
        $record = db_select($info['data_table'], 'e')
          ->fields('e')
          ->condition('e.' . $info['id'], $entity->id())
          ->condition('e.' . $info['revision_id'], $entity->getRevisionId())
          ->execute()
          ->fetchObject();
        $this->assertEqual($record->workspace, 1, "The workspace reference was stored for saved $entity_type_id.");

        $entities = $storage->loadMultiple([$entity_id]);
        $storage->delete($entities);
        $entity = $storage->loadDeleted($entity_id);
        $this->assertEqual($entity->workspace->target_id, 1, "The workspace reference is retained for deleted $entity_type_id.");
        $record = db_select($info['data_table'], 'e')
          ->fields('e')
          ->condition('e.' . $info['id'], $entity->id())
          ->condition('e.' . $info['revision_id'], $entity->getRevisionId())
          ->execute()
          ->fetchObject();
        $this->assertEqual($record->workspace, 1, "The workspace reference was stored for deleted $entity_type_id.");
      }
    }

    // Test saving entities in a different workspace.

    // Create a new workspace and switch to it.
    $workspace = Workspace::create([
      'machine_name' => $this->randomMachineName(),
      'label' => $this->randomMachineName(),
      'type' => 'basic'
    ]);
    $workspace->save();
    $this->workspaceManager->setActiveWorkspace($workspace);

    foreach ($this->entityTypes as $entity_type_id => $info) {
        $storage = $this->entityManager->getStorage($entity_type_id);
        $entity = $storage->create($info['info']);
        $entity->save();
      if ($entity->getEntityType()->get('workspace') !== FALSE) {
        $this->assertEqual($entity->workspace->target_id, $workspace->id(), "$entity_type_id was saved in new workspace.");
      }
    }

    $uuids = [];
    $ids = [];
    foreach ($this->entityTypes as $entity_type_id => $info) {
      $storage = $this->entityManager->getStorage($entity_type_id);
      $entity = $storage->create($info['info']);
      $entity->save();
      $uuids[$entity_type_id] = $entity->uuid();
      $ids[$entity_type_id] = $entity->id();

      $entity = $storage->load($ids[$entity_type_id]);
      $this->assertTrue(!empty($entity), "$entity_type_id was loaded in the workspace it belongs to.");
      $entity = $this->entityManager->loadEntityByUuid($entity_type_id, $uuids[$entity_type_id]);
      $this->assertTrue(!empty($entity), "$entity_type_id was loaded by UUID in the workspace it belongs to.");
    }

    // Switch back to the original workspace and check that the entities does
    // NOT exists here.
    $this->multiversionManager->setActiveWorkspaceId(1);

    foreach ($this->entityTypes as $entity_type_id => $info) {
      $storage = $this->entityManager->getStorage($entity_type_id);
      $entity = $storage->load($ids[$entity_type_id]);
      $this->assertTrue(empty($entity), "$entity_type_id was not loaded in a workspace it does not belongs to.");
      $entity = $this->entityManager->loadEntityByUuid($entity_type_id, $uuids[$entity_type_id]);
      $this->assertTrue(empty($entity), "$entity_type_id was not loaded by UUID in a workspace it does not belong to.");
    }

    // Test saving the same entity in two workspaces. This is a simplified
    // simulation of replication.
    $source = Workspace::create([
      'machine_name' => $this->randomMachineName(),
      'label' => $this->randomMachineName(),
      'type' => 'basic'
    ]);
    $source->save();
    $target = Workspace::create([
      'machine_name' => $this->randomMachineName(),
      'label' => $this->randomMachineName(),
      'type' => 'basic'
    ]);
    $target->save();

    // Save an initial set of entities on source.
    $this->workspaceManager->setActiveWorkspace($source);

    $entities = [];
    foreach ($this->entityTypes as $entity_type_id => $info) {
      $storage = $this->entityManager->getStorage($entity_type_id);
      $entity = $storage->create($info['info']);
      $entity->save();
      $entities[$entity_type_id][$entity->uuid()] = $entity;
    }

    // Save the same entities on target as new entities, but with the same UUID.
    $this->workspaceManager->setActiveWorkspace($target);
    foreach ($this->entityTypes as $entity_type_id => $info) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $source_entity */
      foreach ($entities[$entity_type_id] as $source_entity) {
        if ($source_entity->getEntityType()->get('workspace') !== FALSE) {
          $target_entity = clone $source_entity;

          // Reset the ID and assign the new workspace.
          $target_entity->{$info['id']}->value = NULL;

          $target_entity->enforceIsNew(TRUE);
          $target_entity->workspace->target_id = $target->id();

          // Save the new entity
          $target_entity->save();

          $this->assertTrue(!empty($target_entity->id()), "$entity_type_id in the target workspace got a new entity ID");
          $this->assertEqual($target_entity->uuid(), $source_entity->uuid(), "Entities from source and target share the same UUID");
          $this->assertNotEqual($target_entity->id(), $source_entity->id(), "Entities from source and target does not share the same local ID");
          $this->assertEqual($target_entity->workspace->entity->id(), $target->id(), "Entity in target workspace");
        }
      }
    }
  }

}
