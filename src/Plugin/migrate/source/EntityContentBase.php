<?php

namespace Drupal\multiversion\Plugin\migrate\source;

use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;

/**
 * Migration source class for content entities.
 *
 * @MigrateSource(
 *   id = "multiversion"
 * )
 */
class EntityContentBase extends SourcePluginBase {

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    // At this point Multiversion is obviously installed and the new storage
    // handler is already active. But since the new schema isn't applied yet
    // and the new handler doesn't know how to load from the old schema, we have
    // to initialize the previously installed storage handler and use that to
    // load the entities.
    $last_definition = $this->entityManager->getLastInstalledDefinition($this->entityTypeId);
    $storage_class = $last_definition->getStorageClass();
    $last_storage = $this->entityManager->createHandlerInstance($storage_class, $last_definition);
    $entities = $last_storage->loadMultiple();

    $results = [];
    foreach ($entities as $entity_id => $entity) {
      if (isset($entity->_deleted->value) && $entity->_deleted->value) {
        continue;
      }
      foreach($entity->getTranslationLanguages(TRUE) as $language) {
        $result = [];
        foreach ($entity->getTranslation($language->getId()) as $field_name => $field) {
          if (!$field->isEmpty()) {
            /** @var \Drupal\Core\Field\FieldItemListInterface $field */
            $value = $field->getValue();
            // If there is only one value in the field, unwrap it.
            if (count($value) == 1) {
              $value = reset($value);
              // If there's only one property in the field value, unwrap it.
              if (count($value) == 1 && empty($value['langcode'])) {
                $value = reset($value);
              }
            }
            // Set the 'migrate://' scheme for files.
            if ($this->entityTypeId == 'file' && $field_name == 'uri') {
              $target = file_uri_target($value);
              $value = 'migrate://' . $target;
            }
            $result[$field_name] = $value;
          }
        }
        $results[] = $result;
      }
    }

    return new \ArrayIterator(array_values($results));
  }

}
