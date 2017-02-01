<?php

/**
 * @file
 * Contains \Drupal\viewfield\Plugin\Field\FieldType\ViewfieldItem.
 */

namespace Drupal\viewfield\Plugin\Field\FieldType;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Views;

/**
 * Plugin implementation of the 'viewfield' field type.
 *
 * @FieldType(
 *   id = "viewfield",
 *   label = @Translation("Viewfield"),
 *   description = @Translation("Viewfield field type. Stores view name and arguments."),
 *   default_widget = "viewfield_select",
 *   default_formatter = "viewfield_default"
 * )
 */
class ViewfieldItem extends FieldItemBase {

  static $propertyDefinitions;

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('vname')->getValue();
    return empty($value);
  }


  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return array(
      'force_default' => 0,
      'allowed_views' => array(),
    ) + parent::defaultFieldSettings();
  }


  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return array(
      'columns' => array(
        'vname' => array(
          'type' => 'varchar',
          'not null' => FALSE,
          'length' => 128,
        ),
        'vargs' => array(
          'type' => 'varchar',
          'not null' => FALSE,
          'length' => 255,
        ),
        'settings' => array(
          'type' => 'text',
          'size' => 'normal',
        ),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['vname'] = DataDefinition::create('string')
      ->setLabel(t('View name'));
    $properties['vargs'] = DataDefinition::create('string')
      ->setLabel(t('View args'));
    $properties['settings'] = DataDefinition::create('string')
      ->setLabel(t('View settings'));
    return $properties;
  }

 /**
  * {@inheritdoc}
  */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $form = array(
      '#element_validate' => array(array(get_class($this), 'fieldSettingsFormValidate')),
    );
    $enabled_views = array_keys(Views::getEnabledViews());

    $form['force_default'] = array(
      '#type'          => 'checkbox',
      '#title'         => t('Always use default value'),
      '#default_value' => $this->getSetting('force_default'),
      '#description'   => t('Hides this field in forms and enforces the configured default value. If this is checked, you must provide a default value.'),
    );

    $form['allowed_views'] = array(
      '#type'          => 'checkboxes',
      '#title'         => t('Allowed values'),
      '#options'       => array_combine($enabled_views, $enabled_views),
      '#default_value' => $this->getSetting('allowed_views'),
      '#description'   => t('Only selected views will be available for content authors. Leave empty to allow all.'),
    );

    return $form;
  }

  /**
   * Form element validation handler for field instance form.
   */
  public static function fieldSettingsFormValidate(array $form, FormStateInterface $form_state) {
    $force_default = $form_state->getValue(array('settings', 'force_default'));
    if ($force_default) {
      /**
       * @var \Drupal\Core\Field\FieldConfigBase
       */
      $field = $form_state->getFormObject()->getEntity();
      $default_value_vname = $form_state->getValue(array('default_value_input', $field->getName(), 0, 'vname'));
      if (empty($default_value_vname)) {
        $form_state->setErrorByName('default_value_input', t('%title requires a default value.', array(
          '%title' => $form['force_default']['#title'],
        )));
      }
    }
  }

}
