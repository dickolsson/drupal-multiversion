<?php

namespace Drupal\multiversion;

use Drupal\Core\Entity\EntityInterface;

interface EntityReferencesManagerInterface {

  /**
   * Returns all referenced entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return array
   */
  public function getMultiversionableReferencedEntities(EntityInterface $entity);

  /**
   * Returns UUIDs of all the referenced entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return array
   */
  public function getReferencedEntitiesUuids(EntityInterface $entity);

  /**
   * Returns IDs of all the referenced entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return array
   */
  public function getReferencedEntitiesIds(EntityInterface $entity);

  /**
   * Returns all entities that reference to the specified entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return mixed
   */
  public function getParentEntities(EntityInterface $entity);

  /**
   * Returns UUIDs of all the entities that reference to the specified entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return mixed
   */
  public function getParentEntitiesUuids(EntityInterface $entity);

  /**
   * Returns IDs of all the entities that reference to the specified entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return mixed
   */
  public function getParentEntitiesIds(EntityInterface $entity);

}
