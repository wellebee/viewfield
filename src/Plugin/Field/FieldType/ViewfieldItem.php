<?php

namespace Drupal\viewfield\Plugin\Field\FieldType;

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\views\Views;

/**
 * Plugin implementation of the 'viewfield' field type.
 *
 * @FieldType(
 *   id = "viewfield",
 *   label = @Translation("Viewfield"),
 *   description = @Translation("'Defines a entity reference field type to display a view.'"),
 *   category = @Translation("Reference"),
 *   default_widget = "viewfield_select",
 *   default_formatter = "viewfield_default",
 *   list_class = "\Drupal\Core\Field\EntityReferenceFieldItemList",
 * )
 */
class ViewfieldItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return array(
      'target_type' => 'view',
    ) + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return array(
      'force_default' => 0,
      'allowed_views' => array(),
      'allowed_display_types' => array('block' => 'block'),
    ) + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);
    $schema['columns']['target_id']['description'] = 'The ID of the view.';

    $schema['columns']['display_id'] = array(
      'description' => 'The ID of the view display.',
      'type' => 'varchar',
      'length' => 255,
    );

    $schema['columns']['arguments'] = array(
      'description' => 'Arguments to be passed to the display.',
      'type' => 'varchar',
      'length' => 255,
    );

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    $properties['entity']->setDescription(t('The referenced view'));

    $properties['display_id'] = DataDefinition::create('string')
      ->setLabel(t('Display ID'))
      ->setDescription(t('The view display ID'));

    $properties['arguments'] = DataDefinition::create('string')
      ->setLabel(t('Arguments'))
      ->setDescription(t('An optional comma-delimited list of arguments for the display'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = parent::storageSettingsForm($form, $form_state, $has_data);
    // Hide entity type selection.
    $element['target_type']['#access'] = FALSE;

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $form = array();

    $form['force_default'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Always use default value'),
      '#default_value' => $this->getSetting('force_default'),
      '#description' => $this->t('Hides this field in entity edit forms and enforces the configured default value for all entities in the bundle, making it unnecessary to assign values individually to each one.<br>If this is checked, you must provide a default value.'),
    );

    $form['allowed_views'] = array(
      '#type' => 'checkboxes',
      '#options' => $this->getViewOptions(),
      '#title' => $this->t('Allowed views'),
      '#default_value' => $this->getSetting('allowed_views'),
      '#description' => $this->t('Views available for content authors. Leave empty to allow all.'),
    );

    $form['allowed_display_types'] = array(
      '#type' => 'checkboxes',
      '#options' => $this->getDisplayTypeOptions(),
      '#title' => $this->t('Allowed display types'),
      '#default_value' => $this->getSetting('allowed_display_types'),
      '#description' => $this->t('Display types available for content authors. Leave empty to allow all.'),
    );

    $form['#element_validate'][] = array(get_class($this), 'fieldSettingsFormValidate');

    return $form;
  }

  /**
   * Form API callback
   *
   * Requires that field defaults be supplied when the 'force_default' option
   * is checked.
   *
   * This function is assigned as an #element_validate callback in
   * fieldSettingsForm().
   */
  public static function fieldSettingsFormValidate(array $form, FormStateInterface $form_state) {
    $settings = $form_state->getValue('settings');
    if ($settings['force_default']) {
      $default_value = $form_state->getValue('default_value_input');
      $field_name = $form_state->getFormObject()->getEntity()->getName();
      if (empty($default_value[$field_name][0]['target_id']) || $default_value[$field_name][0]['target_id'] == '_none') {
        $form_state->setErrorByName('default_value_input', t('%title requires a default value.', array(
          '%title' => $form['force_default']['#title'],
        )));
      }
    }
  }

  /**
   * Get an options array of all enabled Views.
   *
   * @return array
   *   The array of options.
   */
  public function getViewOptions() {
    $views_options = array();
    foreach (Views::getEnabledViews() as $key => $view) {
      $views_options[$key] = FieldFilteredMarkup::create($view->get('label'));
    }
    natcasesort($views_options);

    return $views_options;
  }

  /**
   * Get an options array of all allowed Views.
   *
   * @return array
   *   The array of options.
   */
  public function getAllowedViewOptions() {
    $allowed_views_options = array_intersect_key($this->getViewOptions(), array_filter($this->getSetting('allowed_views')));
    if (empty($allowed_views_options)) {
      // At this point, empty $allowed_views_options means allow all.
      $allowed_views_options = $this->getViewOptions();
    }

    return $allowed_views_options;
  }

  /**
   * Get allowed display ID options for a view.
   *
   * @param string $entity_id
   *   The entity_id of the view.
   *
   * @return array
   *   The array of options.
   */
  public function getAllowedDisplayOptions($entity_id) {
    $views = Views::getEnabledViews();
    $allowed_display_types = array_filter($this->getSetting('allowed_display_types'));
    $view_display_options = array();
    if (isset($views[$entity_id])) {
      foreach ($views[$entity_id]->get('display') as $display_id => $display) {
        if (empty($allowed_display_types) || isset($allowed_display_types[$display['display_plugin']])) {
          $view_display_options[$display_id] = FieldFilteredMarkup::create($display['display_title']);
        }
      }
      natcasesort($view_display_options);
    }

    return $view_display_options;
  }

  /**
   * Get an options array of all Views display types.
   *
   * @return array
   *   The array of options.
   */
  public function getDisplayTypeOptions() {
    $display_type_options = array();
    foreach (Views::pluginList() as $key => $type) {
      if ($type['type'] == 'display') {
        $display_type_options[str_replace('display:', '', $key)] = FieldFilteredMarkup::create($type['title']);
      }
    }
    natcasesort($display_type_options);

    return $display_type_options;
  }
}
