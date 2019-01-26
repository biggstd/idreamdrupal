<?php

namespace Drupal\idream_export_json\lib;

use Drupal\Core\Controller\ControllerBase;
Use Drupal\Core\Routing;
Use Drupal\node\Entity;
Use Drupal\node\Entity\Node;
Use Drupal\user\Entity\User;
Use Drupal\field_collection\Entity\FieldCollectionItem;
Use Symfony\Component\HttpFoundation\RedirectResponse;
Use Drupal\Core\Url;

class nmrExport {
    function __construct($unique_id) {
        $this->unique_output_id = $unique_id;
        $this->output = [];
        $this->node = null;
    }

    public function buildAndSave(Node $node) {
        \Drupal::logger('idream')->error('poop');
        $this->node = $node;
        $this->build();
        $this->save();

        return true;
    }

    private function build() {
        // OLD
        /*$this->output = [
            'ID' => $this->node->get('uuid')->getValue()[0]['value'],
            'Publications' => [
                'Title' => $this->node->get('title')->getValue()[0]['value'],
                'DOI' => $this->node->get('field_doi')->getValue()[0]['value'],
                'Link' => $this->node->get('field_link')->getValue()[0]['uri'],
            ],
            'Experimentalist(s)' => $this->getAuthorArray($this->node->get('field_experimentalist_user')->getValue()[0]['target_id']),
            'Experiment Date' => $this->node->get('field_experiment_date')->getValue()[0]['value'],
            'Experiment Protocol(s)' => [
                'NMR Data Aquisition' => [
                    'degrees celsius' => $this->node->get('field_degrees_celcius')->getValue()[0]['value'],
                    'Magnet Strength MHz' => $this->node->get('field_magnet_strength')->getValue()[0]['value'],
                    'NMR Reference Compound' => $this->getReferenceCompounds($this->node->get('field_reference_compound')->getValue()[0]['target_id']),
                    'Number of Scans' => $this->node->get('field_number_of_scans')->getValue()[0]['value']
                ]
            ],
            'Samples' => $this->getSamples($this->node->field_samples),
            'study_factors' => $this->getStudyFactors($this->node->field_study_factor),
            'raw_data_file' => '',
            'processed_data_file' => ''
        ];*/
        // NEW
        
        $this->output = [
            'node_information' => [
                'node_title' => $this->node->get('title')->getValue()[0]['value'],
                'node_description' => $this->getNodeDescription(),
                'node_url' => $this->getNodeURL(),
                'submission_date' => $this->getNodeCreatedDate(),
                'public_release_date' => '' // ?Where is this coming from? This will require a module for published on date, or a manual field
            ],
            'node_samples' => $this->getSamples($this->node->field_experiment_samples), // !!!! TODO: I LEFT OFF HERE !!!!
            'node_experiments' => '',
            'node_comments' => [
                'comment_title' => '',
                'comment_body' => ''
            ]
        ];
    }

    /** 
     * This function returns a formated date string of the date the node was created, i.e submission date.
     */
    private function getNodeCreatedDate() {
        return date("Y-m-d", substr($this->node->getCreatedTime(), 0, 10));
    }

    /**
     * This function returns a direct url to this node, base url and path included
     */
    private function getNodeURL() {
        return \Drupal::request()->getHost() . \Drupal::service('path.alias_manager')->getAliasByPath('/node/'.$this->node->id());;
    }

    /**
     * Returns the entity referenced field value of field_experiment_comment, attempts 
     * to strip the HTML that returns from it.
     */
    private function getNodeDescription() {
        return strip_tags(
            $this->node->get('field_experiment_comment')
                ->first()
                ->get('entity')
                ->getTarget()
                ->getValue()
                ->body
                ->getValue()[0]['value']
            );
    }

    /**
     * This function creates the file and saves it to disk. In the future we'll need
     * to create a drupal file, attach it to the node and save it to disk in the
     * appropriate location. And do this on a entity save, then when visualization is
     * requested just load the appropriate files.
     * 
     * Reference: https://www.drupal8.ovh/en/tutoriels/47/create-a-file-drupal-8
     */
    private function save() {
        $output_dir = \Drupal::service('file_system')->realpath(file_default_scheme() . "://") . '/vizdata/' . $this->unique_output_id;
        \Drupal::logger('idream')->error('hi');
        \Drupal::logger('idream')->error(print_r($output_dir,true));

        if(!is_dir($output_dir)) {
            if(!mkdir($output_dir, 0777, true)) {
                // Creating directory failed, log it, report it to user
                \Drupal::logger('idream_export_json')->error('Creating directory for visualization failed, node_id = ' . $this->node->get('nid')->getValue()[0]['value']);
            }
        }

        file_put_contents(
            $output_dir . '/' . str_replace(' ', '_', $this->node->get('title')->getValue()[0]['value']) . '.json', 
            json_encode($this->output, JSON_PRETTY_PRINT)
        );

        return true;
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

        if(!$term) {
            return '';
        }
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

    private function getExperiments() {
        $result = [];

        return $result;
    }

}