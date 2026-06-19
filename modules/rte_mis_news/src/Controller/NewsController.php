<?php

namespace Drupal\rte_mis_news\Controller;

use Drupal\Core\Controller\ControllerBase;

class NewsController extends ControllerBase {

  public function newsPage() {
    return [
      '#theme' => 'news_static_list',
    ];
  }

}