<?php

namespace Drupal\multiversion\Entity\Index;

use Drupal\Core\Entity\ContentEntityInterface;

interface SequenceIndexInterface extends IndexInterface {

  /**
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   */
  public function add(ContentEntityInterface $entity);

  /**
   * @param float $start
   * @param float $stop
   * @param boolean $inclusive
   *
   * @return array
   */
  public function getRange($start, $stop = NULL, $inclusive = TRUE);

  /**
   * @return float
   */
  public function getLastSequenceId();

}
