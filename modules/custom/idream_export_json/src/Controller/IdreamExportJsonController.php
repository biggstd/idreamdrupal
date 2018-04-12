<?php

namespace Drupal\idream_export_json\Controller;

use Drupal\Core\Controller\ControllerBase;
Use Drupal\Core\Routing;
Use Drupal\node\Entity;
Use Drupal\node\Entity\Node;
Use Drupal\user\Entity\User;
Use Drupal\field_collection\Entity\FieldCollectionItem;

class IdreamExportJsonController extends ControllerBase {
    /**
     * Function to export content to JSON
     */
    public function export(Node $node) {
        //dpm($node);

        $output = [
            'ID' => $node->get('uuid')->getValue()[0]['value'],
            'Publications' => [
                'Title' => $node->get('title')->getValue()[0]['value'],
                'DOI' => $node->get('field_doi')->getValue()[0]['value'],
                'Link' => $node->get('field_link')->getValue()[0]['uri'],
            ],
            // 'Experimentalist(s)' => $this->getAuthorArray($node->get('uid')->getValue()[0]['target_id']),
            'Experimentalist(s)' => $this->getAuthorArray($node->get('field_experimentalist_user')->getValue()[0]['target_id']),
            'Experiment Date' => $node->get('field_experiment_date')->getValue()[0]['value'],
            'Experiment Protocol(s)' => [
                'NMR Data Aquisition' => [
                    'degrees celsius' => $node->get('field_degrees_celcius')->getValue()[0]['value'],
                    'Magnet Strength MHz' => $node->get('field_magnet_strength')->getValue()[0]['value'],
                    'NMR Reference Compound' => $this->getReferenceCompounds($node->get('field_reference_compound')->getValue()[0]['target_id']),
                    'Number of Scans' => $node->get('field_number_of_scans')->getValue()[0]['value']
                ]
            ],
            'Samples' => $this->getSamples($node->field_samples),
            'study_factors' => $this->getStudyFactors($node->field_study_factor),
            'raw_data_file' => '',
            'processed_data_file' => ''
        ];
        
        dpm($output);

        return [
            '#type' => 'markup',
            '#markup' => 'hi'//var_export($parameters['parameters']['node'], true)
        ];
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

    /**
     * This should be using the field_experimentalist_user field
     */
    private function getAuthorArray($uid) {
        $user = User::load($uid);
 
        return [
            $user->get('name')->getValue()[0]['value'] => [
                'Affiliation' => 'Default',
                'Contact Email' => $user->get('mail')->getValue()[0]['value']
            ]
        ];
    }

    /**
     * Is this just a one entity reference?
     */
    private function getReferenceCompounds($compound) {
        $term = \Drupal::entityManager()->getStorage('taxonomy_term')->load($compound);

        return $term->name->getValue()[0]['value'];
    }

    private function getSamples($samples) {
        $return = [];

        foreach($samples as $sample) {
            $fieldCollectionItem = FieldCollectionItem::load($sample->value);

            $sample_name = $fieldCollectionItem->field_sample_name->value;
            $sample_data = [];

            foreach($fieldCollectionItem->field_derives_from as $derived_from_item) {
                $derivesFromCollectionItem = FieldCollectionItem::load($derived_from_item->value);
                $sample_data[$derivesFromCollectionItem->field_derives_from_name->value] = [
                    "percent purity by weight" => $derivesFromCollectionItem->field_purity->value
                ];
            }
            $return[$sample_name]['Derives From'] = $sample_data;
        }

        //$fc_manager = \Drupal::entityTypeManager()->getStorage('field_collection')->load($eid);

        return $return;
    }

    private function getStudyFactors($study_factors) {
        $return = [];

        foreach($study_factors as $study_factor) {
            $fieldCollectionItem = FieldCollectionItem::load($study_factor->value);

            $return[$fieldCollectionItem->field_study_factor_name->value] = 
                $fieldCollectionItem->field_molarity->value;
        }

        return $return;
    }
}