<?php

namespace Drupal\multiversion\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Utility\Error;
use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;
use Drupal\multiversion\Entity\Workspace;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Exception;
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
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  private $workspaceManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, WorkspaceManagerInterface $workspace_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->workspaceManager = $workspace_manager;
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
   * @throws \Exception
   */
  public function processItem($data) {
    $storage = $this->entityTypeManager->getStorage($data['entity_type_id']);
    if ($storage instanceof ContentEntityStorageInterface && !empty($data['workspace_id'])) {
      $workspace = Workspace::load($data['workspace_id']);
      if ($workspace) {
        $this->workspaceManager->setActiveWorkspace($workspace);
      }
      $original_storage = $storage->getOriginalStorage();
      $entity = $original_storage->load($data['entity_id']);
      if ($entity) {
        $this->deleteEntity($original_storage, $entity);
      }
      $default_workspace = Workspace::load(\Drupal::getContainer()->getParameter('workspace.default'));
      if ($default_workspace) {
        $this->workspaceManager->setActiveWorkspace($default_workspace);
      }
    }
    elseif ($data['entity_type_id'] == 'workspace') {
      $entity = $storage->load($data['entity_id']);
      if ($entity) {
        $this->deleteEntity($storage, $entity);
        // Cleanup indexes.
        $database = \Drupal::database();
        $collections = [
          'multiversion.entity_index.id.' . $data['entity_id'],
          'multiversion.entity_index.uuid.' . $data['entity_id'],
          'multiversion.entity_index.rev.' . $data['entity_id'],
        ];
        $database
          ->delete('key_value')
          ->condition('collection', $collections, 'IN')
          ->execute();
        // Delete sequence indexes.
        $database
          ->delete('key_value_sorted')
          ->condition('collection', 'multiversion.entity_index.sequence.' . $data['entity_id'])
          ->execute();
        // Delete revision tree indexes.
        $database
          ->delete('key_value')
          ->condition('collection', 'multiversion.entity_index.rev.tree.' . $data['entity_id'] . '.%', 'LIKE')
          ->execute();
      }
    }
  }

  /**
   * @param \Drupal\Core\Entity\ContentEntityStorageInterface $storage
   * @param \Drupal\Core\Entity\ContentEntityStorageInterface $entity
   */
  protected function deleteEntity($storage, $entity) {
    try {
      $storage->delete([$entity]);
    }
    catch (Exception $e) {
      $arguments = Error::decodeException($e) + ['%uuid' => $entity->uuid()];
      $message = t('%type: @message in %function (line %line of %file). The error occurred while deleting the entity with the UUID: %uuid.', $arguments);
      watchdog_exception('Multiversion', $e, $message);
    }
  }
}
