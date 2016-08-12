<?php

namespace Drupal\Multiversion\Entity\Index;

use Drupal\conflict\ConflictResolverInterface;
use Symfony\Component\Serializer\Normalizer;
use Drupal\Core\Entity\RevisionableInterface;

class ComplexLcaResolver implements ConflictResolverInterface {

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
   * @return mixed
   *  Last created revision's Id.
   */
  public function merge(RevisionableInterface $revision1, RevisionableInterface $revision2, RevisionableInterface $revision3) {
    
  }
}
