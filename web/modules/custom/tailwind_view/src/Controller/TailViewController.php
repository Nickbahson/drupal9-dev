<?php

namespace Drupal\tailwind_view\Controller;

use Drupal\Core\Controller\ControllerBase;

class TailViewController extends ControllerBase {

  public function peopleCards(){
    $media_path = \Drupal::moduleHandler()
      ->getModule('tailwind_view')->getPath();
    // 10 cards
    $data = [
      'people' => range(0,9),
      'media_path' => $media_path.'/media/',
    ];
    return [
      '#theme' => 'tailwind_view',
      '#data' => $data,
      '#attached' => [
        'library' => 'tailwind_view/tailwind_cdn',
      ],
    ];
  }

}
