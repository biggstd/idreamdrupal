<?php

namespace Drupal\idream_export_json\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides block for the rendering the bokeh visualization app
 * 
 * @Block(
 *  id = "idream_export_json",
 *  admin_label = @Translation("iDream Visualization Block"),
 *  category = @Translation("iDream Visualization")
 * )
 */

 class IdreamExportJsonBlock extends BlockBase {
     /**
      * {@inheritdoc}
      */
      public function build() {
          return [
              '#cache' => ['max-age' => 0],
              '#theme' => 'visualization',
              '#link' => 'lampdev02.pnl.gov/idreamviz',
              '#id' => \Drupal::request()->query->get('id')
          ];
      }
 }