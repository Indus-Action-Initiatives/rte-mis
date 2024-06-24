<?php

namespace Drupal\rte_mis_core\Menu;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\sitemap\Menu\SitemapMenuLinkTree;

/**
 * This class overrides the SitemapMenuLinkTree.
 */
class OverrideSiteMap extends SitemapMenuLinkTree {

  /**
   * {@inheritdoc}
   */
  protected function buildItems(array $tree, CacheableMetadata &$tree_access_cacheability, CacheableMetadata &$tree_link_cacheability) {
    $items = [];

    foreach ($tree as $data) {
      /** @var \Drupal\Core\Menu\MenuLinkInterface $link */
      $link = $data->link;

      // Gather the access cacheability of every item in the menu link tree,
      // including inaccessible items. This allows us to render cache the menu
      // tree, yet still automatically vary the rendered menu by the same cache
      // contexts that the access results vary by.
      // However, if $data->access is not an AccessResultInterface object, this
      // will still render the menu link, because this method does not want to
      // require access checking to be able to render a menu tree.
      if ($data->access instanceof AccessResultInterface) {
        $tree_access_cacheability = $tree_access_cacheability->merge(CacheableMetadata::createFromObject($data->access));
      }

      $element = [];
      // If the user doesn't have permission to view,
      // just set the title and render the link.
      if ($data->access instanceof AccessResultInterface && !$data->access->isAllowed()) {
        $element['title'] = $link->getPluginDefinition()['title'];
      }
      // Set a variable for the <li> tag. Only set 'expanded' to true if the
      // link also has visible children within the current tree.
      $element['is_expanded'] = FALSE;
      $element['is_collapsed'] = FALSE;
      if ($data->hasChildren && !empty($data->subtree)) {
        $element['is_expanded'] = TRUE;
      }
      elseif ($data->hasChildren) {
        $element['is_collapsed'] = TRUE;
      }
      // Set a helper variable to indicate whether the link is in the active
      // trail.
      $element['in_active_trail'] = FALSE;
      if ($data->inActiveTrail) {
        $element['in_active_trail'] = TRUE;
      }

      // Note: links are rendered in the menu.html.twig template; and they
      // automatically bubble their associated cacheability metadata.
      $element['attributes'] = new Attribute();
      if ($data->access->isAllowed()) {
        $element['title'] = $link->getTitle();
      }
      $element['url'] = Url::fromRoute('<none>');
      $element['url']->setOption('set_active_class', TRUE);
      $element['below'] = $data->subtree ? $this->buildItems($data->subtree, $tree_access_cacheability, $tree_link_cacheability) : [];
      if (isset($data->options)) {
        $element['url']->setOptions(NestedArray::mergeDeep($element['url']->getOptions(), $data->options));
      }
      $element['original_link'] = $link;
      // Index using the link's unique ID.
      $items[$link->getPluginId()] = $element;
    }

    return $items;
  }

}
