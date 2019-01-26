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
        $node = \Drupal::routeMatch()->getParameter('node');
        $nid = 0;

        if ($node instanceof \Drupal\node\NodeInterface) {
            // You can get nid and anything else you need from the node object.
            $nid = $node->id();
        }

        return [
            '#theme' => 'exportbutton',
            '#link' => '/idreamexport/' . $nid // This needs to grab the node id of the page its on
        ];
      }
 }