<?php

namespace Drupal\multiversion\Redirect;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\State\StateInterface;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Drupal\redirect\Entity\Redirect;
use Drupal\redirect\Exception\RedirectLoopException;
use Drupal\redirect\RedirectRepository as ContribRedirectRepository;

class RedirectRepository extends ContribRedirectRepository {

  /**
   * The workspace manager.
   *
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  private $workspaceManager;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityManagerInterface $manager, Connection $connection, ConfigFactoryInterface $config_factory, WorkspaceManagerInterface $workspace_manager, StateInterface $state) {
    parent::__construct($manager, $connection, $config_factory);
    $this->workspaceManager = $workspace_manager;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function findMatchingRedirect($source_path, array $query = [], $language = Language::LANGCODE_NOT_SPECIFIED) {
    $enabled = $this->state->get('multiversion.migration_done.redirect', FALSE);
    if (!$enabled) {
      return parent::findMatchingRedirect($source_path, $query, $language);
    }
    $hashes = [Redirect::generateHash($source_path, $query, $language)];
    if ($language != Language::LANGCODE_NOT_SPECIFIED) {
      $hashes[] = Redirect::generateHash($source_path, $query, Language::LANGCODE_NOT_SPECIFIED);
    }

    // Add a hash without the query string if using passthrough querystrings.
    if (!empty($query) && $this->config->get('passthrough_querystring')) {
      $hashes[] = Redirect::generateHash($source_path, [], $language);
      if ($language != Language::LANGCODE_NOT_SPECIFIED) {
        $hashes[] = Redirect::generateHash($source_path, [], Language::LANGCODE_NOT_SPECIFIED);
      }
    }

    // Load redirects by hash. A direct query is used to improve performance.
    $rid = $this->connection->query(
      'SELECT rid FROM {redirect} WHERE hash IN (:hashes[]) AND workspace = :workspace AND _deleted != 1 ORDER BY LENGTH(redirect_source__query) DESC',
      [':hashes[]' => $hashes, ':workspace' => $this->workspaceManager->getActiveWorkspaceId()])
      ->fetchField();

    if (!empty($rid)) {
      // Check if this is a loop.
      if (in_array($rid, $this->foundRedirects)) {
        throw new RedirectLoopException('/' . $source_path, $rid);
      }
      $this->foundRedirects[] = $rid;

      $redirect = $this->load($rid);

      // Ensure redirect entity is properly loaded.
      // NULL value is returned when redirect has '_deleted' flag TRUE.
      if (empty($redirect) || !($redirect instanceof Redirect)) {
        return NULL;
      }

      // Find chained redirects.
      if ($recursive = $this->findByRedirect($redirect, $language)) {
        // Reset found redirects.
        $this->foundRedirects = [];
        return $recursive;
      }

      return $redirect;
    }

    return NULL;
  }

}
