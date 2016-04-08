<?php

namespace Drupal\multiversion\Entity\Index;

interface IndexInterface {

  /**
   * @param $id
   * @return \Drupal\multiversion\Entity\Index\IndexInterface
   */
  public function useWorkspace($id);

}
