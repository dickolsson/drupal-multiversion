<?php

namespace Drupal\Multiversion\Entity\Index;

use Drupal\conflict\ConflictResolverInterface;
use Symfony\Component\Serializer;
use Drupal\Serialization\Normalizer\EntityNormalizer;
use Drupal;
use Relaxed\Merge\ThreeWayMerge;
use Drupal\Core\Entity\RevisionableInterface;

/**
 * @property  container
 */
class ComplexMergeResolver implements ConflictResolverInterface {

  protected $serializer;

  protected function setUp() {
    $this->serializer = $this->container->get('serializer.normalizer.entity');
  }

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
    $r1_array = $this->serializer->normalize($revision1);
    $r2_array = $this->serializer->normalize($revision2);
    $r3_array = $this->serializer->normalize($revision3);
    $merge = new ThreeWayMerge();
    $result = $merge->performMerge($r1_array, $r2_array, $r3_array);
    return $result;
  }
}
