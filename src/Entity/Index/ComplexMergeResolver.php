<?php

namespace Drupal\Multiversion\Entity\Index;

use Drupal\conflict\ConflictResolverInterface;
use Symfony\Component\Serializer;
use Drupal\Serialization\Normalizer;
use Drupal;
use Relaxed\Merge\ThreeWayMerge;
use Drupal\Core\Entity\RevisionableInterface;

class ComplexMergeResolver implements ConflictResolverInterface {

  
  /**
   * {@inheritdoc}
   */
  public function applies() {
    return TRUE;
  }

  /**
   * @param RevisionableInterface $revision1
   * @param RevisionableInterface $revision2
   * @param RevisionableInterface $revision3
   *
   * @return array
   */
  public function merge(RevisionableInterface $revision1, RevisionableInterface $revision2, RevisionableInterface $revision3) {
    $r1_array = \Drupal::service('serializer')->normalize($revision1);
    $r2_array = \Drupal::service('serializer')->normalize($revision2, 'array');
    $r3_array = \Drupal::service('serializer')->normalize($revision3, 'array');
    $merge = new ThreeWayMerge();
    $result = $merge->performMerge($r1_array, $r2_array, $r3_array);
    return $result;
  }
}
