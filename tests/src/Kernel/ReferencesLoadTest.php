<?php

namespace Drupal\Tests\multiversion\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\multiversion\Entity\Workspace;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\SchemaCheckTestTrait;

/**
 * Test for paragraphs integration.
 *
 * @requires module paragraphs
 * @requires module entity_reference_revisions
 * @group multiversion
 */
class ReferencesLoadTest extends EntityKernelTestBase {

  use SchemaCheckTestTrait;
  use EntityReferenceTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = [
    'system',
    'field',
    'key_value',
    'user',
    'serialization',
    'paragraphs',
    'multiversion_test_paragraphs',
    'node',
    'multiversion',
    'entity_reference_revisions',
    'entity_test',
    'file',
    'entity_reference_test',
  ];

  /**
   * The entity type used in this test.
   *
   * @var string
   */
  protected $entityType = 'entity_test_rev';

  /**
   * The entity type that is being referenced.
   *
   * @var string
   */
  protected $referencedEntityType = 'entity_test_rev';

  /**
   * The name of the first field used in this test.
   *
   * @var string
   */
  protected $fieldName1 = 'field_test_1';

  /**
   * The name of the second field used in this test.
   *
   * @var string
   */
  protected $fieldName2 = 'field_test_2';

  /**
   * The bundle used in this test.
   *
   * @var string
   */
  protected $bundle = 'entity_test_rev';

  /**
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $paragraphStorage;

  /**
   * @var \Drupal\multiversion\EntityReferencesManagerInterface
   */
  protected $entityReferencesManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test_rev');
    $this->installConfig(['multiversion', 'multiversion_test_paragraphs']);
    $this->installEntitySchema('workspace');
    $this->installEntitySchema('entity_test_rev');
    $this->installSchema('node', 'node_access');
    $this->installSchema('key_value', 'key_value_sorted');
    $workspace = Workspace::create([
      'machine_name' => 'live',
      'label' => 'Live',
      'type' => 'basic',
    ]);
    $workspace->save();
    $multiversion_manager = $this->container->get('multiversion.manager');
    $multiversion_manager->enableEntityTypes();
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->paragraphStorage = $this->entityTypeManager->getStorage('paragraph');
    $this->entityReferencesManager = $this->container->get('multiversion.entity_references.manager');
    // Create the first field.
    $this->createEntityReferenceField(
      $this->entityType,
      $this->bundle,
      $this->fieldName1,
      'Field test 1',
      $this->referencedEntityType,
      'default',
      ['target_bundles' => [$this->bundle]],
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );
    // Create the second field.
    $this->createEntityReferenceField(
      $this->entityType,
      $this->bundle,
      $this->fieldName2,
      'Field test 2',
      $this->referencedEntityType,
      'default',
      ['target_bundles' => [$this->bundle]],
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );
  }

  public function testReferencesLoad() {
    // Create the parent entity.
    $entity = $this->entityTypeManager
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);

    // Create three target entities and attach them to the first parent field.
    $target_entities1 = [];
    $reference_field1 = [];
    for ($i = 0; $i < 3; $i++) {
      $target_entity = $this->entityTypeManager
        ->getStorage($this->referencedEntityType)
        ->create(['type' => $this->bundle]);
      $target_entity->save();
      $target_entities1[] = $target_entity;
      $reference_field1[]['target_id'] = $target_entity->id();
    }
    // Set the field value.
    $entity->{$this->fieldName1}->setValue($reference_field1);

    // Create four target entities and attach them to the second parent field.
    $target_entities2 = [];
    $reference_field2 = [];
    for ($i = 0; $i < 4; $i++) {
      $target_entity = $this->entityTypeManager
        ->getStorage($this->referencedEntityType)
        ->create(['type' => $this->bundle]);
      $target_entity->save();
      $target_entities2[] = $target_entity;
      $reference_field2[]['target_id'] = $target_entity->id();
    }
    // Set the field value.
    $entity->{$this->fieldName2}->setValue($reference_field2);
    $entity->save();

    foreach ($target_entities1 as $target) {
      $uuids[] = $target->uuid();
      $ids[$this->referencedEntityType][] = (int) $target->id();
    }
    foreach ($target_entities2 as $target) {
      $uuids[] = $target->uuid();
      $ids[$this->referencedEntityType][] = (int) $target->id();
    }

    $referenced_uuids = $this->entityReferencesManager->getReferencedEntitiesUuids($entity);
    $this->assertEquals($uuids, $referenced_uuids);
    $referenced_ids = $this->entityReferencesManager->getReferencedEntitiesIds($entity);
    $this->assertEquals($ids, $referenced_ids);
  }

  public function testParagraphReferencesLoad() {
    $paragraph1 = $this->paragraphStorage->create([
      'title' => 'Test paragraph',
      'type' => 'test_paragraph_type',
      'field_test_field' => 'First revision title',
    ]);
    $paragraph1->save();
    $paragraph2 = $this->paragraphStorage->create([
      'title' => 'Test paragraph',
      'type' => 'test_paragraph_type',
      'field_test_field' => 'First revision title',
    ]);
    $paragraph2->save();
    $node = $this->nodeStorage->create([
      'type' => 'paragraphs_node_type',
      'title' => 'Test node',
      'field_paragraph' => [
        $paragraph1,
        $paragraph2,
      ]
    ]);
    $node->save();

    $ids['paragraph'][] = (int) $paragraph1->id();
    $ids['paragraph'][] = (int) $paragraph2->id();
    $referenced_uuids = $this->entityReferencesManager->getReferencedEntitiesUuids($node);
    $this->assertEquals([$paragraph1->uuid(), $paragraph2->uuid()], $referenced_uuids);
    $referenced_ids = $this->entityReferencesManager->getReferencedEntitiesIds($node);
    $this->assertEquals($ids, $referenced_ids);
  }

  public function testParentsLoad() {
    // Create three target entities.
    $target_entities = [];
    $reference_field = [];
    for ($i = 0; $i < 3; $i++) {
      $target_entity = $this->entityTypeManager
        ->getStorage($this->referencedEntityType)
        ->create(['type' => $this->bundle]);
      $target_entity->save();
      $target_entities[] = $target_entity;
      $reference_field[]['target_id'] = $target_entity->id();
    }

    // Create the first parent entity and set all target entities as references.
    $entity1 = $this->entityTypeManager
      ->getStorage($this->entityType)
      ->create([
        'type' => $this->bundle,
        $this->fieldName1 => $reference_field
        ]);
    $entity1->save();

    unset($reference_field[0]);

    // Create the second parent entity and set only two target entities as
    // references.
    $entity2 = $this->entityTypeManager
      ->getStorage($this->entityType)
      ->create([
        'type' => $this->bundle,
        $this->fieldName1 => $reference_field
      ]);
    $entity2->save();

    unset($reference_field[1]);

    // Create the third parent entity and set only one target entity as
    // reference.
    $entity3 = $this->entityTypeManager
      ->getStorage($this->entityType)
      ->create([
        'type' => $this->bundle,
        $this->fieldName1 => $reference_field
      ]);
    $entity3->save();

    // First target entity should be referenced by one entity.
    $parent_uuids = $this->entityReferencesManager->getParentEntitiesUuids($target_entities[0]);
    $expected = [
      $entity1->uuid(),
    ];
    $this->assertEquals($expected, $parent_uuids);

    // Second target entity should be referenced by two entities.
    $parent_uuids = $this->entityReferencesManager->getParentEntitiesUuids($target_entities[1]);
    $expected = [
      $entity1->uuid(),
      $entity2->uuid(),
    ];
    $this->assertEquals($expected, $parent_uuids);

    // Third target entity should be referenced by three entities.
    $parent_uuids = $this->entityReferencesManager->getParentEntitiesUuids($target_entities[2]);
    $expected = [
      $entity1->uuid(),
      $entity2->uuid(),
      $entity3->uuid(),
    ];
    $this->assertEquals($expected, $parent_uuids);

    // First target entity should be referenced by one entity.
    $parent_ids = $this->entityReferencesManager->getParentEntitiesIds($target_entities[0]);
    $expected = [
      $this->entityType => [
        $entity1->id(),
      ],
    ];
    $this->assertEquals($expected, $parent_ids);

    // Second target entity should be referenced by two entities.
    $parent_ids = $this->entityReferencesManager->getParentEntitiesIds($target_entities[1]);
    $expected = [
      $this->entityType => [
        $entity1->id(),
        $entity2->id(),
      ],
    ];
    $this->assertEquals($expected, $parent_ids);

    // Third target entity should be referenced by three entities.
    $parent_ids = $this->entityReferencesManager->getParentEntitiesIds($target_entities[2]);
    $expected = [
      $this->entityType => [
        $entity1->id(),
        $entity2->id(),
        $entity3->id(),
      ],
    ];
    $this->assertEquals($expected, $parent_ids);
  }

  public function testParagraphParentsLoad() {
    $paragraph1 = $this->paragraphStorage->create([
      'title' => 'Test paragraph',
      'type' => 'test_paragraph_type',
      'field_test_field' => 'First revision title',
    ]);
    $paragraph1->save();
    $paragraph2 = $this->paragraphStorage->create([
      'title' => 'Test paragraph',
      'type' => 'test_paragraph_type',
      'field_test_field' => 'First revision title',
    ]);
    $paragraph2->save();

    $node1 = $this->nodeStorage->create([
      'type' => 'paragraphs_node_type',
      'title' => 'Test node',
      'field_paragraph' => [
        $paragraph1,
        $paragraph2,
      ]
    ]);
    $node1->save();

    $node2 = $this->nodeStorage->create([
      'type' => 'paragraphs_node_type',
      'title' => 'Test node',
      'field_paragraph' => [
        $paragraph1,
      ]
    ]);
    $node2->save();

    $node3 = $this->nodeStorage->create([
      'type' => 'paragraphs_node_type',
      'title' => 'Test node',
      'field_paragraph' => [
        $paragraph2,
      ]
    ]);
    $node3->save();

    $node4 = $this->nodeStorage->create([
      'type' => 'paragraphs_node_type',
      'title' => 'Test node',
      'field_paragraph' => [
        $paragraph1,
        $paragraph2,
      ]
    ]);
    $node4->save();

    $node5 = $this->nodeStorage->create([
      'type' => 'paragraphs_node_type',
      'title' => 'Test node',
      'field_paragraph' => [
        $paragraph1,
      ]
    ]);
    $node5->save();

    // First target paragraph should be referenced by four entities:
    // node1, node2 and node4, node5.
    $parent_uuids = $this->entityReferencesManager->getParentEntitiesUuids($paragraph1);
    $expected = [
      $node1->uuid(),
      $node2->uuid(),
      $node4->uuid(),
      $node5->uuid(),
    ];
    $this->assertEquals($expected, $parent_uuids);

    // Second target paragraph should be referenced by three entities:
    // node1, node2 and node4.
    $parent_uuids = $this->entityReferencesManager->getParentEntitiesUuids($paragraph2);
    $expected = [
      $node1->uuid(),
      $node3->uuid(),
      $node4->uuid(),
    ];
    $this->assertEquals($expected, $parent_uuids);

    // First target paragraph should be referenced by four entities.
    $parent_ids = $this->entityReferencesManager->getParentEntitiesIds($paragraph1);
    $expected = [
      'node' => [
        $node1->id(),
        $node2->id(),
        $node4->id(),
        $node5->id(),
      ],
    ];
    $this->assertEquals($expected, $parent_ids);

    // Second target paragraph should be referenced by three entities.
    $parent_ids = $this->entityReferencesManager->getParentEntitiesIds($paragraph2);
    $expected = [
      'node' => [
        $node1->id(),
        $node3->id(),
        $node4->id(),
      ],
    ];
    $this->assertEquals($expected, $parent_ids);
  }

}
