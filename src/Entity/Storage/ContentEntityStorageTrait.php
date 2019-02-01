<?php

namespace Drupal\multiversion\Entity\Storage;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\file\FileInterface;
use Drupal\path\Plugin\Field\FieldType\PathFieldItemList;
use Drupal\pathauto\PathautoState;
use Drupal\user\UserStorageInterface;

trait ContentEntityStorageTrait {

  /**
   * @var boolean
   */
  protected $isDeleted = FALSE;

  /**
   * @var int
   */
  protected $workspaceId = NULL;

  /**
   * @var  \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $originalStorage;

  /**
   * {@inheritdoc}
   */
  public function getQueryServiceName() {
    return 'multiversion.entity.query.sql';
  }

  /**
   * Get original entity type storage handler (not the multiversion one).
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   Original entity type storage handler.
   */
  public function getOriginalStorage() {
    if ($this->originalStorage == NULL) {
      $this->originalStorage = $this->entityManager->getHandler($this->entityTypeId, 'original_storage');
    }
    return $this->originalStorage;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildQuery($ids, $revision_ids = FALSE) {
    $query = parent::buildQuery($ids, $revision_ids);
    $enabled = \Drupal::state()->get('multiversion.migration_done.' . $this->getEntityTypeId(), FALSE);

    // Prevent to modify the query before entity type updates.
    if (!is_subclass_of($this->entityType->getStorageClass(), ContentEntityStorageInterface::class) || !$enabled) {
      return $query;
    }

    $field_data_alias = 'base';
    $revision_data_alias = 'revision';
    if ($this->entityType->isTranslatable()) {
      // Join the field data table in order to set the workspace condition.
      $field_data_table = $this->getDataTable();
      $field_data_alias = 'field_data';
      $query->join($field_data_table, $field_data_alias, "$field_data_alias.{$this->idKey} = base.{$this->idKey}");

      // Join the revision data table in order to set the delete condition.
      $revision_data_table = $this->getRevisionDataTable();
      $revision_data_alias = 'revision_data';
      if ($revision_ids) {
        $query->join($revision_data_table, $revision_data_alias, "$revision_data_alias.{$this->revisionKey} = revision.{$this->revisionKey} AND $revision_data_alias.{$this->revisionKey} IN (:revisionIds[])", [':revisionIds[]' => (array) $revision_ids]);
      }
      else {
        $query->join($revision_data_table, $revision_data_alias, "$revision_data_alias.{$this->revisionKey} = revision.{$this->revisionKey}");
      }
    }
    // Loading a revision is explicit. So when we try to load one we should do
    // so without a condition on the deleted flag.
    if (!$revision_ids) {
      $query->condition("$revision_data_alias._deleted", (int) $this->isDeleted);
    }
    // Entities in other workspaces than the active one can only be queried with
    // the Entity Query API and not by the storage handler itself.
    // Just UserStorage can be queried in all workspaces by the storage handler.
    if (!$this instanceof UserStorageInterface) {
      // We have to join the data table to set a condition on the workspace.
      $query->condition("$field_data_alias.workspace", $this->getWorkspaceId());
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function useWorkspace($id) {
    $this->workspaceId = $id;
    return $this;
  }

  /**
   * Helper method to get the workspace ID to query.
   */
  protected function getWorkspaceId() {
    return $this->workspaceId ?: \Drupal::service('workspace.manager')->getActiveWorkspaceId();
  }

  /**
   * {@inheritdoc}
   */
  public function loadUnchanged($id) {
    $this->resetCache([$id]);
    return $this->load($id) ?: $this->loadDeleted($id);
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL) {
    $this->isDeleted = FALSE;
    return parent::loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByProperties(array $values = []) {
    // Build a query to fetch the entity IDs.
    $entity_query = $this->getQuery();
    $entity_query->useWorkspace($this->getWorkspaceId());
    $entity_query->accessCheck(FALSE);
    $this->buildPropertyQuery($entity_query, $values);
    $result = $entity_query->execute();
    return $result ? $this->loadMultiple($result) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function loadDeleted($id) {
    $entities = $this->loadMultipleDeleted([$id]);
    return isset($entities[$id]) ? $entities[$id] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultipleDeleted(array $ids = NULL) {
    $this->isDeleted = TRUE;
    return parent::loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function saveWithoutForcingNewRevision(EntityInterface $entity) {
    $this->getOriginalStorage()->save($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function save(EntityInterface $entity) {
    // When importing with default content we want to it to be treated like a
    // replicate, and not as a new edit.
    if (isset($entity->default_content)) {
      list(, $hash) = explode('-', $entity->_rev->value);
      $entity->_rev->revisions = [$hash];
      $entity->_rev->new_edit = FALSE;
    }

    // Every update is a new revision with this storage model.
    $entity->setNewRevision();

    // Index the revision.
    $branch = $this->buildRevisionBranch($entity);
    $local = (boolean) $this->entityType->get('local');
    if (!$local) {
      $this->indexEntityRevision($entity);
      $this->indexEntityRevisionTree($entity, $branch);
    }

    // Prepare the file directory.
    if ($entity instanceof FileInterface) {
      multiversion_prepare_file_destination($entity->getFileUri());
    }

    // We prohibit creation of the url alias for entities with a random label,
    // because this can lead to unnecessary redirects.
    if ($entity->_rev->is_stub && isset($entity->path->pathauto)) {
      $entity->path->pathauto = PathautoState::SKIP;
    }

    foreach ($entity->getFields() as $name => $field) {
      if (
        $field instanceof EntityReferenceFieldItemListInterface &&
        !($field instanceof EntityReferenceRevisionsFieldItemList)
      ) {
        $value = [];

        // For the entity reference field with stub entity referenced we check
        // if the entity with corresponding UUID and real values
        // have been created in the database already and use it instead.
        foreach ($field->getValue() as $delta => $item) {
          // At first we take value we receive as it is.
          $value[$delta] = $item;

          // Only stub entities will satisfy this condition.
          if (
            $item['target_id'] === NULL &&
            isset($item['entity']) &&
            $item['entity']->_rev->is_stub
          ) {
            // Lookup for entities with corresponding UUID.
            $target_entities = $this->loadByProperties(['uuid' => $item["entity"]->uuid()]);

            // Replace stub with existing entity if we found such.
            if (!empty($target_entities)) {
              // Here we take first assuming there should be no entities
              // with duplicated UUIDs in one workspace.
              $target_entity = reset($target_entities);
              $item['target_id'] = $target_entity->id();
              unset($item['entity']);
              $value[$delta] = $item;
            }
          }
        }

        // @todo This conditions is not obligatory but will prevent
        // unnecessary action when field value already empty.
        if (!empty($value)) {
          $field->setValue($value, FALSE);
        }
      }
    }

    try {
      $save_result = parent::save($entity);

      // Update indexes.
      $this->indexEntity($entity);
      if (!$local) {
        $this->indexEntitySequence($entity);
        $this->indexEntityRevision($entity);
        $this->trackConflicts($entity);
      }

      return $save_result;
    }
    catch (\Exception $e) {
      // If a new attempt at saving the entity is made after an exception its
      // important that a new rev token is not generated.
      $entity->_rev->new_edit = FALSE;
      throw new EntityStorageException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doPreSave(EntityInterface $entity) {
    if (!$entity->isNew() && !isset($entity->original)) {
      $entity->original = $this->loadUnchanged($entity->originalId ?: $entity->id());
    }

    // This is a workaround for the cases when referenced poll choices are stub
    // entities (during replication). It will avoid deleting poll choice
    // entities on target workspace in Drupal\poll\Entity\Poll::preSave() when
    // not necessary.
    // @todo Find a better way to handle this.
    if (!$entity->isNew() && $this->entityTypeId === 'poll' && isset($entity->original) && $entity->_deleted->value == FALSE) {
      $original_choices = [];
      foreach ($entity->original->choice as $choice_item) {
        $original_choices[] = $choice_item->target_id;
      }

      $current_choices = [];
      $current_choices_entities = [];
      foreach ($entity->choice as $key => $choice_item) {
        $current_choices[$key] = $choice_item->target_id;
        $current_choices_entities[$key] = $choice_item->entity;
      }

      foreach ($current_choices as $key => $id) {
        if ($id === NULL
          && isset($current_choices_entities[$key]->_rev->is_stub)
          && $current_choices_entities[$key]->_rev->is_stub == TRUE
          && isset($entity->original->choice)) {
          unset($entity->original->choice);
        }
      }
    }

    parent::doPreSave($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function doPostSave(EntityInterface $entity, $update) {
    parent::doPostSave($entity, $update);
    // Set the originalId to allow entity renaming.
    $entity->originalId = $entity->id();

    // Delete path alias value if there is one.
    if ($entity->_deleted->value == TRUE && isset($entity->path) && $entity->path instanceof PathFieldItemList) {
      $entity->path->delete();
    }
  }

  /**
   * Indexes basic information about the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  protected function indexEntity(EntityInterface $entity) {
    $workspace = isset($entity->workspace) ? $entity->workspace->entity : null;
    $index_factory = \Drupal::service('multiversion.entity_index.factory');

    $index_factory->get('multiversion.entity_index.id', $workspace)
      ->add($entity);

    $index_factory->get('multiversion.entity_index.uuid', $workspace)
      ->add($entity);
  }

  /**
   * Indexes entity sequence.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  protected function indexEntitySequence(EntityInterface $entity) {
    $workspace = isset($entity->workspace) ? $entity->workspace->entity : null;
    \Drupal::service('multiversion.entity_index.factory')
      ->get('multiversion.entity_index.sequence', $workspace)
      ->add($entity);
  }

  /**
   * Indexes information about the revision.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  protected function indexEntityRevision(EntityInterface $entity) {
    $workspace = isset($entity->workspace) ? $entity->workspace->entity : null;
    \Drupal::service('multiversion.entity_index.factory')
      ->get('multiversion.entity_index.rev', $workspace)
      ->add($entity);
  }

  /**
   * Indexes the revision tree.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param array $branch
   */
  protected function indexEntityRevisionTree(EntityInterface $entity, $branch) {
    $workspace = isset($entity->workspace) ? $entity->workspace->entity : null;
    \Drupal::service('multiversion.entity_index.factory')
      ->get('multiversion.entity_index.rev.tree', $workspace)
      ->updateTree($entity, $branch);
  }

  /**
   * Builds the revision branch.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @return array
   */
  protected function buildRevisionBranch(EntityInterface $entity) {
    // We are going to index the revision ahead of save in order to accurately
    // determine if this is going to be the default revision or not. We also run
    // this logic here outside of any transactions that the parent storage
    // handler might perform. It's important that the revision index does not
    // get rolled back during exceptions. All records are kept in order to more
    // accurately build revision trees of all universally known revisions.
    $branch = [];
    $rev = $entity->_rev->value;
    $revisions = $entity->_rev->revisions;
    list($i) = explode('-', $rev);
    $count_revisions = count($revisions);
    $parent_rev = $rev;
    if ($count_revisions > $i && $entity->isNew()) {
      $i = $count_revisions + 1;
    }
    // When reverting revisions.
    elseif (!empty($entity->is_reverting)) {
      $i = $count_revisions;
      $parent_rev = !empty($revisions[0]) ? $i . '-' . $revisions[0] : $rev;
    }

    // This is a regular local save operation and a new revision token should be
    // generated. The new_edit property will be set to FALSE during replication
    // to ensure the revision token is saved as-is.
    if ($entity->_rev->new_edit || $entity->_rev->is_stub) {
      // If this is the first revision it means that there's no parent.
      // By definition the existing revision value is the parent revision.
      $parent_rev = $i == 0 ? 0 : $parent_rev;
      // Only generate a new revision if this is not a stub entity. This will
      // ensure that stub entities remain with the default value (0) to make it
      // clear on a storage level that this is a stub and not a "real" revision.
      if (!$entity->_rev->is_stub) {
        $rev = \Drupal::service('multiversion.manager')->newRevisionId(
          $entity, $i
        );
      }
      list(, $hash) = explode('-', $rev);
      $entity->_rev->value = $rev;
      $entity->_rev->revisions = [$hash];
      $branch[$rev] = [$parent_rev];

      // Add the parent revision to list of known revisions. This will be useful
      // if an exception is thrown during entity save and a new attempt is made.
      if ($parent_rev != 0) {
        list(, $parent_hash) = explode('-', $parent_rev);
        $entity->_rev->revisions = [$hash, $parent_hash];
      }
    }
    // A list of all known revisions can be passed in to let the current host
    // know about the revision history, for conflict handling etc. A list of
    // revisions are always passed in during replication.
    else {
      for ($c = 0; $c < count($revisions); ++$c) {
        $p = $c + 1;
        $rev = $i-- . '-' . $revisions[$c];
        $parent_rev = isset($revisions[$p]) ? $i . '-' . $revisions[$p] : 0;
        $branch[$rev] = [$parent_rev];
      }
    }
    return $branch;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Revisit this logic with forward revisions in mind.
   */
  protected function doSave($id, EntityInterface $entity) {
    if ($entity->_rev->is_stub || $this->entityType->get('local')
      || (!empty($entity->original) && $entity->original->_rev->is_stub)) {
      $entity->isDefaultRevision(TRUE);
    }
    else {
      // Enforce new revision if any module messed with it in a hook.
      $entity->setNewRevision();

      // Decide whether or not this is the default revision.
      if (!$entity->isNew()) {
        $workspace = isset($entity->workspace) ? $entity->workspace->entity : null;
        $index_factory = \Drupal::service('multiversion.entity_index.factory');
        /** @var \Drupal\multiversion\Entity\Index\RevisionTreeIndexInterface $tree */
        $tree = $index_factory->get('multiversion.entity_index.rev.tree', $workspace);
        $default_rev = $tree->getDefaultRevision($entity->uuid());

        if ($entity->_rev->value == $default_rev) {
          $entity->isDefaultRevision(TRUE);
        }
        // @todo: {@link https://www.drupal.org/node/2597538 Needs test.}
        else {
          $entity->isDefaultRevision(FALSE);
        }
      }
    }

    return parent::doSave($id, $entity);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $entities) {
    // Entities are always "deleted" as new revisions when using a Multiversion
    // storage handler.
    $ids = [];
    foreach ($entities as $entity) {
      $ids[] = $entity->id();
      $entity->_deleted->value = TRUE;
      $this->save($entity);
    }

    // Reset the static cache for the "deleted" entities.
    $this->resetCache(array_keys($ids));
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRevision($revision_id) {
    // Do nothing by design.
  }

  /**
   * {@inheritdoc}
   */
  public function purge(array $entities) {
    parent::delete($entities);
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache(array $ids = NULL) {
    parent::resetCache($ids);
    $ws = $this->getWorkspaceId();
    if ($this->entityType->isStaticallyCacheable() && isset($ids)) {
      foreach ($ids as $id) {
        unset($this->entities[$ws][$id]);
      }
    }
    else {
      $this->entities[$ws] = [];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getFromStaticCache(array $ids) {
    $ws = $this->getWorkspaceId();
    $entities = [];
    // Load any available entities from the internal cache.
    if ($this->entityType->isStaticallyCacheable() && !empty($this->entities[$ws])) {
      $entities += array_intersect_key($this->entities[$ws], array_flip($ids));
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function setStaticCache(array $entities) {
    if ($this->entityType->isStaticallyCacheable()) {
      $ws = $this->getWorkspaceId();
      if (!isset($this->entities[$ws])) {
        $this->entities[$ws] = [];
      }
      $this->entities[$ws] += $entities;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function buildCacheId($id) {
    $ws = $this->getWorkspaceId();
    return "values:{$this->entityTypeId}:$id:$ws";
  }

  /**
   * {@inheritdoc}
   */
  protected function setPersistentCache($entities) {
    if (!$this->entityType->isPersistentlyCacheable()) {
      return;
    }
    $ws = $this->getWorkspaceId();
    $cache_tags = [
      $this->entityTypeId . '_values',
      'entity_field_info',
      'workspace_' . $ws,
    ];
    foreach ($entities as $entity) {
      $this->cacheBackend->set($this->buildCacheId($entity->id()), $entity, CacheBackendInterface::CACHE_PERMANENT, $cache_tags);
    }
  }

  /**
   * Uses the Conflict Tracker service to track conflicts for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to track for which to track conflicts.
   */
  protected function trackConflicts(EntityInterface $entity) {
    $workspace = isset($entity->workspace) ? $entity->workspace->entity : null;
    /** @var \Drupal\multiversion\Workspace\ConflictTrackerInterface $conflictTracker */
    $conflictTracker = \Drupal::service('workspace.conflict_tracker')
      ->useWorkspace($workspace);

    $index_factory = \Drupal::service('multiversion.entity_index.factory');
    /** @var \Drupal\multiversion\Entity\Index\RevisionTreeIndexInterface $tree */
    $tree = $index_factory->get('multiversion.entity_index.rev.tree', $workspace);
    $conflicts = $tree->getConflicts($entity->uuid());

    if ($conflicts) {
      $conflictTracker->add($entity->uuid(), $conflicts, TRUE);
    }
    else {
      $conflictTracker->resolveAll($entity->uuid());
    }
  }

}
