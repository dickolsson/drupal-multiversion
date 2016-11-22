<?php

namespace Drupal\multiversion;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Serializer\Serializer;

class MultiversionManager implements MultiversionManagerInterface, ContainerAwareInterface {

  use ContainerAwareTrait;

  /**
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * @var int
   */
  protected $lastSequenceId;

  /**
   * Entity types that Multiversion support but are disabled.
   *
   * @var array
   */
  protected $disabledEntityTypes = [];

  /**
   * Entity types that Multiversion won't support.
   *
   * This list will mostly contain edge case entity test types that break
   * Multiversion's tests in really strange ways.
   *
   * @var array
   * @todo: {@link https://www.drupal.org/node/2597333 Fix these some day.
   * Some contrib modules might behave the same way?}
   */
  protected $entityTypeBlackList = array(
    'user',
    'shortcut',
    'contact_message',
    'content_moderation_state',
    'entity_test_no_id',
    'entity_test_base_field_display',
    'search_api_task',
  );

  /**
   * @param \Drupal\multiversion\Workspace\WorkspaceManagerInterface $workspace_manager
   * @param \Symfony\Component\Serializer\Serializer $serializer
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   * @param \Drupal\Core\Database\Connection $connection
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   */
  public function __construct(WorkspaceManagerInterface $workspace_manager, Serializer $serializer, EntityTypeManagerInterface $entity_type_manager, StateInterface $state, LanguageManagerInterface $language_manager, CacheBackendInterface $cache, Connection $connection, EntityFieldManagerInterface $entity_field_manager) {
    $this->workspaceManager = $workspace_manager;
    $this->serializer = $serializer;
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
    $this->languageManager = $language_manager;
    $this->cache = $cache;
    $this->connection = $connection;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * Static method maintaining the migration status.
   *
   * This method neededs to be static because in some strange situations Drupal
   * might create multiple instances of this manager. Is this only an issue
   * during tests perhaps?
   *
   * @param boolean $status
   * @return boolean
   */
  public static function migrationIsActive($status = NULL) {
    static $cache = FALSE;
    if ($status !== NULL) {
      $cache = $status;
    }
    return $cache;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveWorkspaceId() {
    return $this->workspaceManager->getActiveWorkspace()->id();
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveWorkspaceId($id) {
    $workspace = $this->workspaceManager->load($id);
    return $this->workspaceManager->setActiveWorkspace($workspace);
  }

  /**
   * {@inheritdoc}
   *
   * @todo: {@link https://www.drupal.org/node/2597337 Consider using the
   * nextId API to generate more sequential IDs.}
   * @see \Drupal\Core\Database\Connection::nextId
   */
  public function newSequenceId() {
    // Multiply the microtime by 1 million to ensure we get an accurate integer.
    // Credit goes to @letharion and @logaritmisk for this simple but genius
    // solution.
    $this->lastSequenceId = (int) (microtime(TRUE) * 1000000);
    return $this->lastSequenceId;
  }

  /**
   * {@inheritdoc}
   */
  public function lastSequenceId() {
    return $this->lastSequenceId;
  }

  /**
   * {@inheritdoc}
   */
  public function isSupportedEntityType(EntityTypeInterface $entity_type, $ignore_status = FALSE) {
    if ($entity_type->get('multiversion') === FALSE) {
      return FALSE;
    }
    $entity_type_id = $entity_type->id();

    if (in_array($entity_type_id, $this->entityTypeBlackList)) {
      return FALSE;
    }

    return ($entity_type instanceof ContentEntityTypeInterface);
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedEntityTypes() {
    $entity_types = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($this->isSupportedEntityType($entity_type)) {
        $entity_types[$entity_type->id()] = $entity_type;
      }
    }
    return $entity_types;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabledEntityType(EntityTypeInterface $entity_type) {
    if ($this->isSupportedEntityType($entity_type)
      && !in_array($entity_type->id(), $this->disabledEntityTypes)) {
      // Check if the whole migration is done.
      if ($this->state->get('multiversion.migration_done', FALSE)) {
        return TRUE;
      }
      // Check if the migration for this particular entity type is done or if
      // the migration is still active.
      $done = $this->state->get('multiversion.migration_done.' . $entity_type->id(), FALSE);
      return ($done || self::migrationIsActive());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEnabledEntityTypes() {
    $entity_types = [];
    foreach ($this->getSupportedEntityTypes() as $entity_type_id => $entity_type) {
      if ($this->isEnabledEntityType($entity_type)) {
        $entity_types[$entity_type_id] = $entity_type;
      }
    }
    return $entity_types;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Ensure nothing breaks if the migration is run twice.
   */
  public function enableEntityTypes() {
    $entity_types = $this->getSupportedEntityTypes();
    $migration = $this->createMigration();
    $migration->installDependencies();
    $has_data = $this->prepareContentForMigration($entity_types, $migration);

    // Nasty workaround until {@link https://www.drupal.org/node/2549143 there
    // is a better way to invalidate caches in services}.
    // For some reason we have to clear cache on the "global" service as opposed
    // to the injected one. Services in the dark corners of Entity API won't see
    // the same result otherwise. Very strange.
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    foreach ($entity_types as $entity_type_id => $entity_type) {
      $cid = "entity_base_field_definitions:$entity_type_id:" . $this->languageManager->getCurrentLanguage()->getId();
      $this->cache->invalidate($cid);
    }

    self::migrationIsActive(TRUE);
    $migration->applyNewStorage();

    // Definitions will now be updated. So fetch the new ones.
    $entity_types = $this->getSupportedEntityTypes();

    // Temporarily disable the maintenance of the {comment_entity_statistics} table.
    $this->state->set('comment.maintain_entity_statistics', FALSE);
    \Drupal::state()->resetCache();

    foreach ($entity_types as $entity_type_id => $entity_type) {
      // Migrate from the temporary storage to the new shiny home.
      if ($has_data[$entity_type_id]) {
        $migration->migrateContentFromTemp($entity_type);
        $migration->cleanupMigration($entity_type_id . '__to_tmp');
        $migration->cleanupMigration($entity_type_id . '__from_tmp');
      }

      // Mark the migration for this particular entity type as done even if no
      // actual content was migrated.
      $this->state->set("multiversion.migration_done.$entity_type_id", TRUE);
    }

    // Enable the the maintenance of entity statistics for comments.
    $this->state->set('comment.maintain_entity_statistics', TRUE);

    // Clean up after us.
    $migration->uninstallDependencies();
    self::migrationIsActive(FALSE);

    // Mark the whole migration as done. Any entity types installed after this
    // will not need a migration since they will be created directly on top of
    // the Multiversion storage.
    $this->state->set('multiversion.migration_done', TRUE);

    // Another nasty workaround because the cache is getting skewed somewhere.
    // And resetting the cache on the injected state service does not work.
    // Very strange.
    \Drupal::state()->resetCache();

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function disableEntityTypes() {
    $entity_types = $this->getSupportedEntityTypes();
    $migration = $this->createMigration();
    $migration->installDependencies();
    $has_data = $this->prepareContentForMigration($entity_types, $migration);

    // Delete all content of workspace type.
    $storage = $this->entityTypeManager->getStorage('workspace');
    $this->emptyOldStorage($storage, $migration);

    // Uninstall field storage definitions provided by multiversion.
    $this->entityTypeManager->clearCachedDefinitions();
    $update_manager = \Drupal::entityDefinitionUpdateManager();
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if ($entity_type->isSubclassOf(FieldableEntityInterface::CLASS)) {
        $entity_type_id = $entity_type->id();
        $revision_key = $entity_type->getKey('revision');
        /** @var \Drupal\Core\Entity\FieldableEntityStorageInterface $storage */
        $storage = $this->entityTypeManager->getStorage($entity_type_id);
        foreach ($this->entityFieldManager->getFieldStorageDefinitions($entity_type_id) as $storage_definition) {
          // @todo We need to trigger field purging here.
          //   See https://www.drupal.org/node/2282119.
          if ($storage_definition->getProvider() == 'multiversion' && !$storage->countFieldData($storage_definition, TRUE) && $storage_definition->getName() != $revision_key) {
            $update_manager->uninstallFieldStorageDefinition($storage_definition);
          }
        }
      }
    }

    // Disable all enabled entity types.
    $enabled_entity_types = array_keys($this->getEnabledEntityTypes());
    foreach ($enabled_entity_types as $entity_type_id) {
      $this->disabledEntityTypes[] = $entity_type_id;
    }

    self::migrationIsActive(TRUE);
    $migration->applyNewStorage();

    // Temporarily disable the maintenance of the {comment_entity_statistics} table.
    $this->state->set('comment.maintain_entity_statistics', FALSE);
    \Drupal::state()->resetCache();

    // Definitions will now be updated. So fetch the new ones.
    $entity_types = $this->getSupportedEntityTypes();
    foreach ($entity_types as $entity_type_id => $entity_type) {
      // Drop unique key from uuid on each entity type.
      $base_table = $entity_type->getBaseTable();
      $uuid_key = $entity_type->getKey('uuid');
      $this->connection->schema()->dropUniqueKey($base_table, $entity_type_id . '_field__' . $uuid_key . '__value');

      // Migrate from the temporary storage to the drupal default storage.
      if ($has_data[$entity_type_id]) {
        $migration->migrateContentFromTemp($entity_type);
        $migration->cleanupMigration($entity_type_id . '__to_tmp');
        $migration->cleanupMigration($entity_type_id . '__from_tmp');
      }

      $this->state->delete("multiversion.migration_done.$entity_type_id");
    }

    // Enable the the maintenance of entity statistics for comments.
    $this->state->set('comment.maintain_entity_statistics', TRUE);

    // Clean up after us.
    $migration->uninstallDependencies();
    self::migrationIsActive(FALSE);

    $this->state->delete('multiversion.migration_done');

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function newRevisionId(ContentEntityInterface $entity, $index = 0) {
    $deleted = $entity->_deleted->value;
    $old_rev = $entity->_rev->value;
    // The 'new_revision_id' context will be used in normalizers (where it's
    // necessary) to identify in which format to return the normalized entity.
    $normalized_entity = $this->serializer->normalize($entity, NULL, ['new_revision_id' => TRUE]);
    // Remove fields internal to the multiversion system.
    foreach ($normalized_entity as $key => $value) {
      if ($key{0} == '_') {
        unset($normalized_entity[$key]);
      }
    }
    // The terms being serialized are:
    // - deleted
    // - old sequence ID (@todo: {@link https://www.drupal.org/node/2597341
    // Address this property.})
    // - old revision hash
    // - normalized entity (without revision info field)
    // - attachments (@todo: {@link https://www.drupal.org/node/2597341
    // Address this property.})
    return ($index + 1) . '-' . md5($this->termToBinary(array($deleted, 0, $old_rev, $normalized_entity, array())));
  }

  protected function termToBinary(array $term) {
    // @todo: {@link https://www.drupal.org/node/2597478 Switch to BERT
    // serialization format instead of JSON.}
    return $this->serializer->serialize($term, 'json');
  }

  /**
   * Factory method for a new Multiversion migration.
   *
   * @return \Drupal\multiversion\MultiversionMigrationInterface
   */
  protected function createMigration() {
    return MultiversionMigration::create($this->container, $this->entityTypeManager, $this->entityFieldManager);
  }

  protected function prepareContentForMigration($entity_types, MultiversionMigrationInterface $migration) {
    $has_data = [];
    // Walk through and verify that the original storage is in good order.
    // Flakey contrib modules or mocked tests where some schemas aren't properly
    // installed should be ignored.
    foreach ($entity_types as $entity_type_id => $entity_type) {
      /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage($entity_type_id);

      $has_data[$entity_type_id] = FALSE;
      try {
        if ($storage->hasData()) {
          $has_data[$entity_type_id] = TRUE;
        }
      }
      catch (\Exception $e) {
        // Don't bother with this entity type any more.
        unset($entity_types[$entity_type_id]);
      }

      // Migrate content to temporary storage. And empty the old storage.
      if ($has_data[$entity_type_id]) {
        $this->emptyOldStorage($storage, $migration);
      }
    }

    return $has_data;
  }

  protected function emptyOldStorage(EntityStorageInterface $storage, MultiversionMigrationInterface $migration) {
    if ($storage->getEntityTypeId() === 'file') {
      $migration->copyFilesToMigrateDirectory($storage);
    }
    $migration->migrateContentToTemp($storage->getEntityType());

    // Because of the way the Entity API treats entity definition updates we
    // need to ensure each storage is empty before we can apply the new
    // definition.
    $migration->emptyOldStorage($storage);
  }

}
