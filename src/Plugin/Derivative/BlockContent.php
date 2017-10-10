<?php

namespace Drupal\multiversion\Plugin\Derivative;

use Drupal\block_content\Plugin\Derivative\BlockContent as CoreBlockContent;

class BlockContent extends CoreBlockContent {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $block_contents = $this->blockContentStorage->loadMultiple();
    // Reset the discovered definitions.
    $this->derivatives = [];
    $workspace_id = multiversion_get_active_workspace_id();
    /** @var $block_content \Drupal\block_content\Entity\BlockContent */
    foreach ($block_contents as $block_content) {
      $this->derivatives[$block_content->uuid() . ':ws' . $workspace_id] = $base_plugin_definition;
      $this->derivatives[$block_content->uuid() . ':ws' . $workspace_id]['admin_label'] = $block_content->label();
      $this->derivatives[$block_content->uuid() . ':ws' . $workspace_id]['config_dependencies']['content'] = array(
        $block_content->getConfigDependencyName()
      );
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
