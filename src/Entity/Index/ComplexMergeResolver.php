<?php

namespace Drupal\Multiversion\Entity\Index;

use Drupal\conflict\ConflictResolverInterface;
use Symfony\Component\Serializer;
use Symfony\Component\Serializer\Normalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Drupal\Core\Entity\RevisionableInterface;

class ComplexMergeResolver implements ConflictResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function applies() {
    return TRUE;
  }

  public function normalize($object, $format = NULL, array $context = array()) {
    $attributes = array();
    foreach ($object as $name => $field) {
      $attributes[$name] = $this->serializer->normalize($field, $format, $context);
    }
    return $attributes;
  }

  /**
   * @param RevisionableInterface $revision1
   * @param RevisionableInterface $revision2
   * @param RevisionableInterface $revision3
   *
   * @return mixed
   *  Last created revision's Id.
   */
  public function merge(RevisionableInterface $revision1, RevisionableInterface $revision2, RevisionableInterface $revision3) {
    $r1_array = $this->normalize($revision1, 'array');
    $r2_array = $this->normalize($revision2, 'array');
    $r3_array = $this->normalize($revision3, 'array');
    // Implement the recursive merge here.
  }
}
