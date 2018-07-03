<?php

namespace Drupal\multiversion\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;
use Drupal\multiversion\Entity\Workspace;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DeletedWorkspaceQueue
 *
 * @QueueWorker(
 *   id = "deleted_workspace_queue",
 *   title = @Translation("Queue of deleted workspaces"),
 *   cron = {"time" = 60}
 * )
 */
class DeletedWorkspaceQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('workspace.manager')
    );
  }

  /**
   * @param mixed $data
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function processItem($data) {
    $storage = $this->entityTypeManager->getStorage($data['entity_type_id']);
    if ($storage instanceof ContentEntityStorageInterface) {
      $original_storage = $storage->getOriginalStorage();
      $entity = $original_storage->load($data['entity_id']);
      if ($entity) {
        $original_storage->delete([$entity]);
      }
    }
    elseif ($data['entity_type_id'] == 'workspace') {
      $entity = $storage->load($data['entity_id']);
      if ($entity) {
        $storage->delete([$entity]);
      }
    }
  }
}
