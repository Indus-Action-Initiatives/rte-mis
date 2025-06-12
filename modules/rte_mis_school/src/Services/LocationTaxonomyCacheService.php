<?php

namespace Drupal\rte_mis_school\Services;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service to cache deeply nested taxonomy terms and serve fast lookups.
 */
class LocationTaxonomyCacheService {

  const CACHE_ID = 'location_terms_cache';

  /**
   * The cache backend to use for storing terms.
   *
   * @var Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * The entity type manager to load taxonomy terms.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(CacheBackendInterface $cache, EntityTypeManagerInterface $entityTypeManager) {
    $this->cache = $cache;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Get all location terms from cache, or rebuild if not found.
   */
  public function getLocationTerms(): array {
    if ($cache = $this->cache->get(self::CACHE_ID)) {
      return $cache->data;
    }
    return $this->buildAndSetCache();
  }

  /**
   * Get a specific term's data by its ID.
   */
  public function getTermById(int $tid): ?array {
    return $this->getLocationTerms()['terms'][$tid] ?? NULL;
  }

  /**
   * Get child term IDs of a given term.
   */
  public function getChildren(int $tid): array {
    return $this->getLocationTerms()['terms'][$tid]['children'] ?? [];
  }

  /**
   * Get parent term ID of a given term.
   */
  public function getParent(int $tid): ?int {
    return $this->getLocationTerms()['terms'][$tid]['parent'] ?? NULL;
  }

  /**
   * Get all terms with a given type_of_area (and their descendants).
   */
  public function getTermsByTypeOfArea(string $type): array {
    return $this->getLocationTerms()['by_type_of_area_data'][$type] ?? [];
  }

  /**
   * Get terms up to a specific depth.
   */
  public function getTermsUpToDepth(?int $maxDepth = NULL): array {
    $terms = $this->getLocationTerms()['terms'];
    if ($maxDepth === NULL) {
      return $terms;
    }
    $maxDepth -= 1;
    return array_filter($terms, function ($term) use ($maxDepth) {
      return $term['depth'] <= $maxDepth;
    });
  }

  /**
   * Get all terms starting from a given depth (inclusive) to the deepest level.
   *
   * @param int $startDepth
   *   The depth from which terms should be included (inclusive).
   *
   * @return array
   *   An associative array of term data keyed by term ID.
   */
  public function getTermsFromDepth(int $startDepth): array {
    $terms = $this->getLocationTerms()['terms'];

    return array_filter($terms, function ($term) use ($startDepth) {
      return $term['depth'] >= $startDepth;
    });
  }

  /**
   * Rebuild and set the cache.
   */
  public function buildAndSetCache(): array {
    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
    $all_terms = $termStorage->loadTree('location', 0, NULL, TRUE);

    $term_data = [];
    $by_parent = [];
    $by_type_ids = [];
    $by_type_data = [];

    foreach ($all_terms as $term) {
      $tid = $term->id();
      $parent_ids = $term->get('parent')->getValue();
      $parent_id = (!empty($parent_ids)) ? $parent_ids[0]['target_id'] : 0;

      $type_of_area = $term->get('field_type_of_area')->value ?? NULL;

      $term_data[$tid] = [
        'id' => $tid,
        'name' => $term->label(),
        'parent' => $parent_id,
        'children' => [],
        'type_of_area' => ['value' => $type_of_area],
        'depth' => $term->depth,
        'entity' => $term,
      ];

      $by_parent[$parent_id][] = $tid;

      if ($type_of_area) {
        $by_type_ids[$type_of_area][] = $tid;
        $by_type_data[$type_of_area][$tid] = &$term_data[$tid];
      }
    }

    // Populate children.
    foreach ($term_data as $tid => &$data) {
      $data['children'] = $by_parent[$tid] ?? [];
    }

    $final_data = [
      'terms' => $term_data,
      'by_parent' => $by_parent,
      'by_type_of_area_ids' => $by_type_ids,
      'by_type_of_area_data' => $by_type_data,
    ];

    $this->cache->set(self::CACHE_ID, $final_data, CacheBackendInterface::CACHE_PERMANENT, ['taxonomy_terms']);
    return $final_data;
  }

  /**
   * Clear the taxonomy cache.
   */
  public function clearCache(): void {
    $this->cache->delete(self::CACHE_ID);
  }

}
