<?php

namespace Drupal\multiversion\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Utility\Error;
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
   * @throws \Drupal\Core\Entity\EntityStorageException
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
        try {
          $original_storage->delete([$entity]);
        }
        catch (\Throwable $e) {
          $arguments = Error::decodeException($e) + ['%uuid' => $entity->uuid()];
          $message = t('%type: @message in %function (line %line of %file). The error occurred while deleting the entity with the UUID: %uuid.', $arguments);
          throw new \Exception($message);
        }
      }
      $default_workspace = Workspace::load(\Drupal::getContainer()->getParameter('workspace.default'));
      if ($default_workspace) {
        $this->workspaceManager->setActiveWorkspace($default_workspace);
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
