<?php

namespace Drupal\Multiversion\Entity\Index;

use Drupal\conflict\ConflictAncestorResolverInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Relaxed\LCA\LowestCommonAncestor;
use Fhaculty\Graph\Graph;
use Relaxed\LCA\LcaException;
use Drupal\Multiversion\Entity\Index\RevisionTreeIndex;

class ComplexLcaResolver implements ConflictAncestorResolverInterface {

  public function applies() {
    return TRUE;
  }

  public function resolve(RevisionableInterface $revision1, RevisionableInterface $revision2,Graph $graph) {
   $lca = new LowestCommonAncestor($graph);
    $vertices = $graph->getVertices()->getMap();
    return $lca->find($vertices[$revision1], $vertices[$revision2]);
  }
}