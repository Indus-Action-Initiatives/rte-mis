<?php

namespace Drupal\rte_mis_school\Services;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service to cache all school terms with their field data.
 */
class SchoolTaxonomyCacheService {

  const CACHE_ID = 'school_terms_cache';

  /**
   * The cache backend to use for storing terms.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * The entity type manager to load taxonomy terms.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(CacheBackendInterface $cache, EntityTypeManagerInterface $entityTypeManager) {
    $this->cache = $cache;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Get all school terms from cache, or rebuild if not found.
   */
  public function getSchoolTerms(): array {
    if ($cache = $this->cache->get(self::CACHE_ID)) {
      return $cache->data;
    }
    return $this->buildAndSetCache();
  }

  /**
   * Get a specific school term's data by its ID.
   */
  public function getTermById(int $tid): ?array {
    $terms = $this->getSchoolTerms();
    return $terms['terms'][$tid] ?? NULL;
  }

  /**
   * Rebuild and set cache for the school vocabulary.
   */
  public function buildAndSetCache(): array {
    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
    $all_terms = $termStorage->loadTree('school', 0, NULL, TRUE);

    $term_data = [];

    /** @var \Drupal\taxonomy\TermInterface $term */
    foreach ($all_terms as $term) {
      $tid = $term->id();
      $field_values = [];

      foreach ($term->getFields() as $field_name => $field) {
        if (strpos($field_name, 'field_') === 0) {
          $field_values[$field_name] = $term->get($field_name)->getValue();
        }
      }

      $term_data[$tid] = [
        'id' => $tid,
        'name' => $term->label(),
        'fields' => $field_values,
      ];
    }

    $final_data = [
      'terms' => $term_data,
    ];

    $this->cache->set(self::CACHE_ID, $final_data, CacheBackendInterface::CACHE_PERMANENT, ['taxonomy_terms']);
    return $final_data;
  }

  /**
   * Clears the cached terms.
   */
  public function clearCache(): void {
    $this->cache->delete(self::CACHE_ID);
  }

}
