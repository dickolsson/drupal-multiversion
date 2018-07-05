<?php

namespace Drupal\multiversion;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteProvider as CoreRouteProvider;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Database\Connection;

/**
 * Overrides core RouteProvider.
 */
class RouteProvider extends CoreRouteProvider {

  /**
   * The workspace manager.
   *
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  private $workspaceManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $connection, StateInterface $state, CurrentPathStack $current_path, CacheBackendInterface $cache_backend, InboundPathProcessorInterface $path_processor, CacheTagsInvalidatorInterface $cache_tag_invalidator, $table = 'router', LanguageManagerInterface $language_manager = NULL, WorkspaceManagerInterface $workspace_manager) {
    // @todo Remove this when Multiversion requires Drupal 8.5 or newer.
    if (floatval(\Drupal::VERSION) < 8.5) {
      parent::__construct($connection, $state, $current_path, $cache_backend, $path_processor, $cache_tag_invalidator, $table);
    }
    else {
      parent::__construct($connection, $state, $current_path, $cache_backend, $path_processor, $cache_tag_invalidator, $table, $language_manager);
    }
    $this->workspaceManager = $workspace_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteCollectionForRequest(Request $request) {
    // Cache both the system path as well as route parameters and matching
    // routes.
    $workspace_id = $this->workspaceManager->getActiveWorkspaceId();
    // @todo Remove this when Multiversion requires Drupal 8.5 or newer.
    if (!method_exists($this, 'getCurrentLanguageCacheIdPart')) {
      $cid = 'route:' . "workspace$workspace_id:" . $request->getPathInfo() . ':' . $request->getQueryString();
    }
    else {
      $language_part = $this->getCurrentLanguageCacheIdPart();
      $cid = 'route:' . "workspace$workspace_id:" . "$language_part:" . $request->getPathInfo() . ':' . $request->getQueryString();
    }
    if ($cached = $this->cache->get($cid)) {
      $this->currentPath->setPath($cached->data['path'], $request);
      $request->query->replace($cached->data['query']);
      return $cached->data['routes'];
    }
    else {
      // Just trim on the right side.
      $path = $request->getPathInfo();
      $path = $path === '/' ? $path : rtrim($request->getPathInfo(), '/');
      $path = $this->pathProcessor->processInbound($path, $request);
      $this->currentPath->setPath($path, $request);
      // Incoming path processors may also set query parameters.
      $query_parameters = $request->query->all();
      $routes = $this->getRoutesByPath(rtrim($path, '/'));
      $cache_value = [
        'path' => $path,
        'query' => $query_parameters,
        'routes' => $routes,
      ];
      $this->cache->set($cid, $cache_value, CacheBackendInterface::CACHE_PERMANENT, ['route_match']);
      return $routes;
    }
  }

}
