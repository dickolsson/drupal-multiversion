<?php

namespace Drupal\multiversion;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;

/**
 * Multiversion entity references manager.
 *
 * @package Drupal\multiversion
 */
class EntityReferencesManager implements EntityReferencesManagerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The multiversion manager.
   *
   * @var \Drupal\multiversion\MultiversionManagerInterface
   */
  protected $multiversionManager;

  /**
   * EntityReferencesManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\multiversion\MultiversionManagerInterface $multiversion_manager
   *   The multiversion manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MultiversionManagerInterface $multiversion_manager, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->entityTypeManager = $entity_type_manager;
    $this->multiversionManager = $multiversion_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiversionableReferencedEntities(EntityInterface $entity) {
    $references = $entity->referencedEntities();
    foreach ($references as $key => $reference) {
      if (!($reference instanceof ContentEntityInterface)
        || !$this->multiversionManager->isEnabledEntityType($reference->getEntityType())) {
        unset($references[$key]);
      }
    }
    return $references;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencedEntitiesUuids(EntityInterface $entity) {
    $referenced_uuids = [];
    foreach ($this->getMultiversionableReferencedEntities($entity) as $reference) {
      $referenced_uuids[] = $reference->uuid();
    }
    return $referenced_uuids;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencedEntitiesIds(EntityInterface $entity) {
    $referenced_ids = [];
    foreach ($this->getMultiversionableReferencedEntities($entity) as $reference) {
      $referenced_ids[$reference->getEntityTypeId()][] = (int) $reference->id();
    }
    return $referenced_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEntities(EntityInterface $entity) {
    $entity_id = $entity->id();
    $entity_type = $entity->getEntityType();
    $entity_type_id = $entity_type->id();
    $parents = [];
    $definitions = $this->entityTypeManager->getDefinitions();
    foreach ($definitions as $definition_id => $definition) {
      if (!is_subclass_of($definition->getStorageClass(), ContentEntityStorageInterface::class)) {
        continue;
      }
      $storage = $this->entityTypeManager->getStorage($definition_id);
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($definition_id);
      foreach ($bundles as $bundle => $info) {
        $field_definitions = $this->entityFieldManager->getFieldDefinitions($definition_id, $bundle);
        foreach ($field_definitions as $field_definition) {
          if ($field_definition->getType() === 'entity_reference'
            || $field_definition->getType() === 'entity_reference_revisions') {
            if ($field_definition->getSetting('target_type') != $entity_type_id) {
              continue;
            }

            // Possible match.
            $matches = $storage->getQuery()
              ->condition('type', $bundle)
              ->condition($field_definition->getName(), $entity_id, 'IN')
              ->execute();

            if (empty($matches)) {
              continue;
            }

            if (isset($parents[$definition_id])) {
              $parents += $storage->loadMultiple(array_values($matches));
            }
            else {
              $parents = $storage->loadMultiple(array_values($matches));
            }
          }
        }
      }
    }
    return $parents;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEntitiesUuids(EntityInterface $entity) {
    $parent_uuids = [];
    foreach ($this->getParentEntities($entity) as $parent) {
      $parent_uuids[] = $parent->uuid();
    }
    return $parent_uuids;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEntitiesIds(EntityInterface $entity) {
    $parent_ids = [];
    foreach ($this->getParentEntities($entity) as $parent) {
      $parent_ids[$parent->getEntityTypeId()][] = (int) $parent->id();
    }
    return $parent_ids;
  }

}
