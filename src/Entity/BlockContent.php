<?php

namespace Drupal\multiversion\Entity;

use Drupal\block_content\Entity\BlockContent as CoreBlockContent;

class BlockContent extends CoreBlockContent {

  /**
   * {@inheritdoc}
   */
  public function getInstances() {
    return \Drupal::entityTypeManager()
      ->getStorage('block')
      ->loadByProperties(['plugin' => 'block_content:' . $this->uuid() . ':ws' . multiversion_get_active_workspace_id()]);
  }

}
