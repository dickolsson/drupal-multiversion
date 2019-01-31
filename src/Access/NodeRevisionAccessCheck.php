<?php

namespace Drupal\multiversion\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\Access\NodeRevisionAccessCheck as CoreNodeRevisionAccessCheck;
use Drupal\node\NodeInterface;

class NodeRevisionAccessCheck extends CoreNodeRevisionAccessCheck {

  public function checkAccess(NodeInterface $node, AccountInterface $account, $op = 'view') {
    if ($op == 'view' || $op == 'update') {
      return parent::checkAccess($node, $account, $op);
    }
    else {
      return FALSE;
    }
  }

}
