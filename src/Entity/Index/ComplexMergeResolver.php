<?php

namespace Drupal\Multiversion\Entity\Index;

use Drupal\conflict\ConflictResolverInterface;
use Symfony\Component\Serializer;
use Relaxed\Merge\ThreeWayMerge;
use Drupal\Core\Entity\RevisionableInterface;

/**
 * @property  container
 */
class ComplexMergeResolver implements ConflictResolverInterface {

  protected $serializer;
  protected  $container;

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
    $container = \Drupal::getContainer();
    $serializer = $container->get('serializer');
    $r1_array = $serializer->normalize($revision1, 'array');
    $r2_array = $serializer->normalize($revision2, 'array');
    $r3_array = $serializer->normalize($revision3, 'array');
    $merge = new ThreeWayMerge();
    $result = $merge->performMerge($r1_array, $r2_array, $r3_array);
    return $result;
  }
}