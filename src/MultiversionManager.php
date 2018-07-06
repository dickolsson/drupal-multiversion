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
   * Static method maintaining the enable migration status.
   *
   * This method needs to be static because in some strange situations Drupal
   * might create multiple instances of this manager. Is this only an issue
   * during tests perhaps?
   *
   * @param boolean|array $status
   * @return boolean|array
   */
  public static function enableMigrationIsActive($status = NULL) {
    static $cache = FALSE;
    if ($status !== NULL) {
      $cache = $status;
    }
    return $cache;
  }

  /**
   * Static method maintaining the disable migration status.
   *
   * @param boolean|array $status
   * @return boolean|array
   */
  public static function disableMigrationIsActive($status = NULL) {
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
    return $this->workspaceManager->getActiveWorkspaceId();
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
  public function isSupportedEntityType(EntityTypeInterface $entity_type) {
    $supported_entity_types = \Drupal::config('multiversion.settings')->get('supported_entity_types') ?: [];
    if (empty($supported_entity_types)) {
      return FALSE;
    }

    if (!in_array($entity_type->id(), $supported_entity_types)) {
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
    if ($this->isSupportedEntityType($entity_type)) {
      $entity_type_id = $entity_type->id();
      $migration_done = $this->state->get("multiversion.migration_done.$entity_type_id", FALSE);
      $enabled_entity_types = \Drupal::config('multiversion.settings')->get('enabled_entity_types') ?: [];
      if ($migration_done && in_array($entity_type_id, $enabled_entity_types)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function allowToAlter(EntityTypeInterface $entity_type) {
    $supported_entity_types = \Drupal::config('multiversion.settings')->get('supported_entity_types') ?: [];
    $id = $entity_type->id();
    $enable_migration = self::enableMigrationIsActive();
    $disable_migration = self::disableMigrationIsActive();
    // Don't allow to alter entity type that is not supported.
    if (!in_array($id, $supported_entity_types)) {
      return FALSE;
    }
    // Don't allow to alter entity type that is in process to be disabled.
    if (is_array($disable_migration) && in_array($id, $disable_migration)) {
      return FALSE;
    }
    // Allow to alter entity type that is in process to be enabled.
    if (is_array($enable_migration) && in_array($id, $enable_migration)) {
      return TRUE;
    }
    return ($this->isEnabledEntityType($entity_type));
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
  public function enableEntityTypes($entity_types_to_enable = NULL) {
    $entity_types = ($entity_types_to_enable !== NULL) ? $entity_types_to_enable : $this->getSupportedEntityTypes();
    $enabled_entity_types = \Drupal::config('multiversion.settings')->get('enabled_entity_types') ?: [];
    if (empty($entity_types)) {
      return $this;
    }
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

    self::enableMigrationIsActive(array_keys($entity_types));
    $migration->applyNewStorage();

    // Definitions will now be updated. So fetch the new ones.
    if ($entity_types_to_enable !== NULL) {
      $updated_entity_types = [];
      foreach ($entity_types as $entity_type_id => $entity_type) {
        $updated_entity_types[$entity_type_id] = $this->entityTypeManager->getStorage($entity_type_id)->getEntityType();
      }
    }
    else {
      $updated_entity_types = $this->getSupportedEntityTypes();
    }

    // Temporarily disable the maintenance of the {comment_entity_statistics} table.
    $this->state->set('comment.maintain_entity_statistics', FALSE);
    \Drupal::state()->resetCache();

    foreach ($updated_entity_types as $entity_type_id => $entity_type) {
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

    foreach ($entity_types as $entity_type_id => $entity_type) {
      $enabled = $this->state->get("multiversion.migration_done.$entity_type_id", FALSE);
      if (!in_array($entity_type_id, $enabled_entity_types) && $enabled) {
        $enabled_entity_types[] = $entity_type_id;
      }
    }
    \Drupal::configFactory()
      ->getEditable('multiversion.settings')
      ->set('enabled_entity_types', $enabled_entity_types)
      ->save();

    // Enable the the maintenance of entity statistics for comments.
    $this->state->set('comment.maintain_entity_statistics', TRUE);

    // Clean up after us.
    $migration->uninstallDependencies();
    self::enableMigrationIsActive(FALSE);

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
  public function disableEntityTypes($entity_types_to_disable = NULL) {
    $entity_types = ($entity_types_to_disable !== NULL) ? $entity_types_to_disable : $this->getEnabledEntityTypes();
    $migration = $this->createMigration();
    $migration->installDependencies();
    $has_data = $this->prepareContentForMigration($entity_types, $migration);

    if (empty($entity_types)) {
      return $this;
    }

    if ($entity_types_to_disable === NULL) {
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
    }

    $enabled_entity_types = \Drupal::config('multiversion.settings')->get('enabled_entity_types') ?: [];
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if (($key = array_search($entity_type_id, $enabled_entity_types)) !== FALSE) {
        unset($enabled_entity_types[$key]);
      }
    }
    if ($entity_types_to_disable === NULL) {
      $enabled_entity_types = [];
    }
    \Drupal::configFactory()
      ->getEditable('multiversion.settings')
      ->set('enabled_entity_types', $enabled_entity_types)
      ->save();

    self::disableMigrationIsActive(array_keys($entity_types));
    $migration->applyNewStorage();

    // Temporarily disable the maintenance of the {comment_entity_statistics} table.
    $this->state->set('comment.maintain_entity_statistics', FALSE);
    \Drupal::state()->resetCache();

    // Definitions will now be updated. So fetch the new ones.
    $updated_entity_types = [];
    foreach ($entity_types as $entity_type_id => $entity_type) {
      $updated_entity_types[$entity_type_id] = $this->entityTypeManager->getStorage($entity_type_id)->getEntityType();
    }
    foreach ($updated_entity_types as $entity_type_id => $entity_type) {
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
    self::disableMigrationIsActive(FALSE);

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
    $this->filterNormalizedEntity($normalized_entity);
    // The terms being serialized are:
    // - deleted
    // - old sequence ID (@todo: {@link https://www.drupal.org/node/2597341
    // Address this property.})
    // - old revision hash
    // - normalized entity (without revision info field)
    // - attachments (@todo: {@link https://www.drupal.org/node/2597341
    // Address this property.})
    return ($index + 1) . '-' . md5($this->termToBinary([$deleted, 0, $old_rev, $normalized_entity, []]));
  }

  /**
   * @param array $normalized_entity
   */
  protected function filterNormalizedEntity(&$normalized_entity){
    foreach ($normalized_entity as $key => &$value) {
      if ($key{0} == '_') {
        unset($normalized_entity[$key]);
      }
      elseif (is_array($value)) {
        $this->filterNormalizedEntity($value);
      }
    }
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

      if ($has_data[$entity_type_id]) {
        // Migrate content to temporary storage.
        if ($storage->getEntityTypeId() === 'file') {
          $migration->copyFilesToMigrateDirectory($storage);
        }
        $migration->migrateContentToTemp($storage->getEntityType());
      }
    }

    // Empty old storages. Do this just after migrating all entities to
    // temporary storage because deleting some entity types could delete
    // referenced entities (E.g.: deleting poll entities will also delete
    // poll_choice).
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if ($has_data[$entity_type_id] === TRUE) {
        /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
        $storage = $this->entityTypeManager->getStorage($entity_type_id);

        // Because of the way the Entity API treats entity definition updates we
        // need to ensure each storage is empty before we can apply the new
        // definition.
        $migration->emptyOldStorage($storage);
      }
    }

    return $has_data;
  }

}
