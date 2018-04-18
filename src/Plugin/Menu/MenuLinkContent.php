<?php

namespace Drupal\multiversion\Plugin\Menu;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent as CoreMenuLinkContent;

class MenuLinkContent extends CoreMenuLinkContent {

  /**
   * {@inheritdoc}
   */
  public function getBaseId() {
    $plugin_id = $this->getPluginId();
    if (strpos($plugin_id, static::DERIVATIVE_SEPARATOR)) {
      list($plugin_id) = explode(static::DERIVATIVE_SEPARATOR, $plugin_id, 3);
    }
    return $plugin_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeId() {
    $plugin_id = $this->getPluginId();
    $derivative_id = NULL;
    if (strpos($plugin_id, static::DERIVATIVE_SEPARATOR)) {
      list(, $derivative_id,) = explode(static::DERIVATIVE_SEPARATOR, $plugin_id, 3);
    }
    return $derivative_id;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntity() {
    if (empty($this->entity)) {
      $entity = NULL;
      $storage = $this->entityManager->getStorage('menu_link_content');
      if (!empty($this->pluginDefinition['metadata']['entity_id'])) {
        $entity_id = $this->pluginDefinition['metadata']['entity_id'];
        // Make sure the current ID is in the list, since each plugin empties
        // the list after calling loadMultiple(). Note that the list may include
        // multiple IDs added earlier in each plugin's constructor.
        static::$entityIdsToLoad[$entity_id] = $entity_id;
        $entities = $storage->loadMultiple(array_values(static::$entityIdsToLoad));
        $entity = isset($entities[$entity_id]) ? $entities[$entity_id] : NULL;
        static::$entityIdsToLoad = [];
      }
      if (!$entity) {
        // Fallback to the loading by the UUID.
        $uuid = $this->getUuid();
        $loaded_entities = $storage->loadByProperties(['uuid' => $uuid]);
        $entity = reset($loaded_entities);
      }
      if (!$entity) {
        throw new PluginException("Entity not found through the menu link plugin definition and could not fallback on UUID '$uuid'");
      }
      // Clone the entity object to avoid tampering with the static cache.
      $this->entity = clone $entity;
      $the_entity = $this->entityManager->getTranslationFromContext($this->entity);
      /** @var \Drupal\menu_link_content\MenuLinkContentInterface $the_entity */
      $this->entity = $the_entity;
      $this->entity->setInsidePlugin();
    }
    return $this->entity;
  }

}
