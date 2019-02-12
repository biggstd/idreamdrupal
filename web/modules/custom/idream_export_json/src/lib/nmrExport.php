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
        $this->node_information = null;
    }

    public function buildAndSave(Node $node) {
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
                'public_release_date' => $this->getPublishedDate()
            ],
            'node_factors' => $this->getFactors($this->node->field_experiment_factors), 
            'node_samples' => $this->getSamples($this->node->field_experiment_samples), 
            'node_experiments' => $this->getExperiments($this->node->field_assay),
            'node_comments' => $this->getComments($this->node->field_experiment_comment),
        ];
    }

    /**
     * Builds an array of factors, calling the underlying getFactor function to return an array of 
     * individual factor data.
     *
     * @param array $factors
     * @return array $return
     */
    private function getFactors($factors) {
        $return = [];

        foreach($factors as $factor) {
            $return[] = $this->getFactor($factor->target_id);
        }

        return $return;
    }

    /**
     * This function returns a formated date string of the date the node was created, i.e submission date.
     *
     * @return string date
     */
    private function getNodeCreatedDate() {
        return date("Y-m-d", substr($this->node->getCreatedTime(), 0, 10));
    }

    /**
     * This function returns a direct url to this node, base url and path included
     *
     * @return string URL
     */
    private function getNodeURL() {
        return \Drupal::request()->getHost() . \Drupal::service('path.alias_manager')->getAliasByPath('/node/'.$this->node->id());;
    }

    /**
     * Returns the published date as string
     *
     * @return string publishedDate
     */
    private function getPublishedDate() {
        if(!isset($this->node_information)) {
            $target_id = $this->node->field_information_entity->target_id;
            $this->node_information = \Drupal\node\Entity\Node::load($target_id);
        }
        return $this->node_information->field_public_release_date->getValue()[0]['value'];
    }

    /**
     * Returns the entity referenced field value of field_experiment_comment, attempts 
     * to strip the HTML that returns from it.
     *
     * @return string description
     */
    private function getNodeDescription() {
        if(!isset($this->node_information)) {
            $target_id = $this->node->field_information_entity->target_id;
            $this->node_information = \Drupal\node\Entity\Node::load($target_id);
        }

        return $this->node_information->field_description->getValue()[0]['value'];
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
     * This function assembles the samples associated with an experiment
     *
     * @param array $samples
     * @return array $return
     */
    private function getSamples($samples) {
        $return = [];

        foreach($samples as $sample) {
            $node_sample = \Drupal\node\Entity\Node::load($sample->target_id);

            $sample_data['sample_name'] = $node_sample->title->value;
            $sample_data['sample_factors'] = $this->getFactors($node_sample->field_factor_entity);
            $sample_data['sample_source']= $this->getSource($node_sample->field_source_entity);
            $sample_data['sample_species'] = $this->getSpecies($node_sample->field_species_entity);
            
            $return[] = $sample_data;
        }

        return $return;
    }

    /**
     * Function to return an array of factor data, used in creating node_samples and node_factors
     *
     * @param int $sample_factor_id
     * @return arrays
     */
    private function getFactor($sample_factor_id) {
        $sample_factor = \Drupal\node\Entity\Node::load($sample_factor_id);
        $return = [];
        
        !isset($sample_factor->field_factor_type->value) ?: $return['factor_type'] = $sample_factor->field_factor_type->value; 
        !isset($sample_factor->field_decimal_value->value) ?: $return['decimal_value'] = $sample_factor->field_decimal_value->value;
        !isset($sample_factor->field_string_value->value) ?: $return['string_value'] = $sample_factor->field_string_value->value;
        !isset($sample_factor->field_reference_value->value) ?: $return['reference_value'] = $sample_factor->field_reference_value->value;
        !isset($sample_factor->field_unit->value) ?: $return['unit_reference'] = $sample_factor->field_unit->value;
        !isset($sample_factor->field_csv_column_index->value) ?: $return['csv_column_index'] = $sample_factor->field_csv_column_index->value;

        return $return;
    }

    /**
     * Function to return an array of sample sources, used in creating node_samples
     *
     * @param array $sample_sources
     * @return array $return
     */
    private function getSource($sample_sources) {
        $return = [];

        foreach($sample_sources as $sample_source) {
            $source_node = \Drupal\node\Entity\Node::load($sample_source->target_id);

            $return[] = [
                'source_name' => $source_node->title->value,
                'source_factors' => $this->getFactors($source_node->field_factor_entity),
                'source_species' => $this->getSpecies($source_node->field_species_entity),
            ];
        }

        return $return;
    }

    /**
     * Function to return an array of sample sources, used in creating node_samples
     *
     * @param int $sample_species_id
     * @return array $return
     */
    private function getSpecies($sample_species) {
        $return = [];
    
        foreach($sample_species as $sample_specie) {
            $species_node = \Drupal\node\Entity\Node::load($sample_specie->target_id);

            $return[] = [
                "species_reference" => isset($species_node->field_species->target_id) ? 
                    $this->getSpeciesReference($species_node->field_species->target_id) : '', 
                "stoichiometry" => isset($species_node->field_stoichiometry->value) ? $species_node->field_stoichiometry->value : '',
            ];
        }
        
        return $return;
    }

    /**
     * Returns the species taxonomy term name used in getSpecies
     *
     * @param int $species_id
     * @return string taxonomy_name
     */
    private function getSpeciesReference($species_id) {
        return \Drupal\taxonomy\Entity\Term::load($species_id)->getName();
    }

    /**
     * Grabs all the experemints from assay content type
     *
     * @param array $factor_entities
     * @return array $result
     */
    private function getExperiments($experiments) {
        $result = [];

        foreach($experiments as $experiment) {
            $experiment_node = \Drupal\node\Entity\Node::load($experiment->target_id);
            $experiment_array['experiment_name'] = $experiment_node->title->value;
            $experiment_array['experiment_datafile'] = file_create_url($experiment_node->field_experiment_data->entity->getFileUri());
            $experiment_array['experiment_factors'] = $this->getFactors($experiment_node->field_experiment_factors);
            $experiment_array['experiment_samples'] = $this->getSamples($experiment_node->field_sample);
            $experiment_array['experiment_comments'] = $this->getComments($experiment_node->field_experiment_comment);
            
            $result[] = $experiment_array;
        }
        
        return $result;
    }

    /**
     * Grabs all of the comments in the array and returns an array of arrays with comment title and body.
     *
     * @param array $comment_entities
     * @return array $results
     */
    private function getComments($comment_entities) {
        $return = [];

        foreach($comment_entities as $comment_entity) {
            $comment = \Drupal\node\Entity\Node::load($comment_entity->target_id);
            $return[] = [
                'comment_title' => $comment->title->value,
                'comment_body' => strip_tags($comment->body->value),
            ];
        }

        return $return;
    }
}