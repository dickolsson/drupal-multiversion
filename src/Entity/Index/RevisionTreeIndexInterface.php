<?php

namespace Drupal\multiversion\Entity\Index;

interface RevisionTreeIndexInterface extends IndexInterface {

  /**
   * @param string $uuid
   *
   * @return array
   */
  public function getTree($uuid);

  /**
   * @param array $branch
   */
  public function updateTree($uuid, array $branch = array());

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
