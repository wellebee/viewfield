<?php

namespace Drupal\viewfield\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\viewfield\Plugin\Field\FieldType\ViewfieldItem;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\views\Views;

/**
 * @FieldWidget(
 *   id = "viewfield_select",
 *   label = @Translation("Viewfield select"),
 *   field_types = {"viewfield"}
 * )
 */
class ViewfieldWidgetSelect extends OptionsSelectWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Must always show fields on configuration form.
    $force_default = !$this->isDefaultValueWidget($form_state) ? $this->getFieldSetting('force_default') : FALSE;

    $element = array('target_id' => parent::formElement($items, $delta, $element, $form, $form_state));
    $element['target_id']['#description'] = $this->t('View name.');
    $element['target_id']['#access'] = !$force_default;
    $element['target_id']['#ajax'] = array(
      'callback' => array($this, 'ajaxGetViewDisplayOptions'),
      'event' => 'change',
      'progress' => array(
        'type' => 'throbber',
        'message' => $this->t('Retrieving view displays.'),
      ),
    );

    // Set up options for allowed views.
    $element['target_id']['#multiple'] = FALSE;
    // Always allow '_none' for non-required fields or second and greater delta.
    $views_options = (!$this->fieldDefinition->isRequired() || $delta > 0) ? array('_none' => '- None -') : array();
    $allowed_views_options = array_intersect_key(ViewfieldItem::getViewsOptions(), array_filter($items->getSetting('allowed_views')));
    if (empty($allowed_views_options)) {
      $allowed_views_options = $element['target_id']['#options'];
    }
    $element['target_id']['#options'] = array_merge($views_options, $allowed_views_options);

    // Build an array of keys to retrieve values from $form_state.
    $form_state_keys = array($items->getName(), $delta);
    if (!empty($element['target_id']['#field_parents'])) {
      $form_state_keys = array_merge($element['target_id']['#field_parents'], $form_state_keys);
    }

    // Assign default values.
    $form_state_value = $form_state->getValue($form_state_keys);
    $item_value = $items[$delta]->getValue();
    $display_id_options = NULL;
    $default_display_id = NULL;
    $default_arguments = NULL;
    if (isset($form_state_value['target_id']) || $form_state->getTriggeringElement()) {
      if (isset($form_state_value['target_id'])) {
        $display_id_options = $this->getViewDisplayOptions($form_state_value['target_id']);
        $default_display_id = $form_state_value['display_id'];
        $default_arguments = $form_state_value['arguments'];
      }
    }
    elseif (isset($item_value['target_id'])) {
      $display_id_options = $this->getViewDisplayOptions($item_value['target_id']);
      $default_display_id = $item_value['display_id'];
      $default_arguments = $item_value['arguments'];
    }

    // Construct CSS class to target ajax callback.
    $display_id_class = $this->createDisplayClass($form_state_keys);

    // Construct name of main field used to control visibility.
    $visible_field_name = $form_state_keys[0] . '[' . implode('][', array_slice($form_state_keys, 1)) . '][target_id]';

    $element['display_id'] = array(
      '#title' => 'Display',
      '#type' => 'select',
      '#options' => $display_id_options,
      '#default_value' => $default_display_id,
      '#access' => !$force_default,
      '#description' => $this->t('View display to be used.'),
      '#attributes' => array('class' => array($display_id_class)),
      '#weight' => 10,
      '#states' => array(
        'visible' => array(
          ':input[name="' . $visible_field_name . '"]' => array('!value' => '_none'),
        ),
      ),
    );

    $element['arguments'] = array(
      '#title' => 'Arguments',
      '#type' => 'textfield',
      '#default_value' => $default_arguments,
      '#access' => !$force_default,
      '#description' => $this->t('A comma separated list of arguments to pass to the selected view display.<br>This field supports tokens.'),
      '#weight' => 20,
      '#states' => array(
        'visible' => array(
          ':input[name="' . $visible_field_name . '"]' => array('!value' => '_none'),
        ),
      ),
    );

    if (!$force_default) {
      $element['tokens'] = array(
        '#theme' => 'token_tree_link',
        '#token_types' => array($items->getEntity()->getEntityTypeId()),
        '#weight' => 30,
      );
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $elements = parent::formMultipleElements($items, $form, $form_state);

    $is_multiple = $elements['#cardinality_multiple'];
    $max_delta = $elements['#max_delta'];
    for ($delta = 0; $delta <= $max_delta; $delta++) {
      $element = &$elements[$delta];
      // Change title to 'View #' for multiple values, 'View' for single value.
      if ($is_multiple) {
        $element['target_id']['#title'] = $this->t('View @number', array('@number' => $delta + 1));
        // Force title display.
        $element['target_id']['#title_display'] = 'before';
      }
      else {
        $element['target_id']['#title'] = $this->t('View');
        // Wrap single values in a fieldset unless on the default settings form,
        // as long as the field is visible (!force_default).
        if (!$this->isDefaultValueWidget($form_state) && !$this->getFieldSetting('force_default')) {
          $element += array(
            '#type' => 'fieldset',
            '#title' => $this->fieldDefinition->getLabel(),
          );
        }
      }
    }

    return $elements;
  }

  /**
   * Overridden form validation handler for widget elements.
   *
   * Save selected value as a single item, since there will be at most one.
   * This prevents the target_id value being nested inside $form_state.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @see OptionsWidgetBase::validateElement()
   */
  public static function validateElement(array $element, FormStateInterface $form_state) {
    if ($element['#required'] && $element['#value'] == '_none') {
      $form_state->setError($element, t('@name field is required.', array('@name' => $element['#title'])));
    }

    // Massage submitted form values.
    // Drupal\Core\Field\WidgetBase::submit() expects values as
    // an array of values keyed by delta first, then by column, while our
    // widgets return the opposite.

    if (is_array($element['#value'])) {
      $values = array_values($element['#value']);
    }
    else {
      $values = array($element['#value']);
    }

    // Filter out the 'none' option. Use a strict comparison, because
    // 0 == 'any string'.
    $index = array_search('_none', $values, TRUE);
    if ($index !== FALSE) {
      unset($values[$index]);
    }

    // Transpose selections from field => delta to delta => field.
//    $items = array();
//    foreach ($values as $value) {
//      $items[] = array($element['#key_column'] => $value);
//    }
//    $form_state->setValueForElement($element, $items);

    $element_value = !empty($values[0]) ? $values[0] : NULL;
    $form_state->setValueForElement($element, $element_value);
  }

  /**
   *  Ajax callback to retrieve display IDs.
   *
   * @param array $form
   *   The form from which the display IDs are being requested.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   */
  public function ajaxGetViewDisplayOptions(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $form_state_keys = array_slice($trigger['#parents'], 0, -1);
    $form_state_value = $form_state->getValue($form_state_keys);

    $display_options = $this->getViewDisplayOptions($form_state_value['target_id']);
    $html = '';
    foreach ($display_options as $key => $value) {
      $html .= '<option value="' . $key . '">' . $value . '</option>';
    }
    $html = '<optgroup>' . $html . '</optgroup>';

    // Create a class selector for ajax response.
    $selector = '.' . $this->createDisplayClass($form_state_keys);
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand($selector, $html));

    return $response;
  }

  /**
   * Get display ID options for a view.
   *
   * @param string $entity_id
   *   The entity_id of the view.
   *
   * @return array
   *   The array of options.
   */
  protected function getViewDisplayOptions($entity_id) {
    $views = Views::getEnabledViews();
    $view_display_options = array();
    if (isset($views[$entity_id])) {
      $allowed_display_types = array_filter($this->getFieldSetting('allowed_display_types'));
      foreach ($views[$entity_id]->get('display') as $display_id => $display) {
        if (empty($allowed_display_types) || isset($allowed_display_types[$display['display_plugin']])) {
          $view_display_options[$display_id] = $display['display_title'];
        }
      }
      natcasesort($view_display_options);
    }

    return $view_display_options;
  }

  /**
   * Produce a class for a display input field.
   *
   * @param array $components
   *   An array of class components to be concatenated.
   *
   * @return string
   *   The display input field class.
   */
  protected function createDisplayClass($components) {
    return implode('-', $components) . '-display-id';
  }
}
