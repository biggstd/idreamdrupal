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

class IdreamExportJsonController extends ControllerBase {
    /**
     * Function to export content to JSON
     */
    public function export(Node $node) {
        $exportController =  new \Drupal\idream_export_json\Controller\exportController();
        $exportController->export([$node]);
        
        return $exportController->redirectToVisualization();
    }

    /**
     * Display main content
     */
    public function content() {
        return [
            '#type' => 'markup',
            '#markup' => $this->t('Hello World')
        ];
    }

    
}