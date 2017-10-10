<?php

namespace Drupal\multiversion\Plugin\Discovery;

use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator as CoreContainerDerivativeDiscoveryDecorator;

class ContainerDerivativeDiscoveryDecorator extends CoreContainerDerivativeDiscoveryDecorator {

  /**
   * {@inheritdoc}
   */
  protected function decodePluginId($plugin_id) {
    // Try and split the passed plugin definition into a plugin and a
    // derivative id. We don't need to check for !== FALSE because a leading
    // colon would break the derivative system and doesn't makes sense.
    if (strpos($plugin_id, ':')) {
      return explode(':', $plugin_id, 3);
    }

    return array($plugin_id, NULL);
  }

  /**
   * {@inheritdoc}
   */
  protected function encodePluginId($base_plugin_id, $derivative_id) {
    $ws = multiversion_get_active_workspace_id();
    if ($base_plugin_id === 'block_content' && $derivative_id) {
      return "$base_plugin_id:$derivative_id:ws$ws";
    }
    elseif ($derivative_id) {
      return "$base_plugin_id:$derivative_id";
    }

    // By returning the unmerged plugin_id, we are able to support derivative
    // plugins that support fetching the base definitions.
    return $base_plugin_id;
  }

}
