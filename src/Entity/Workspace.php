<?php

namespace Drupal\multiversion\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;

/**
 * The workspace entity class.
 *
 * @ContentEntityType(
 *   id = "workspace",
 *   label = @Translation("Workspace"),
 *   bundle_label = @Translation("Workspace type"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "views_data" = "Drupal\views\EntityViewsData"
 *   },
 *   admin_permission = "administer workspaces",
 *   base_table = "workspace",
 *   revision_table = "workspace_revision",
 *   data_table = "workspace_field_data",
 *   revision_data_table = "workspace_field_revision",
 *   bundle_entity_type = "workspace_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *     "machine_name" = "machine_name",
 *     "uid" = "uid",
 *     "created" = "created",
 *     "published" = "published"
 *   },
 *   multiversion = FALSE,
 *   local = TRUE
 * )
 */
class Workspace extends ContentEntityBase implements WorkspaceInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Add the published field.
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Workspace name'))
      ->setDescription(t('The workspace name.'))
      ->setRevisionable(TRUE)
      ->setSetting('max_length', 128)
      ->setRequired(TRUE);

    $fields['machine_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Workspace ID'))
      ->setDescription(t('The workspace machine name.'))
      ->setRevisionable(TRUE)
      ->setSetting('max_length', 128)
      ->setRequired(TRUE)
      ->addPropertyConstraints('value', ['Regex' => ['pattern' => '/^[\da-z_$()+-\/]*$/']]);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setDescription(t('The workspace owner.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('Drupal\multiversion\Entity\Workspace::getCurrentUserId');

    $fields['type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Type'))
      ->setDescription(t('The workspace type.'))
      ->setSetting('target_type', 'workspace_type')
      ->setReadOnly(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the workspace was last edited.'))
      ->setRevisionable(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The UNIX timestamp of when the workspace has been created.'));

    $fields['published']->addConstraint('UnpublishWorkspace');

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    if (!$this->isNew()) {
      $workspace_id = $this->id();
      $entity_type_manager = \Drupal::entityTypeManager();
      // Delete related workspace pointer entities.
      /** @var \Drupal\workspace\WorkspacePointerInterface[] $workspace_pointers */
      $workspace_pointers = $entity_type_manager->getStorage('workspace_pointer')->loadByProperties(['workspace_pointer' => $workspace_id]);
      if (!empty($workspace_pointers)) {
        $workspace_pointer = reset($workspace_pointers);
        $workspace_pointer->delete();
      }

      /** @var \Drupal\Core\Queue\QueueInterface $queue */
      $queue = \Drupal::queue('deleted_workspace_queue');
      $queue->createQueue();
      /** @var \Drupal\multiversion\MultiversionManagerInterface $multiversion_manager */
      $multiversion_manager = \Drupal::service('multiversion.manager');
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity_type */
      foreach ($multiversion_manager->getEnabledEntityTypes() as $entity_type) {
        // Load IDs for deleted entities.
        $deleted_entity_ids = $entity_type_manager
          ->getStorage($entity_type->id())
          ->getQuery()
          ->useWorkspace($workspace_id)
          ->isDeleted()
          ->execute();
        // Load IDs for non-deleted entities.
        $entity_ids = $entity_type_manager
          ->getStorage($entity_type->id())
          ->getQuery()
          ->useWorkspace($workspace_id)
          ->isNotDeleted()
          ->execute();
        foreach (array_merge($entity_ids, $deleted_entity_ids) as $entity_id) {
          $data = [
            'workspace_id' => $workspace_id,
            'entity_type_id' => $entity_type->id(),
            'entity_id' => $entity_id,
          ];
          $queue->createItem($data);
        }
      }
      // Add the workspace to the queue to be deleted.
      $data = [
        'entity_type_id' => 'workspace',
        'entity_id' => $workspace_id,
      ];
      $queue->createItem($data);

      if ($this->id() === $multiversion_manager->getActiveWorkspaceId()) {
        $multiversion_manager->setActiveWorkspaceId(\Drupal::getContainer()->getParameter('workspace.default'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdateSeq() {
    return \Drupal::service('multiversion.entity_index.sequence')->useWorkspace($this->id())->getLastSequenceId();
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($created) {
    $this->set('created', (int) $created);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStartTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getMachineName() {
    return $this->get('machine_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isDefaultWorkspace() {
    return $this->id() == \Drupal::getContainer()->getParameter('workspace.default');
  }

  /**
   * Default value callback for 'uid' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }

}
