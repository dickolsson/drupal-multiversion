<?php

namespace Drupal\multiversion\Entity\Index;

use Drupal\Core\Entity\ContentEntityInterface;

interface RevisionTreeIndexInterface extends IndexInterface {

  /**
   * @param string $uuid
   *
   * @return array
   */
  public function getTree($uuid);

  /**
   * @param string $uuid
   *
   * @return object of graph type 
   */
  public function getGraph($uuid);

  /**
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   * @param array $branch
   * @return RevisionTreeIndexInterface
   */
  public function updateTree(ContentEntityInterface $entity, array $branch = array());

  /**
   * @param string $uuid
   *
   * @return string
   */
  public function getDefaultRevision($uuid);

  /**
   * @param string $uuid
   *
   * @return string[]
   */
  public function getDefaultBranch($uuid);

  /**
   * @param string $uuid
   *
   * @return string[]
   */
  public function getOpenRevisions($uuid);

  /**
   * @param string $uuid
   *
   * @return string[]
   */
  public function getConflicts($uuid);

  /**
   * @param array $a
   * @param array $b
   * @return integer
   */
  public static function sortRevisions(array $a, array $b);

  /**
   * @param array $tree
   * @return mixed
   */
  public static function sortTree(array &$tree);

}
