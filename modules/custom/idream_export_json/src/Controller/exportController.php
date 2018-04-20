<?php

namespace Drupal\idream_export_json\Controller;

use Drupal\Core\Controller\ControllerBase;
Use Drupal\Core\Routing;
Use Drupal\node\Entity;
Use Drupal\node\Entity\Node;
Use Drupal\user\Entity\User;
Use Drupal\field_collection\Entity\FieldCollectionItem;
Use Symfony\Component\HttpFoundation\RedirectResponse;
Use Drupal\Core\Url;
Use Drupal\idream_export_json\lib;

/**
 * This class is the default entry point for all exports, this way whatever the request mechanism is, it only
 * has to call this class, which will route to the appropriate class.
 */
class exportController extends ControllerBase {
    /**
     * @param $node 
     *  - An array of nodes to export
     * The primary entry point.
     * Determine the node type, and export the data
     */
    function export(array $nodes) {
        $this->unique_output_id = uniqid();
        $this->nmr =  new \Drupal\idream_export_json\lib\nmrExport($this->unique_output_id);

        foreach($nodes as $node) {
            if($node->get('type')->getValue()[0]['target_id'] == 'nmr') {
                $this->nmr->buildAndSave($node);
            }
        }
      
    }

    /**
     * This will redirect the user to the visualization page
     */
    public function redirectToVisualization() {
        return new RedirectResponse(URL::fromUserInput('/node/5?id=' . $this->unique_output_id)->toString());
    }
}

