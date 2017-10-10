<?php

namespace Drupal\multiversion;

use Drupal\Core\Block\BlockManager as CoreBlockManager;
use Drupal\Core\Plugin\Discovery\AnnotatedClassDiscovery;
use Drupal\multiversion\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;

class BlockManager extends CoreBlockManager {

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!$this->discovery) {
      $discovery = new AnnotatedClassDiscovery($this->subdir, $this->namespaces, $this->pluginDefinitionAnnotationName, $this->additionalAnnotationNamespaces);
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($discovery);
    }
    return $this->discovery;
  }

}
