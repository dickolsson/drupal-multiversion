<?php

namespace Drupal\multiversion;

use Drupal\block_content\BlockContentForm as CoreBlockContentForm;
use Drupal\Core\Form\FormStateInterface;

class BlockContentForm extends CoreBlockContentForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $block = $this->entity;

    // Save as a new revision if requested to do so.
    if (!$form_state->isValueEmpty('revision')) {
      $block->setNewRevision();
      // If a new revision is created, save the current user as revision author.
      $block->setRevisionCreationTime(REQUEST_TIME);
      $block->setRevisionUserId(\Drupal::currentUser()->id());
    }

    $insert = $block->isNew();
    $block->save();
    $context = array('@type' => $block->bundle(), '%info' => $block->label());
    $logger = $this->logger('block_content');
    $block_type = $this->blockContentTypeStorage->load($block->bundle());
    $t_args = array('@type' => $block_type->label(), '%info' => $block->label());

    if ($insert) {
      $logger->notice('@type: added %info.', $context);
      drupal_set_message($this->t('@type %info has been created.', $t_args));
    }
    else {
      $logger->notice('@type: updated %info.', $context);
      drupal_set_message($this->t('@type %info has been updated.', $t_args));
    }

    if ($block->id()) {
      $form_state->setValue('id', $block->id());
      $form_state->set('id', $block->id());
      if ($insert) {
        if (!$theme = $block->getTheme()) {
          $theme = $this->config('system.theme')->get('default');
        }
        $form_state->setRedirect(
          'block.admin_add',
          array(
            'plugin_id' => 'block_content:' . $block->uuid() . ':ws' . multiversion_get_active_workspace_id(),
            'theme' => $theme,
          )
        );
      }
      else {
        $form_state->setRedirectUrl($block->urlInfo('collection'));
      }
    }
    else {
      // In the unlikely case something went wrong on save, the block will be
      // rebuilt and block form redisplayed.
      drupal_set_message($this->t('The block could not be saved.'), 'error');
      $form_state->setRebuild();
    }
  }

}
