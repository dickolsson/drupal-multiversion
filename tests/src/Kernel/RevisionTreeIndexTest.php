<?php

namespace Drupal\Tests\multiversion\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\KeyValueStore\KeyValueEntityStorage;
use Drupal\Core\KeyValueStore\KeyValueFactory;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\multiversion\Entity\Index\EntityIndexInterface;
use Drupal\multiversion\Entity\Index\IndexInterface;
use Drupal\multiversion\Entity\Index\MultiversionIndexFactory;
use Drupal\multiversion\Entity\Index\RevisionTreeIndex;
use Drupal\multiversion\Entity\Workspace;
use Drupal\multiversion\Workspace\WorkspaceManager;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Drupal\Tests\token\Kernel\KernelTestBase;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;
use Drupal\multiversion\Workspace\DefaultWorkspaceNegotiator;

/**
 * @coversDefaultClass \Drupal\multiversion\Entity\Index\RevisionTreeIndex
 * @group multiversion
 */
class RevisionTreeIndexTest extends MultiversionKernelTestBase {

  /**
   * @var
   */
  protected $revisionTreeIndex;

  /**
   * @var
   */
  protected $uuid;

  /**
   * Tests the getDefaultRevision() method.
   *
   * @dataProvider getRevisionData
   */
  public function testGetDefaultRevision($is_imported) {

    $this->uuid = '9a7a81a0-8d8a-4718-b1a7-b0d452898149';

    $key_value_storage = $this->prophesize(KeyValueStoreInterface::class);
    $key_value_storage->getAll()->willReturn($this->getRevisions($is_imported));

    $key_value_factory = $this->prophesize(KeyValueFactoryInterface::class);
    $key_value_factory->get(Argument::any())->willReturn($key_value_storage->reveal());

    $workspace = $this->prophesize(Workspace::class);
    $workspace->id()->willReturn(1);

    $workspace_manager = $this->prophesize(WorkspaceManagerInterface::class);
    $workspace_manager->getActiveWorkspace()->willReturn($workspace->reveal());

    $index = $this->prophesize(EntityIndexInterface::class);
    $index->getMultiple(Argument::any())->willReturn($this->getRevisionInfo());

    $multiversion_entity_index_factory = $this->prophesize(MultiversionIndexFactory::class);
    $multiversion_entity_index_factory->get('multiversion.entity_index.rev', NULL)->willReturn($index->reveal());

    $this->revisionTreeIndex = new RevisionTreeIndex($key_value_factory->reveal(), $workspace_manager->reveal(), $multiversion_entity_index_factory->reveal());

    $container = \Drupal::getContainer();
    $container->set('keyvalue', $key_value_factory);
    $container->set('workspace.manager', $workspace_manager);
    $container->set('multiversion.entity_index.factory', $multiversion_entity_index_factory);
    \Drupal::setContainer($container);

    $default_revision = $this->revisionTreeIndex->getDefaultRevision($this->uuid);
    $this->assertNotNull($default_revision);
  }

  /**
   * Data provider.
   *
   * @return array
   *   An array of data.
   */
  public static function getRevisionData() {
    return [
      [TRUE],
      [FALSE],
    ];
  }

  /**
   * @return array
   */
  protected function getRevisions($is_imported) {
    $first_rid = 0;
    if (!$is_imported) {
      $first_rid = '2-08bb9dc59307871ab4b337bb3adddcfd';
    }
    return [
      '3-37bb3adddcfd71ab4b308bb9dc593078' =>[$first_rid],
      '4-5740ca7a7f991770091ecf93054722bc' =>['3-37bb3adddcfd71ab4b308bb9dc593078'],
      '4-6730219cd6c794a2700d20f2550eaa1e' =>['3-37bb3adddcfd71ab4b308bb9dc593078'],
      '4-8bb8b8ee7aba19e806d769701a54c233' =>['3-37bb3adddcfd71ab4b308bb9dc593078'],
      '4-e6c962a6aff780dc6dc6a2da69035b4b' =>['3-37bb3adddcfd71ab4b308bb9dc593078'],
      '4-ec6fb7fad847aa38372dee7cd5d788e4' =>['3-37bb3adddcfd71ab4b308bb9dc593078'],
      '5-36a540a10989069678d6bf11a919b954' =>['4-8bb8b8ee7aba19e806d769701a54c233'],
      '5-41f86ce7dd5810cae7c5797e45bdce40' =>['4-8bb8b8ee7aba19e806d769701a54c233'],
      '5-6e3b77e66d816e014e9b7afa585916c1' =>['4-8bb8b8ee7aba19e806d769701a54c233'],
      '5-7c25de8e3e20588183e12eb99cb075dd' =>['4-8bb8b8ee7aba19e806d769701a54c233'],
      '5-9e11f209bdb45a3bf90c0e6f75ae8d1a' =>['4-8bb8b8ee7aba19e806d769701a54c233'],
      '5-ab5f13a4ddd5085d5c0aad3511aa8a7b' =>['4-8bb8b8ee7aba19e806d769701a54c233'],
      '5-c213bd05e4110c4d75607cc83ee2e3ab' =>['4-8bb8b8ee7aba19e806d769701a54c233'],
      '5-e38a1365530ea7bf4baa968928459f91' =>['4-8bb8b8ee7aba19e806d769701a54c233'],
      '5-e56fb7943a9bb1bdc02b122a03e3bf90' =>['4-8bb8b8ee7aba19e806d769701a54c233'],
      '6-ee35cce08829c47e4b91addef1a57afe' =>['5-36a540a10989069678d6bf11a919b954'],
      '7-57d6dfe197d19f2244556ff7f725b893' =>['6-ee35cce08829c47e4b91addef1a57afe'],
      '8-9506e9651bf7b78450919a0eedbb7de6' =>['7-57d6dfe197d19f2244556ff7f725b893'],
      '9-74737bbc95b3c643fb3975d62af5c17d' =>['8-9506e9651bf7b78450919a0eedbb7de6'],
      '9-85bc65ca016061d0728fa7db1e619721' =>['8-9506e9651bf7b78450919a0eedbb7de6'],
      '10-3aafa2d3719d6465f5e7f1df361465d6' =>['9-85bc65ca016061d0728fa7db1e619721'],
      '10-67ef6a7dbb78b7dc62ccbcdd3e5215cf' =>['9-85bc65ca016061d0728fa7db1e619721'],
      '10-96c5c3ae5dc24bb2bee0c1c25d943244' =>['9-85bc65ca016061d0728fa7db1e619721'],
    ];
  }

  /**
   * @return array
   */
  protected function getRevisionInfo() {
    return [
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:10-3aafa2d3719d6465f5e7f1df361465d6' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '33',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '10-3aafa2d3719d6465f5e7f1df361465d6',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:10-67ef6a7dbb78b7dc62ccbcdd3e5215cf' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '32',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '10-67ef6a7dbb78b7dc62ccbcdd3e5215cf',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:10-96c5c3ae5dc24bb2bee0c1c25d943244' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '30',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '10-96c5c3ae5dc24bb2bee0c1c25d943244',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:3-37bb3adddcfd71ab4b308bb9dc593078' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '1',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '3-37bb3adddcfd71ab4b308bb9dc593078',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:4-5740ca7a7f991770091ecf93054722bc' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '3',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '4-5740ca7a7f991770091ecf93054722bc',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:4-6730219cd6c794a2700d20f2550eaa1e' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '6',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '4-6730219cd6c794a2700d20f2550eaa1e',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:4-8bb8b8ee7aba19e806d769701a54c233' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '7',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '4-8bb8b8ee7aba19e806d769701a54c233',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:4-e6c962a6aff780dc6dc6a2da69035b4b' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '5',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '4-e6c962a6aff780dc6dc6a2da69035b4b',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:4-ec6fb7fad847aa38372dee7cd5d788e4' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '4',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '4-ec6fb7fad847aa38372dee7cd5d788e4',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:5-36a540a10989069678d6bf11a919b954' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '24',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '5-36a540a10989069678d6bf11a919b954',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:5-41f86ce7dd5810cae7c5797e45bdce40' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '8',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '5-41f86ce7dd5810cae7c5797e45bdce40',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:5-6e3b77e66d816e014e9b7afa585916c1' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '21',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '5-6e3b77e66d816e014e9b7afa585916c1',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:5-7c25de8e3e20588183e12eb99cb075dd' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '23',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '5-7c25de8e3e20588183e12eb99cb075dd',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:5-9e11f209bdb45a3bf90c0e6f75ae8d1a' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '9',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '5-9e11f209bdb45a3bf90c0e6f75ae8d1a',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:5-ab5f13a4ddd5085d5c0aad3511aa8a7b' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '16',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '5-ab5f13a4ddd5085d5c0aad3511aa8a7b',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:5-c213bd05e4110c4d75607cc83ee2e3ab' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '14',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '5-c213bd05e4110c4d75607cc83ee2e3ab',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:5-e38a1365530ea7bf4baa968928459f91' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => NULL,
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '5-e38a1365530ea7bf4baa968928459f91',
          'is_stub' => false,
          'status' => 'indexed',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:5-e56fb7943a9bb1bdc02b122a03e3bf90' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '18',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '5-e56fb7943a9bb1bdc02b122a03e3bf90',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:6-ee35cce08829c47e4b91addef1a57afe' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '25',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '6-ee35cce08829c47e4b91addef1a57afe',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:7-57d6dfe197d19f2244556ff7f725b893' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '26',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '7-57d6dfe197d19f2244556ff7f725b893',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:8-9506e9651bf7b78450919a0eedbb7de6' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '27',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '8-9506e9651bf7b78450919a0eedbb7de6',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:9-74737bbc95b3c643fb3975d62af5c17d' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '28',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '9-74737bbc95b3c643fb3975d62af5c17d',
          'is_stub' => false,
          'status' => 'available',
        ],
      '9a7a81a0-8d8a-4718-b1a7-b0d452898149:9-85bc65ca016061d0728fa7db1e619721' =>
        [
          'entity_type_id' => 'node',
          'entity_id' => '2',
          'revision_id' => '29',
          'uuid' => '9a7a81a0-8d8a-4718-b1a7-b0d452898149',
          'rev' => '9-85bc65ca016061d0728fa7db1e619721',
          'is_stub' => false,
          'status' => 'available',
        ],
    ];
  }

}
