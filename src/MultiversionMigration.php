<?php

namespace Drupal\multiversion;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\migrate\Entity\Migration;
use Drupal\migrate\Entity\MigrationInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MultiversionMigration implements MultiversionMigrationInterface {

  /**
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected $updateManager;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected $moduleInstaller;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, EntityManagerInterface $entity_manager) {
    return new static(
      $entity_manager,
      $container->get('entity.definition_update_manager'),
      $container->get('module_handler'),
      $container->get('module_installer')
    );
  }

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   * @param \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $update_manager
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $module_installer
   */
  public function __construct(EntityManagerInterface $entity_manager, EntityDefinitionUpdateManagerInterface $update_manager, ModuleHandlerInterface $module_handler, ModuleInstallerInterface $module_installer) {
    $this->entityManager = $entity_manager;
    $this->updateManager = $update_manager;
    $this->moduleHandler = $module_handler;
    $this->moduleInstaller = $module_installer;
  }

  /**
   * {@inheritdoc}
   */
  public function installDependencies() {
    $modules = ['migrate', 'migrate_drupal'];
    foreach ($modules as $i => $module) {
      if ($this->moduleHandler->moduleExists($module)) {
        unset($modules[$i]);
      }
    }
    if (!empty($modules)) {
      $this->moduleInstaller->install($modules, TRUE);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function migrateContentToTemp(EntityTypeInterface $entity_type) {
    $id = $entity_type->id() . '__to_tmp';
    if (!$migration = Migration::load($id)) {
      $values = [
        'id' => $id,
        'label' => '',
        'process' => $this->getFieldMap($entity_type, $id),
        'source' => ['plugin' => 'multiversion'],
        'destination' => ['plugin' => 'tempstore'],
      ];
      $migration = Migration::create($values);
      $migration->save();
    }
    $this->executeMigration($migration);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function emptyOldStorage(EntityTypeInterface $entity_type, EntityStorageInterface $storage) {
    $entities = $storage->loadMultiple();
    if ($entities) {
      $storage->delete($entities);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function applyNewStorage() {
    $this->updateManager->applyUpdates();
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @todo: Create the migration with the correct parameters for using stub
   *   entities for entity references.
   */
  public function migrateContentFromTemp(EntityTypeInterface $entity_type) {
    $id = $entity_type->id() . '__from_tmp';
    if (!$migration = Migration::load($id)) {
      $values = [
        'id' => $id,
        'label' => '',
        'process' => $this->getFieldMap($entity_type, $id),
        'source' => ['plugin' => 'tempstore'],
        'destination' => ['plugin' => 'multiversion'],
      ];
      $migration = Migration::create($values);
      $migration->save();
    }
    $this->executeMigration($migration);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function uninstallDependencies() {
    return $this;
  }

  /**
   * Helper method to fetch the field map for an entity type.
   *
   * @param EntityTypeInterface $entity_type
   * @param string $migration_id
   */
  protected function getFieldMap(EntityTypeInterface $entity_type, $migration_id) {
    $map = array();
    $bundle_info = $this->entityManager->getBundleInfo($entity_type->id());
    foreach ($bundle_info as $bundle_id => $bundle_label) {
      $fields = $this->entityManager->getFieldDefinitions($entity_type->id(), $bundle_id);
      foreach ($fields as $field) {
        $name = $field->getName();
        // We don't want our own fields to be part of the migration mapping or
        // they would get assigned NULL instead of default values.
        if (in_array($name, ['workspace', '_deleted', '_rev'])) {
          continue;
        }
        list(, $migration_type) = explode('__', $migration_id);

        // @todo Term parent doesn't work
        if ($migration_type == 'from_tmp' && $field->getType() == 'entity_reference' && $name != $entity_type->getKey('bundle')) {
          $settings = $field->getSettings();
          $map[$name] = [
            'plugin' => 'migration',
            'migration' => $settings['target_type'] . '__from_tmp',
            'source' => $name,
          ];
        }
        else {
          $map[$name] = $name;
        }
      }
    }
    return $map;
  }

  /**
   * Helper method for running a migration.
   *
   * @param \Drupal\migrate\Entity\MigrationInterface $migration
   * @return \Drupal\migrate\MigrateExecutableInterface
   */
  protected function executeMigration(MigrationInterface $migration) {
    // Add necessary database connection that the Migrate API needs during
    // a migration like this.
    $connection_info = Database::getConnectionInfo('default');
    foreach ($connection_info as $target => $value) {
      $connection_info[$target]['prefix'] = array(
        'default' => $value['prefix']['default'],
      );
    }
    Database::addConnectionInfo('migrate', 'default', $connection_info['default']);

    $message = new MigrateMessage();
    $executable = new MigrateExecutable($migration, $message);
    $executable->import();
    return $executable;
  }
}
