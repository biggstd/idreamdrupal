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
            'node_samples' => $this->getSamples($this->node->field_experiment_samples), 
            'node_experiments' => $this->getExperiments($this->node->field_experiment_factors),
            'node_comments' => $this->getComments($this->node->field_experiment_comment),
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

    /**
     * This function assembles the samples associated with an experiment
     */
    private function getSamples($samples) {
        $return = [];

        foreach($samples as $sample) {
            $node_sample = \Drupal\node\Entity\Node::load($sample->target_id);

            $sample_data['sample_name'] = $node_sample->title->value;

            if(isset($node_sample->field_factor_entity->target_id)) {
                $sample_factors = $node_sample->field_factor_entity->getIterator();

                foreach($sample_factors as $sample_factor) {
                    $sample_data['sample_factors'][] = $this->getSampleFactors($sample_factor->target_id);
                }
            }

            if(isset($node_sample->field_source_entitty->target_id)) {
                $sample_sources = $node_sample->field_source_entitty->getIterator();

                foreach($sample_sources as $sample_source) {
                    $sample_data['sample_source'][] = $this->getSampleSource($sample_source->target_id);
                }
            }

            if(isset($node_sample->field_species_entity->target_id)) {
                $sample_species = $node_sample->field_species_entity->getIterator();

                foreach($sample_species as $sample_specie) {
                    $sample_data['sample_species'][] = $this->getSampleSpecies($sample_specie->target_id);
                }
            }

            $return[] = $sample_data;
        }

        return $return;
    }

    /**
     * Function to return an array of sample factors, used in creating node_samples
     */
    private function getSampleFactors($sample_factor_id) {
        $sample_factor = \Drupal\node\Entity\Node::load($sample_factor_id);

        return [
            "factor_type" => isset($sample_factor->field_factor_type->value) ? $sample_factor->field_factor_type->value : '', 
            "unit_reference" => isset($sample_factor->field_unit->value) ? $sample_factor->field_unit->value : '',
            "csv_column_index" => isset($sample_factor->field_csv_column_index->value) ? $sample_factor->field_csv_column_index->value : ''
        ];
    }

    /**
     * Function to return an array of sample sources, used in creating node_samples
     */
    private function getSampleSource($sample_source_id) {
        // Skippping this for now, it has references back to factors, dont currently have a good example of this
    }

    /**
     * Function to return an array of sample sources, used in creating node_samples
     */
    private function getSampleSpecies($sample_species_id) {
        $sample_species = \Drupal\node\Entity\Node::load($sample_species_id);

        return [
            "species_reference" => isset($sample_species->field_species->target_id) ? 
                $this->getSpeciesReference($sample_species->field_species->target_id) : '', 
            "stoichiometry" => isset($sample_species->field_stoichiometry->value) ? $sample_species->field_stoichiometry->value : '',
        ];
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

    /** 
     * Returns the species taxonomy term name used in getSampleSpecies
     */
    private function getSpeciesReference($species_id) {
        return \Drupal\taxonomy\Entity\Term::load($species_id)->getName();
    }

    private function getExperiments($factor_entities) {
        $result = [];

        if(isset($factor_entities->target_id)) {
            $factors = $factor_entities->getIterator();

            foreach($factors as $factor) {
                $factor_node = \Drupal\node\Entity\Node::load($factor->target_id);

                $result[] = [];
            }
        }

        return $result;
    }

    /**
     * Grabs all of the comments in the array and returns an array of arrays with comment title and body.
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