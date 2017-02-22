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

    $schema['columns']['display_id'] = array(
      'description' => 'The ID of the display.',
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

    $properties['display_id'] = DataDefinition::create('string')
      ->setLabel(t('Display Id'))
      ->setDescription(t('The referenced display ID'));

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
      '#description' => $this->t('Hides this field in forms and enforces the configured default value for all entities in the bundle, making it unnecessary to assign values individually to each one.<br>If this is checked, you must provide a default value.'),
    );

    $form['allowed_views'] = array(
      '#type' => 'checkboxes',
      '#options' => self::getViewsOptions(),
      '#title' => $this->t('Allowed views'),
      '#default_value' => $this->getSetting('allowed_views'),
      '#description' => $this->t('Views available for content authors. Leave empty to allow all.'),
    );

    $form['allowed_display_types'] = array(
      '#type' => 'checkboxes',
      '#options' => self::getDisplayTypeOptions(),
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
  public static function getViewsOptions() {
    $views_options = array();
    foreach (Views::getEnabledViews() as $key => $view) {
      $views_options[$key] = FieldFilteredMarkup::create($view->get('label'));
    }
    natcasesort($views_options);

    return $views_options;
  }

  /**
   * Get an options array of all Views display types.
   *
   * @return array
   *   The array of options.
   */
  public static function getDisplayTypeOptions() {
    $display_type_options = array();
    foreach (Views::pluginList() as $key => $type) {
      if ($type['type'] == 'display') {
        $display_type_options[str_replace('display:', '', $key)] = FieldFilteredMarkup::create($type['title']->render());
      }
    }
    natcasesort($display_type_options);

    return $display_type_options;
  }
}
