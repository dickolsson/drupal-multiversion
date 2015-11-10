<?php

namespace Drupal\multiversion;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
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
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

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
   * @var int
   */
  protected $lastSequenceId;

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
    'entity_test_no_id',
    'entity_test_base_field_display',
    'shortcut',
    'contact_message',
  );

  /**
   * Entity types that Multiversion should support but currently does not.
   *
   * @var array
   */
  protected $entityTypeToDo = array(
    'file',
  );

  /**
   * @param \Drupal\multiversion\Workspace\WorkspaceManagerInterface $workspace_manager
   * @param \Symfony\Component\Serializer\Serializer $serializer
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   */
  public function __construct(WorkspaceManagerInterface $workspace_manager, Serializer $serializer, EntityManagerInterface $entity_manager, StateInterface $state, LanguageManagerInterface $language_manager, CacheBackendInterface $cache) {
    $this->workspaceManager = $workspace_manager;
    $this->serializer = $serializer;
    $this->entityManager = $entity_manager;
    $this->state = $state;
    $this->languageManager = $language_manager;
    $this->cache = $cache;
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
    $entity_type_id = $entity_type->id();

    if (in_array($entity_type_id, $this->entityTypeBlackList)) {
      return FALSE;
    }

    // @todo: {@link https://www.drupal.org/node/2597339 Remove this when there
    // are no entity types left to implement.}
    if (in_array($entity_type_id, $this->entityTypeToDo)) {
      return FALSE;
    }
    return ($entity_type instanceof ContentEntityTypeInterface);
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedEntityTypes() {
    $entity_types = [];
    foreach ($this->entityManager->getDefinitions() as $entity_type_id => $entity_type) {
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

    // For data integrity and consistency reasons we need to finish each
    // migration step for all entity types before moving on to the next step.

    $has_data = [];
    // Walk through and verify that the original storage is in good order.
    // Flakey contrib modules or mocked tests where some schemas aren't properly
    // installed should be ignored.
    foreach ($entity_types as $entity_type_id => $entity_type) {
      $storage = $this->entityManager->getStorage($entity_type_id);

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
    }
    // Migrate content to temporary storage.
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if ($has_data[$entity_type_id]) {
        $migration->migrateContentToTemp($entity_type);
      }
    }

    // Because of the way the Entity API treats entity definition updates we
    // need to ensure each storage is empty before we can apply the new
    // definition.
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if ($has_data[$entity_type_id]) {
        $storage = $this->entityManager->getStorage($entity_type_id);
        $migration->emptyOldStorage($entity_type, $storage);
      }
    }

    // Nasty workaround until {@link https://www.drupal.org/node/2549143 there
    // is a better way to invalidate caches in services}.
    // For some reason we have to clear cache on the "global" service as opposed
    // to the injected one. Services in the dark corners of Entity API won't see
    // the same result otherwise. Very strange.
    \Drupal::entityManager()->clearCachedDefinitions();
    foreach ($entity_types as $entity_type_id => $entity_type) {
      $cid = "entity_base_field_definitions:$entity_type_id:" . $this->languageManager->getCurrentLanguage()->getId();
      $this->cache->invalidate($cid);
    }

    self::migrationIsActive(TRUE);
    $migration->applyNewStorage();

    // Definitions will now be updated. So fetch the new ones.
    $entity_types = $this->getSupportedEntityTypes();

    // The order of migrations are important due to how stub entities are
    // created. However, there is not one fit for all scenarios.
    // @todo Make this configurable or provide the ability to modify migrations
    // @todo Needs tests
    $priorities = ['taxonomy_term', 'user', 'file'];
    foreach ($priorities as $entity_type_id) {
      if (!isset($entity_types[$entity_type_id])) {
        continue;
      }
      // Unshift the priority entity type to the beginning of the array.
      $priority = [$entity_type_id => $entity_types[$entity_type_id]];
      unset($entity_types[$entity_type_id]);
      $entity_types = array_merge($priority, $entity_types);
    }

    // Migrate from the temporary storage to the new shiny home.
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if ($has_data[$entity_type_id]) {
        $migration->migrateContentFromTemp($entity_type);
      }
      // Mark the migration for this particular entity type as done even if no
      // actual content was migrated.
      $this->state->set("multiversion.migration_done.$entity_type_id", TRUE);
    }

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
  public function newRevisionId(ContentEntityInterface $entity, $index = 0) {
    $deleted = $entity->_deleted->value;
    $old_rev = $entity->_rev->value;
    $normalized_entity = $this->serializer->normalize($entity);
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
    return MultiversionMigration::create($this->container, $this->entityManager);
  }
}
