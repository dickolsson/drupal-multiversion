<?php

namespace Drupal\Multiversion\Entity\Index;

use Drupal\conflict\ConflictAncestorResolverInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Fhaculty\Graph\Vertex;
use Relaxed\LCA\LowestCommonAncestor;
use Fhaculty\Graph\Graph;
use Relaxed\LCA\LcaException;
use Drupal\Multiversion\Entity\Index\RevisionTreeIndex;

class ComplexLcaResolver implements ConflictAncestorResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function applies() {
    return TRUE;
  }
  
  /**
   * Find the lowest common parent of two revisions from given graph.
   *
   * @param RevisionableInterface $revision1
   * @param RevisionableInterface $revision2
   * @param Graph $graph
   *
   * @return array
   *   Returns an array of vertices or an empty array.
   */
  public function resolve(RevisionableInterface $revision1, RevisionableInterface $revision2, Graph $graph = NULL) {
    $lca = new LowestCommonAncestor($graph);
    $vertices = $graph->getVertices()->getMap();
    return $lca->find($vertices[$revision1->_rev->value], $vertices[$revision2->_rev->value]);
  }
}
