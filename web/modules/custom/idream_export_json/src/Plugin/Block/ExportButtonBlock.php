<?php

namespace Drupal\idream_export_json\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides block for the rendering the bokeh visualization app
 * 
 * @Block(
 *  id = "exportbutton",
 *  admin_label = @Translation("iDream Export Button Block"),
 *  category = @Translation("iDream Visualization")
 * )
 */

 class ExportButtonBlock extends BlockBase {
     /**
      * {@inheritdoc}
      */
      public function build() {
          return [
              '#theme' => 'exportbutton',
              '#link' => '/idreamexport/7' // This needs to grab the node id of the page its on
          ];
      }
 }