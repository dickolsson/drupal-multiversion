<?php

namespace Drupal\multiversion\Entity\Storage\Sql;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\entity_storage_migrate\Entity\Storage\ContentEntityStorageSchemaTrait;
use Drupal\paragraphs\ParagraphStorageSchema as BaseParagraphsStorageSchema;

/**
 * Storage schema handler for generic content entities.
 */
class ParagraphsStorageSchema extends BaseParagraphsStorageSchema {

  use ContentEntityStorageSchemaTrait;

}
