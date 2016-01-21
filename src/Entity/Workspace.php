<?php

/**
 * @file
 * Contains \Drupal\multiversion\Entity\Workspace.
 */

namespace Drupal\multiversion\Entity;

use Drupal\Core\Entity\Entity;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * The workspace entity class.
 *
 * @ContentEntityType(
 *   id = "workspace",
 *   label = @Translation("Workspace"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "list_builder" = "Drupal\multiversion\WorkspaceListBuilder",
 *     "form" = {
 *       "add" = "Drupal\multiversion\WorkspaceForm",
 *       "edit" = "Drupal\multiversion\WorkspaceForm",
 *       "default" = "Drupal\multiversion\WorkspaceForm"
 *     },
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/workspaces/{workspace}",
 *     "edit-form" = "/admin/structure/workspaces/{workspace}/edit",
 *     "collection" = "/admin/structure/workspaces"
 *   },
 *   admin_permission = "administer workspaces",
 *   base_table = "workspace",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *     "machine_name" = "machine_name",
 *     "created" = "created"
 *   },
 *   multiversion = FALSE,
 *   local = TRUE
 * )
 */
class Workspace extends ContentEntityBase implements WorkspaceInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Workspace ID'))
      ->setDescription(t('The workspace ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Workaspace ID'))
      ->setDescription(t('The workspace label.'))
      ->setSetting('max_length', 128)
      ->setRequired(TRUE);

    $fields['machine_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Workaspace ID'))
      ->setDescription(t('The workspace machine name.'))
      ->setSetting('max_length', 128)
      ->setRequired(TRUE)
      ->addPropertyConstraints('value', ['Regex' => ['pattern' => '/^[\da-z_$()+-\/]*$/']]);

    $fields['revision_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Revision ID'))
      ->setDescription(t('The revision ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The workspace UUID.'))
      ->setReadOnly(TRUE);

    $fields['created'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Created'))
      ->setDescription(t('The UNIX timestamp of when the workspace has been created.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdateSeq() {
    return \Drupal::service('entity.index.sequence')->useWorkspace($this->id())->getLastSequenceId();
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
  public function save() {
    if (is_null($this->getStartTime())) {
      $this->setCreatedTime(microtime(TRUE) * 1000000);
    }
    parent::save();
  }

}
