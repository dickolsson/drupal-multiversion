<?php

namespace Drupal\multiversion\Entity\Index;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\multiversion\Entity\Workspace;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Fhaculty\Graph\Graph;

/**
 * @todo: {@link https://www.drupal.org/node/2597444 Consider caching once/if
 * rev and rev tree indices are merged.}
 */
class RevisionTreeIndex implements RevisionTreeIndexInterface {

  /**
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValueFactory;

  /**
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * @var \Drupal\multiversion\Entity\Index\MultiversionIndexFactory
   */
  protected $indexFactory;

  /**
   * @var string
   */
  protected $workspaceId;

  /**
   * @var array
   */
  protected $cache = array();

  /**
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   * @param \Drupal\multiversion\Workspace\WorkspaceManagerInterface $workspace_manager
   * @param \Drupal\multiversion\Entity\Index\MultiversionIndexFactory $index_factory
   */
  function __construct(KeyValueFactoryInterface $key_value_factory, WorkspaceManagerInterface $workspace_manager, MultiversionIndexFactory $index_factory) {
    $this->keyValueFactory = $key_value_factory;
    $this->workspaceManager = $workspace_manager;
    $this->indexFactory = $index_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function useWorkspace($id) {
    $this->workspaceId = $id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTree($uuid) {
    $values = $this->buildTree($uuid);
    return $values['tree'];
  }

  /**
   * {@inheritdoc}
   */
  public function getGraph($uuid) {
    $tree = $this->getTree($uuid);
    $graph = new Graph();
    $rev_ids = array();
    $this->storeNodesId($tree, $rev_ids);
    $vertices = $this->generateVertices($graph, $rev_ids);
    $this->generateEdges($vertices, $tree);
    return $graph;
  }

  /**
   * Helper function to store all revision IDs in an array.
   *
   * @param array $tree
   *   An associative array containing information about tree.
   * @param array $rev_ids
   *   An array to store all revision ID.
   */
  protected function storeNodesId($tree, &$revision_ids) {
    foreach ($tree as $value) {
      $current_id = $value['#rev'];
      $revision_ids[$current_id] = $current_id;
      if (count($value['children'])) {
        $this->storeNodesId($value['children'], $revision_ids);
      }
    }
  }

  /**
   * Helper function to create Edges between parent and children.
   *
   * @param array $revisions_array
   *   Associative array containing graph nodes.
   * @param array $tree
   *   Associative array containing tree structure.
   * @param int $parent
   *   Parent vertex Id.
   */
  protected function generateEdges($revisions_array, $tree, $parent = -1 ) {
    foreach ($tree as $item) {
      $current_id = $item['#rev'];
      if($parent != -1) {
        $revisions_array[$parent]->createEdgeTo($revisions_array[$current_id]);
      }
      if(count($item['children'])) {
        $this->generateEdges($revisions_array, $item['children'], $current_id);
      }
    }
  }

  /**
   * Generates vertices for Graph.
   *
   * @param Graph $graph
   * @param array $revision_ids
   *
   * @return array
   *   An array of vertices.
   */
  protected function generateVertices(Graph $graph, $revision_ids) {
    foreach ($revision_ids as $id) {
      $ids[] = $id;
    }
    return $graph->createVertices($ids)->getMap();
  }

  /**
   * {@inheritdoc}
   */
  public function updateTree(ContentEntityInterface $entity, array $branch = array()) {
    if ($entity->getEntityType()->get('workspace') === FALSE) {
      $this->keyValueStore($entity->uuid(), 0)->setMultiple($branch);
    }
    else {
      $this->keyValueStore($entity->uuid())->setMultiple($branch);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @todo: {@link https://www.drupal.org/node/2597422 The revision tree also
   * contain missing revisions. We need a better way to count.}
   */
  public function countRevs($uuid) {
    $values = $this->buildTree($uuid);
    return count($values['default_branch']);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultRevision($uuid) {
    $values = $this->buildTree($uuid);
    return $values['default_rev']['#rev'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultBranch($uuid) {
    $values = $this->buildTree($uuid);
    return $values['default_branch'];
  }

  /**
   * {@inheritdoc}
   */
  public function getOpenRevisions($uuid) {
    $revs = [];
    $values = $this->buildTree($uuid);
    foreach ($values['open_revs'] as $rev => $element) {
      $revs[$rev] = $element['#rev_info']['status'];
    }
    return $revs;
  }

  /**
   * {@inheritdoc}
   */
  public function getConflicts($uuid) {
    $revs = [];
    $values = $this->buildTree($uuid);
    foreach ($values['conflicts'] as $rev => $element) {
      $revs[$rev] = $element['#rev_info']['status'];
    }
    return $revs;
  }

  /**
   * {@inheritdoc}
   */
  public static function sortRevisions(array $a, array $b) {
    $a_deleted = ($a['#rev_info']['status'] == 'deleted') ? TRUE : FALSE;
    $b_deleted = ($b['#rev_info']['status'] == 'deleted') ? TRUE : FALSE;

    // The goal is to sort winning revision candidates from low to high. The
    // criteria are:
    // 1. Non-deleted always win over deleted
    // 2. Higher ASCII sort on revision hash wins
    if ($a_deleted && !$b_deleted) {
      return 1;
    }
    elseif (!$a_deleted && $b_deleted) {
      return -1;
    }
    return ($a['#rev'] < $b['#rev']) ? 1 : -1;
  }

  /**
   * {@inheritdoc}
   */
  public static function sortTree(array &$tree) {
    // Sort all tree elements according to the algorithm before recursing.
    usort($tree, [__CLASS__, 'sortRevisions']);

    foreach ($tree as &$element) {
      if (!empty($element['children'])) {
        self::sortTree($element['children']);
      }
    }
  }

  /**
   * @param string $uuid
   * @param $workspace_id
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected function keyValueStore($uuid, $workspace_id = null) {
    if (!is_numeric($workspace_id)) {
      $workspace_id = $this->getWorkspaceId();
    }
    return $this->keyValueFactory->get("multiversion.entity_index.rev.tree.$workspace_id.$uuid");
  }

  /**
   * Helper method to build the revision tree.
   */
  protected function buildTree($uuid) {
    $revs = $this->keyValueStore($uuid)->getAll();
    if (!$revs) {
      $revs = $this->keyValueStore($uuid, 0)->getAll();
    }
    // Build the keys to fetch from the rev index.
    $keys = [];
    foreach (array_keys($revs) as $rev) {
      $keys[] = "$uuid:$rev";
    }
    $workspace = Workspace::load($this->getWorkspaceId());
    $revs_info = $this->indexFactory
      ->get('multiversion.entity_index.rev', $workspace)
      ->getMultiple($keys);
    return self::doBuildTree($uuid, $revs, $revs_info);
  }

  /**
   * Recursive helper method to build the revision tree.
   *
   * @return array
   *   Returns an array containing the built tree, open revisions, default
   *   revision, default branch and conflicts.
   *
   * @todo: {@link https://www.drupal.org/node/2597430 Implement
   * 'deleted_conflicts'.}
   */
  protected static function doBuildTree($uuid, $revs, $revs_info, $parse = 0, &$tree = array(), &$open_revs = array(), &$conflicts = array()) {
    foreach ($revs as $rev => $parent_revs) {
      foreach ($parent_revs as $parent_rev) {
        if ($rev == 0) {
          continue;
        }

        if ($parent_rev == $parse) {

          // Avoid bad data to cause endless loops.
          // @todo: {@link https://www.drupal.org/node/2597434 Needs test.}
          if ($rev == $parse) {
            throw new \InvalidArgumentException('Child and parent revision can not be the same value.');
          }

          // Build an element structure compatible with Drupal's Render API.
          $i = count($tree);
          $tree[$i] = array(
            '#type' => 'rev',
            '#uuid' => $uuid,
            '#rev' => $rev,
            '#rev_info' => array(
              'status' => isset($revs_info["$uuid:$rev"]['status']) ? $revs_info["$uuid:$rev"]['status'] : 'missing',
              'default' => FALSE,
              'open_rev' => FALSE,
              'conflict' => FALSE,
            ),
            'children' => array(),
          );

          // Recurse down through the children.
          self::doBuildTree($uuid, $revs, $revs_info, $rev, $tree[$i]['children'], $open_revs, $conflicts);

          // Find open revisions and conflicts. Only revisions with no children,
          // and that are not missing can be an open revision or a conflict.
          if (empty($tree[$i]['children']) && $tree[$i]['#rev_info']['status'] != 'missing') {
            $tree[$i]['#rev_info']['open_rev'] = TRUE;
            $open_revs[$rev] = $tree[$i];
            // All open revisions, except deleted and default revisions, are
            // conflicts by definition. We will revert the conflict flag when we
            // find the default revision later on.
            if ($tree[$i]['#rev_info']['status'] != 'deleted') {
              $tree[$i]['#rev_info']['conflict'] = TRUE;
              $conflicts[$rev] = $tree[$i];
            }
          }
        }
      }
    }

    // Now when the full tree is built we'll find the default revision and
    // its branch.
    if ($parse == 0) {
      $default_rev = 0;
      uasort($open_revs, [__CLASS__, 'sortRevisions']);
      $default_rev = reset($open_revs);

      // Remove the default revision from the conflicts array and sort it.
      unset($conflicts[$default_rev['#rev']]);
      uasort($conflicts, [__CLASS__, 'sortRevisions']);

      // Update the default revision in the tree and sort it.
      self::updateDefaultRevision($tree, $default_rev);
      self::sortTree($tree);

      // Find the branch of the default revision.
      $default_branch = array();
      $rev = $default_rev['#rev'];
      while ($rev != 0) {
        if (isset($revs_info["$uuid:$rev"])) {
          $default_branch[$rev] = $revs_info["$uuid:$rev"]['status'];
        }
        // Only the first parent gets included in the default branch.
        $rev = $revs[$rev][0];
      }
      return array(
        'tree' => $tree,
        'default_rev' => $default_rev,
        'default_branch' => array_reverse($default_branch),
        'open_revs' => $open_revs,
        'conflicts' => $conflicts,
      );
    }
  }

  /**
   * Helper method to update the default revision.
   */
  protected static function updateDefaultRevision(&$tree, $default_rev) {
    // @todo: {@link https://www.drupal.org/node/2597442 We can temporarily
    // flip the sort to find the default rev earlier.}
    foreach ($tree as &$element) {
      if (isset($element['#rev']) && $element['#rev'] == $default_rev['#rev']) {
        $element['#rev_info']['default'] = TRUE;
        $element['#rev_info']['conflict'] = FALSE;
        break;
      }
      if (!empty($element['children'])) {
        self::updateDefaultRevision($element['children'], $default_rev);
      }
    }
  }

  /**
   * Helper method to get the workspace ID to query.
   */
  protected function getWorkspaceId() {
    return $this->workspaceId ?: $this->workspaceManager->getActiveWorkspace()->id();
  }

}
