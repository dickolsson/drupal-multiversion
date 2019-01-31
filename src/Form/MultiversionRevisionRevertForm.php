<?php

namespace Drupal\multiversion\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Form\NodeRevisionRevertForm;

/**
 * Adds a flag when the entity is reverting.
 */
class MultiversionRevisionRevertForm extends NodeRevisionRevertForm {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->revision->is_reverting = TRUE;
    parent::submitForm($form, $form_state);
  }

}
