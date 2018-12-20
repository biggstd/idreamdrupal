<?php

namespace Drupal\idream_export_json\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/* Class "\\Drupal\\ideram_export_json\\Form\\ModuleConfigurationForm" does not exist*/

/**
 * Defines a form that configures forms module settings.
 */
class ModuleConfigurationForm extends ConfigFormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'idream_export_json_admin_settings';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames() {
        return [
            'idream_export_json.settings',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $config = $this->config('idream_export_json.settings');
        $form['viz_url'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Visualization App URL'),
            '#default_value' => $config->get('viz_url'),
        ];
        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $values = $form_state->getValues();
        $this->config('idream_export_json.settings')
            ->set('viz_url', $values['viz_url'])
            ->save();
        parent::submitForm($form, $form_state);
    }

}