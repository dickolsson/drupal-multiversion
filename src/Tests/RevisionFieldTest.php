<?php

namespace Drupal\multiversion\Tests;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\multiversion\Plugin\Field\FieldType\RevisionItem;

/**
 * Test the creation and operation of the Revision field.
 *
 * @group multiversion
 */
class RevisionFieldTest extends FieldTestBase {

  /**
   * {@inheritdoc}
   */
  protected $fieldName = '_rev';

  /**
   * {@inheritdoc}
   */
  protected $createdEmpty = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $itemClass = '\Drupal\multiversion\Plugin\Field\FieldType\RevisionItem';

  public function testFieldOperations() {
    foreach ($this->entityTypes as $entity_type_id => $values) {
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $entity = $this->createTestEntity($storage, $values);

      // Test normal save operations.
      $this->assertTrue($entity->_rev->new_edit, 'New edit flag is TRUE after creation.');

      $revisions = $entity->_rev->revisions;
      $this->assertTrue((is_array($revisions) && empty($revisions)), 'Revisions property is empty after creation.');
      $this->assertTrue((strpos($entity->_rev->value, '0') === 0), 'Revision index was 0 after creation.');

      $entity->save();
      $first_rev = $entity->_rev->value;
      $this->assertTrue((strpos($first_rev, '1') === 0), 'Revision index was 1 after first save.');

      // Simulate the input from a replication.
      $entity = $this->createTestEntity($storage, $values);
      $sample_rev = RevisionItem::generateSampleValue($entity->_rev->getFieldDefinition());

      $entity->_rev->value = $sample_rev['value'];
      $entity->_rev->new_edit = FALSE;
      $entity->_rev->revisions = [$sample_rev['revisions'][0]];
      $entity->save();
      // Assert that the revision token did not change.
      $this->assertEqual($entity->_rev->value, $sample_rev['value']);

      // Test the is_stub property.
      $entity = $this->createTestEntity($storage, $values);
      $entity->save();
      $entity = $storage->load($entity->id());
      $this->assertIdentical(FALSE, $entity->_rev->is_stub, 'Entity saved normally is loaded as not stub.');

      $entity = $this->createTestEntity($storage, $values);
      $entity->_rev->is_stub = FALSE;
      $entity->save();
      $entity = $storage->load($entity->id());
      $this->assertIdentical(FALSE, $entity->_rev->is_stub, 'Entity saved explicitly as not stub is loaded as not stub.');

      $entity = $this->createTestEntity($storage, $values);
      $entity->_rev->is_stub = TRUE;
      $entity->save();
      $entity = $storage->load($entity->id());
      $this->assertIdentical(TRUE, $entity->_rev->is_stub, 'Entity saved explicitly as stub is loaded as stub.');
      $this->assertEqual($entity->_rev->value, '0-00000000000000000000000000000000', 'Entity has the revision ID of a stub.');
      $entity->_rev->is_stub = FALSE;
      $this->assertFalse($entity->_rev->is_stub, 'Setting an explicit value as not stub works after an entity has been saved.');

      // Test that a new entity with an existing _rev value get's it reset.
      $rev = '3-b6ae50eeb6b074a80647364f5b7cb6d6';
      $entity = $this->createTestEntity($storage, array_merge($values, ['_rev' => $rev]));
      $this->assertEqual($entity->_rev->value, $rev);
      $entity->save();
      $this->assertEqual(explode('-', $entity->_rev->value)[0], '1');
      $entity->setNewRevision(TRUE);
      $entity->save();
      $this->assertEqual(explode('-', $entity->_rev->value)[0], '2');
    }
  }

  /**
   * Create an entity to test the _rev field.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage to create an entity for.
   * @param array $values
   *   The values for the created entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The created entity.
   */
  protected function createTestEntity(EntityStorageInterface $storage, array $values) {
    switch ($storage->getEntityTypeId()) {
      case 'block_content':
        $values['info'] = $this->randomMachineName();
        break;
    }
    return $storage->create($values);
  }

}
