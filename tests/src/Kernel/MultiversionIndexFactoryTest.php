<?php

namespace Drupal\Tests\multiversion\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\multiversion\Entity\Index\EntityIndexInterface;
use Drupal\multiversion\Entity\Index\RevisionIndexInterface;
use Drupal\multiversion\Entity\Index\RevisionTreeIndexInterface;
use Drupal\multiversion\Entity\Index\SequenceIndexInterface;
use Drupal\multiversion\Entity\Index\UuidIndexInterface;
use Drupal\multiversion\Entity\Workspace;


/**
 * @group multiversion
 */
class MultiversionIndexFactoryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['multiversion', 'key_value', 'serialization', 'user', 'system'];

  /** @var  \Drupal\multiversion\Entity\Index\MultiversionIndexFactory */
  protected $multiversionIndexFactory;

  /** @var  \Drupal\multiversion\Entity\WorkspaceInterface */
  protected $workspace;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('workspace');
    $this->installConfig('multiversion');
    $this->multiversionIndexFactory = \Drupal::service('multiversion.entity_index.factory');

    $this->workspace = Workspace::create([
      'type' => 'test',
      'machine_name' => 'le_workspace',
      'label' => 'Le Workspace',
    ]);
    $this->workspace->save();
  }

  /**
   * Tests the factory.
   */
  public function testFactory() {
    // With workspace
    $sequence_index = $this->multiversionIndexFactory->get('multiversion.entity_index.sequence', $this->workspace);
    $this->assertTrue(($sequence_index instanceof SequenceIndexInterface));

    $id_index = $this->multiversionIndexFactory->get('multiversion.entity_index.id', $this->workspace);
    $this->assertTrue(($id_index instanceof EntityIndexInterface));

    $uuid_index = $this->multiversionIndexFactory->get('multiversion.entity_index.uuid', $this->workspace);
    $this->assertTrue(($uuid_index instanceof UuidIndexInterface));

    $rev_index = $this->multiversionIndexFactory->get('multiversion.entity_index.rev', $this->workspace);
    $this->assertTrue(($rev_index instanceof RevisionIndexInterface));

    $rev_tree_index = $this->multiversionIndexFactory->get('multiversion.entity_index.rev.tree', $this->workspace);
    $this->assertTrue(($rev_tree_index instanceof RevisionTreeIndexInterface));

    // Without a workspace
    $sequence_index = $this->multiversionIndexFactory->get('multiversion.entity_index.sequence');
    $this->assertTrue(($sequence_index instanceof SequenceIndexInterface));

    $id_index = $this->multiversionIndexFactory->get('multiversion.entity_index.id');
    $this->assertTrue(($id_index instanceof EntityIndexInterface));

    $uuid_index = $this->multiversionIndexFactory->get('multiversion.entity_index.uuid');
    $this->assertTrue(($uuid_index instanceof UuidIndexInterface));

    $rev_index = $this->multiversionIndexFactory->get('multiversion.entity_index.rev');
    $this->assertTrue(($rev_index instanceof RevisionIndexInterface));

    $rev_tree_index = $this->multiversionIndexFactory->get('multiversion.entity_index.rev.tree');
    $this->assertTrue(($rev_tree_index instanceof RevisionTreeIndexInterface));

    // Expecting an exception
    $this->setExpectedException(\InvalidArgumentException::class);
    $this->multiversionIndexFactory->get('non.existant.service.name');
  }

}
