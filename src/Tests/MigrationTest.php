<?php

/**
 * @file
 * Contains \Drupal\multiversion\Tests\MigrationTest.
 */

namespace Drupal\multiversion\Tests;

use Drupal\multiversion\Entity\Query\QueryInterface;
use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;
use Drupal\simpletest\WebTestBase;

/**
 * Test the MigrationTest class.
 *
 * @group multiversion
 */
class MigrationTest extends WebTestBase {

  protected $strictConfigSchema = FALSE;

  /**
   * @var \Drupal\multiversion\MultiversionManagerInterface
   */
  protected $multiversionManager;

  /**
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected $moduleInstaller;

  /**
   * @var array
   */
  protected $entityTypes = array(
    'entity_test' => array(),
    'entity_test_rev' => array(),
    'entity_test_mul' => array(),
    'entity_test_mulrev' => array(),
    'user' => array(),
    'node' => array('type' => 'article', 'title' => 'foo', 'uid' => 1, 'revision_uid' => 1)
  );

  /**
   * {@inheritdoc}
   */
  public static $modules = array(
    'entity_test',
    'node'
  );

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->moduleInstaller = \Drupal::service('module_installer');

    $this->drupalCreateContentType(array('type' => 'article', 'name' => 'Article'));
  }

  public function testEnableWithExistingContent() {
    $before = array();

    foreach ($this->entityTypes as $entity_type_id => $values) {
      $storage = \Drupal::entityManager()->getStorage($entity_type_id);

      if ($entity_type_id == 'user') {
        $this->createUser(array('administer nodes'));
        // There should now be 3 users in total, including the initial anonymous
        // and admin users.
        $count = 3;
      }
      // Generic handling for the rest of the entity types.
      else {
        $count = 2;
        for ($i = 0; $i < $count; $i++) {
          $storage->create($values)->save();
        }
      }
      $count_before[$entity_type_id] = $count;
    }

    // Installing Multiversion will trigger the migration of existing content.
    $this->moduleInstaller->install(['multiversion']);
    $this->multiversionManager = \Drupal::service('multiversion.manager');

    $ids_after = array();
    // Now check that the previosuly created entities still exist, have the
    // right IDs and are multiversion enabled. That means profit. Big profit.
    foreach ($this->entityTypes as $entity_type_id => $values) {
      $entity_type = \Drupal::entityManager()->getDefinition($entity_type_id);
      $storage = \Drupal::entityManager()->getStorage($entity_type_id);
      $id_key = $entity_type->getKey('id');

      $this->assertTrue($this->multiversionManager->isEnabledEntityType($entity_type), "$entity_type_id was enabled for Multiversion.");
      $this->assertTrue($storage instanceof ContentEntityStorageInterface, "$entity_type_id got the correct storage handler assigned.");
      $this->assertTrue($storage->getQuery() instanceof QueryInterface, "$entity_type_id got the correct query handler assigned.");

      $ids_after[$entity_type_id] = $storage->getQuery()->execute();
      $this->assertEqual($count_before[$entity_type_id], count($ids_after[$entity_type_id]), "All ${entity_type_id}s were migrated.");

      foreach ($ids_after[$entity_type_id] as $revision_id => $entity_id) {
        $rev = (int) $storage->getQuery()
          ->condition($id_key, $entity_id)
          ->condition('_rev', 'NULL', '<>')
          ->count()
          ->execute();

        $workspace = (int) $storage->getQuery()
          ->condition($id_key, $entity_id)
          ->condition('workspace', 'default')
          ->count()
          ->execute();

        $deleted = (int) $storage->getQuery()
          ->condition($id_key, $entity_id)
          ->condition('_deleted', 0)
          ->count()
          ->execute();

        $this->assertEqual($rev, 1, "$entity_type_id $entity_id has a revision hash in database");
        $this->assertEqual($workspace, 1, "$entity_type_id $entity_id has correct workspace in database");
        $this->assertEqual($deleted, 1, "$entity_type_id $entity_id is not marked as deleted in database");
      }
    }

    // Now install a module with an entity type AFTER the migration and assert
    // that is being returned as supported and enabled.
    $this->moduleInstaller->install(['taxonomy']);

    $entity_type = \Drupal::entityManager()->getDefinition('taxonomy_term');
    $this->assertTrue($this->multiversionManager->isEnabledEntityType($entity_type), 'Newly installed entity types gets enabled as well.');
  }

}
