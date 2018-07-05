<?php

namespace Drupal\Tests\multiversion\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\multiversion\Entity\Workspace;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;


/**
 * @group multiversion
 */
class EntityLoadingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['multiversion', 'key_value', 'serialization', 'user', 'system', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('workspace');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig('multiversion');
    $this->installSchema('key_value', 'key_value_sorted');
    $multiversion_manager = $this->container->get('multiversion.manager');
    $multiversion_manager->enableEntityTypes();
  }

  /**
   * Tests loading entities.
   */
  public function testLoadingEntities() {
    $un_workspace = Workspace::create([
      'type' => 'test',
      'machine_name' => 'un_workspace',
      'label' => 'Un Workspace',
    ]);
    $un_workspace->save();
    $dau_workspace = Workspace::create([
      'type' => 'test',
      'machine_name' => 'dau_workspace',
      'label' => 'Dau Workspace',
    ]);
    $dau_workspace->save();

    $workspace_manager = \Drupal::service('workspace.manager');
    $this->assertEquals($un_workspace->id(), $workspace_manager->getActiveWorkspaceId());

    $node_type = NodeType::create([
      'type' => 'example',
    ]);
    $node_type->save();

    $node = Node::create([
      'type' => 'example',
      'title' => 'Test title',
    ]);
    $node->save();
    $this->assertEquals($un_workspace->id(), $node->workspace->target_id);

    $node2 = Node::create([
      'type' => 'example',
      'title' => 'Test title',
      'workspace' => $dau_workspace->id(),
    ]);
    $node2->save();
    $this->assertEquals($dau_workspace->id(), $node2->workspace->target_id);

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $entities = $storage->loadMultiple();
    $this->assertEquals(1, count($entities));
    $this->assertEquals($node->id(), reset($entities)->id());

    $results = $storage->getQuery()->execute();
    $this->assertEquals(1, count($results));
    $this->assertEquals($node->id(), reset($results));
  }

}
