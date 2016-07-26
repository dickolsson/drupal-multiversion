<?php

namespace Drupal\multiversion\Entity\Storage\Sql;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Entity\EntityInterface;
use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;
use Drupal\multiversion\Entity\Storage\ContentEntityStorageTrait;

class ParagraphsStorage extends SqlContentEntityStorage implements ContentEntityStorageInterface {

  use ContentEntityStorageTrait {
    save as saveEntity;
  }

  public function save(EntityInterface $entity) {
    $result = $this->saveEntity($entity);
    $this->updateRevIds($entity);

    return $result;
  }

  // change revision id the parent entity links to
  private function updateRevIds($entity) {
    // test if entity has parent
    if($parent_entity = $entity->getParentEntity()){
      $parent_entity_id = $parent_entity->id();
      $parent_entity_rev_id = $parent_entity->getRevisionId();
      $parent_entity_type = $parent_entity->getEntityTypeId();
      $parent_entity_field_name = $entity->parent_field_name->value;

      $entity_id = $entity->id();
      $entity_rev_id = $entity->getRevisionId();

      if(!$parent_entity_id   ||
        !$parent_entity_type  ||
        !$parent_entity_field_name   ||
        !$entity_id           ||
        !$entity_rev_id) return NULL;

      // set newest revision id in parent field table
      $db = \Drupal::database();

      // address normal and revision tables
      foreach (['', '_revision'] as $table) {
        $query = $db
          ->update($parent_entity_type . $table . '__' . $parent_entity_field_name)
          ->fields(["{$parent_entity_field_name}_target_revision_id" => $entity_rev_id])
          ->condition("{$parent_entity_field_name}_target_id", $entity_id);

        if($table === '_revision') {
          $query
            ->condition('entity_id', $parent_entity_id)
            ->condition('revision_id', $parent_entity_rev_id);
        }

        $query->execute();
      }

      \Drupal::service('cache_tags.invalidator')->invalidateTags((array) $parent_entity->getCacheTagsToInvalidate());
    }
  }
}
