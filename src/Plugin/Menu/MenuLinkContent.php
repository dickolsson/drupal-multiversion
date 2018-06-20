<?php

namespace Drupal\multiversion\Plugin\Menu;

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

}
