<?php

namespace Drupal\Multiversion\Entity\Index;

use Drupal\conflict\ConflictAncestorResolverInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Relaxed\LCA\LowestCommonAncestor;
use Fhaculty\Graph\Graph;
use Relaxed\LCA\LcaException;
use Drupal\Multiversion\Entity\Index\RevisionTreeIndex;

class LcaResolver implements ConflictAncestorResolverInterface {

  public function resolve(RevisionableInterface $revision1, RevisionableInterface $revision2) {
   $lca = new LowestCommonAncestor($graph);
    return $lca->find($revision1, $revision2);
  }
}