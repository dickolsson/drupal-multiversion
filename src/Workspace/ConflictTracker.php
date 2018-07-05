<?php

namespace Drupal\multiversion\Workspace;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;

/**
 * The Conflict Tracker service.
 */
class ConflictTracker implements ConflictTrackerInterface {

  /**
   * The id of current workspace to query.
   *
   * @var string
   */
  protected $workspaceId;

  /**
   * The workspace manager service.
   *
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * The key value factory service.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValueFactory;

  /**
   * The prefix to use for workspace conflicts.
   *
   * @var string
   */
  protected $collectionPrefix = 'workspace.conflicts.';


  /**
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   * @param \Drupal\multiversion\Workspace\WorkspaceManagerInterface $workspace_manager
   */
  public function __construct(KeyValueFactoryInterface $key_value_factory, WorkspaceManagerInterface $workspace_manager) {
    $this->keyValueFactory = $key_value_factory;
    $this->workspaceManager = $workspace_manager;
    // Set workspace to currently active workspace.
    $this->workspaceId = $this->workspaceManager->getActiveWorkspaceId();
  }

  /**
   * {@inheritdoc}
   */
  public function useWorkspace(WorkspaceInterface $workspace = null) {
    $this->workspaceId = 0;
    if ($workspace) {
      $this->workspaceId = $workspace->id();
    }
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function add($uuid, array $revision_conflicts, $replace = FALSE) {
    if (!$replace) {
      $current_conflicts = $this->keyValueStore()->get($uuid);
      $revision_conflicts = array_merge($current_conflicts, $revision_conflicts);
    }
    $this->keyValueStore()->set($uuid, $revision_conflicts);
  }

  /**
   * @inheritDoc
   */
  public function resolveAll($uuid) {
    $this->keyValueStore()->delete($uuid);
  }

  /**
   * @inheritDoc
   */
  public function resolve($uuid, $revision_id) {
    $conflicts = $this->keyValueStore()->get($uuid);
    unset($conflicts[$revision_id]);
    $this->keyValueStore()->set($uuid, $conflicts);
  }

  /**
   * @inheritDoc
   */
  public function get($uuid) {
    return $this->keyValueStore()->get($uuid, []);
  }

  /**
   * @inheritDoc
   */
  public function getAll() {
    return $this->keyValueStore()->getAll();
  }

  /**
   * Gets the key value store used to store conflicts.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected function keyValueStore() {
    return $this->keyValueFactory->get($this->collectionPrefix . $this->workspaceId);
  }

}
