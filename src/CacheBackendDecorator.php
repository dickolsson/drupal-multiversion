<?php

namespace Drupal\multiversion;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;

class CacheBackendDecorator implements CacheBackendInterface {

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $decorated;

  /**
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * Constructor
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $decorated
   * @param \Drupal\multiversion\Workspace\WorkspaceManagerInterface $workspace_manager
   */
  public function __construct(CacheBackendInterface $decorated, WorkspaceManagerInterface $workspace_manager) {
    $this->decorated = $decorated;
    $this->workspaceManager = $workspace_manager;
  }

  /**
   * Helper method to decorate a cache ID.
   *
   * @param string $cid
   * @return string
   */
  protected function decorate($cid) {
    return "$cid:" . $this->workspaceManager->getActiveWorkspaceId();
  }

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {
    $cid = $this->decorate($cid);
    return $this->decorated->get($cid, $allow_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    foreach ($cids as &$cid) {
      $cid = $this->decorate($cid);
    }
    return $this->decorated->getMultiple($cids, $allow_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = []) {
    $cid = $this->decorate($cid);
    return $this->decorated->set($cid, $data, $expire, $tags);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items) {
    $decorated_items = [];
    foreach ($items as $cid => $item) {
      $decorated_items[$this->decorate($cid)] = $item;
      // Save some memory.
      unset($items[$cid]);
    }
    return $this->setMultiple($decorated_items);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid) {
    $cid = $this->decorate($cid);
    return $this->delete($cid);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids) {
    foreach ($cids as &$cid) {
      $cid = $this->decorate($cid);
    }
    return $this->decorated->deleteMultiple($cids);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    return $this->decorated->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($cid) {
    $cid = $this->decorate($cid);
    return $this->decorated->invalidate($cid);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids) {
    foreach ($cids as &$cid) {
      $cid = $this->decorate($cid);
    }
    return $this->decorated->invalidateMultiple($cids);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateAll() {
    return $this->decorated->invalidateAll();
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    return $this->decorated->garbageCollection();
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin() {
    return $this->decorated->removeBin();
  }

}
