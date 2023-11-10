<?php

namespace Drupal\viewfield\Plugin\Field\FieldWidget;

use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;

/**
 * Plugin implementation of the 'viewfield_select' widget.
 *
 * @FieldWidget(
 *   id = "viewfield_select",
 *   label = @Translation("Viewfield"),
 *   field_types = {"viewfield"}
 * )
 */
class ViewfieldWidgetSelect extends OptionsSelectWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $field_type = $this->fieldDefinition->getType();
    $item = $items[$delta];

    $element['target_id'] = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['target_id']['#field_type'] = $field_type;
    $element['target_id']['#field_item'] = $item;
    $element['target_id']['#description'] = $this->t('View name.');
    $element['target_id']['#ajax'] = [
      'callback' => [$this, 'ajaxGetDisplayOptions'],
      'event' => 'change',
      'progress' => [
        'type' => 'throbber',
        'message' => $this->t('Retrieving view displays.'),
      ],
    ];

    // Set up options for allowed views.
    $empty_label = $this->getEmptyLabel() ?: $this->t('- None -');
    // Always allow '_none' for non-required fields or second and greater delta.
    $none_option = (!$this->fieldDefinition->isRequired() || $delta > 0) ? ['_none' => $empty_label] : [];
    $element['target_id']['#options'] = array_merge($none_option, $item->getViewOptions());
    $element['target_id']['#multiple'] = FALSE;

    // Build an array of keys to retrieve values from $form_state.
    $form_state_keys = [$this->fieldDefinition->getName(), $delta];
    if (!empty($form['#parents'])) {
      $form_state_keys = array_merge($form['#parents'], $form_state_keys);
    }

    // Assign default values.
    $default_target_id = NULL;
    $display_id_options = NULL;
    $default_display_id = NULL;
    $default_arguments = NULL;
    $default_items_to_display = NULL;
    $item_value = $item->getValue();

    // Set initial default values from the item if they exist.
    if (isset($item_value['target_id'])) {
      $default_target_id = $item_value['target_id'];
      $display_id_options = $item->getDisplayOptions($item_value['target_id']);
      $default_display_id = $item_value['display_id'];
      $default_arguments = $item_value['arguments'];
      $default_items_to_display = $item_value['items_to_display'];
    }

    // Set default values based on values from form_state, if they exist.
    $values = $form_state->getValue($this->fieldDefinition->getName());
    if (!empty($values[$delta])) {
      $default_target_id = $values[$delta]['target_id'];
      $default_display_id = $values[$delta]['display_id'];
      $default_arguments = $values[$delta]['arguments'];
      $display_id_options = $item->getDisplayOptions($default_target_id);
      $default_items_to_display = $values[$delta]['items_to_display'];
    }

    // #default_value needs special handling, otherwise it consists of an array
    // of values corresponding to field items, one for each #delta.
    $element['target_id']['#default_value'] = $default_target_id;

    // Construct CSS class to target ajax callback.
    $display_id_class = $this->createDisplayClass($form_state_keys);

    // Use primary target_id field to control visibility of secondary ones.
    $field_key =  $form_state_keys[0] . '[' . implode('][', array_slice($form_state_keys, 1)) . ']';
    $primary_field_name = $field_key . '[target_id]';
    $primary_field_visible_test = [':input[name="' . $primary_field_name . '"]' => ['!value' => '_none']];

    $element['display_id'] = [
      '#title' => $this->t('Display'),
      '#type' => 'select',
      '#options' => $display_id_options,
      '#default_value' => $default_display_id,
      '#description' => $this->t('View display to be used.'),
      '#attributes' => ['class' => [$display_id_class]],
      '#weight' => 10,
      '#states' => ['visible' => $primary_field_visible_test],
    ];

    $element['view_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced options'),
      '#weight' => 20,
      '#open' => false,
      '#parents' => $form_state_keys,
      '#states' => [
        'open' => [
          [':input[name="' . $field_key .'[arguments]"]' => ['filled' => TRUE]],
          [':input[name="' . $field_key .'[items_to_display]"]' => ['filled' => TRUE]],
        ],
        'visible' => $primary_field_visible_test,
      ],
    ];

    $element['view_options']['arguments'] = [
      '#title' => $this->t('Arguments'),
      '#type' => 'textfield',
      '#default_value' => $default_arguments,
      '#description' => $this->t('Separate contextual filters with a "/". Each filter may use "+" or "," for multi-value arguments.<br>This field supports tokens.'),
      '#weight' => 20,
      '#states' => ['visible' => $primary_field_visible_test],
      '#maxlength' => 255,
    ];

    // Get entity types from the $items.
    $entity_type = $items->getEntity()->getEntityTypeId();

    // Token expects the value "term" for taxonomy_term entities.
    if ($entity_type == 'taxonomy_term') {
      $entity_type = 'term';
    }

    $element['view_options']['token_help'] = [
      '#type' => 'item',
      '#weight' => 30,
      '#states' => ['visible' => $primary_field_visible_test],
      'tokens' => [
        '#theme' => 'token_tree_link',
        '#token_types' => [$entity_type],
      ],
    ];

    $element['view_options']['items_to_display'] = [
      '#title' => $this->t('Items to display'),
      '#type' => 'textfield',
      '#default_value' => $default_items_to_display,
      '#description' => $this->t('Override the number of items to display. This also disables the pager if one is configured. Leave empty for default limit.'),
      '#weight' => 40,
      '#states' => ['visible' => $primary_field_visible_test],
    ];

    $element['#attached']['library'][] = 'viewfield/viewfield';

    return $element;
  }
  
  /**
   * {@inheritdoc}
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $elements = parent::formMultipleElements($items, $form, $form_state);
    // Must always show fields on configuration form.
    if (!$this->isDefaultValueWidget($form_state) && $this->getFieldSetting('force_default')) {
      $elements['#access'] = FALSE;
    }

    $max_delta = $elements['#max_delta'];
    $is_multiple = $elements['#cardinality_multiple'];

    if ($is_multiple) {
      for ($delta = 0; $delta <= $max_delta; $delta++) {
        $element = &$elements[$delta];
        // Change title to 'View #'.
        $element['target_id']['#title'] = $this->t('View @number', ['@number' => $delta + 1]);
        // Force title display.
        $element['target_id']['#title_display'] = 'before';
      }
    }
    else {
      // $max_delta == 0 for this case.
      $element = &$elements[0];
      // Change title to simply 'View'.
      $element['target_id']['#title'] = $this->t('View');
      // Wrap single values in a fieldset unless on the default settings form,
      // as long as the field is visible (!force_default).
      if (!$this->isDefaultValueWidget($form_state) && !$this->getFieldSetting('force_default')) {
        $element += [
          '#type' => 'fieldset',
          '#title' => $this->fieldDefinition->getLabel(),
        ];
      }
    }

    return $elements;
  }

  /**
   * Overridden form validation handler for widget elements.
   *
   * Save selected target_id as a single item, since there will be at most one.
   * This prevents the value from being deeply nested inside $form_state.
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
      $form_state->setError($element, t('@name field is required.', ['@name' => $element['#title']]));
    }

    // Massage submitted form values.
    // Drupal\Core\Field\WidgetBase::submit() expects values as
    // an array of values keyed by delta first, then by column, while our
    // widgets return the opposite.
    if (is_array($element['#value'])) {
      $values = array_values($element['#value']);
    }
    else {
      $values = [$element['#value']];
    }

    // Filter out the 'none' option. Use a strict comparison, because
    // 0 == 'any string'.
    $index = array_search('_none', $values, TRUE);
    if ($index !== FALSE) {
      unset($values[$index]);
    }

    $target_id = !empty($values[0]) ? $values[0] : NULL;
    $form_state->setValueForElement($element, $target_id);
  }

  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $values = parent::massageFormValues($values, $form, $form_state); // TODO: Change the autogenerated stub
    foreach ($values as $key => $value) {
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state) {
    $element = $element;
    return $element['target_id'];
  }

  /**
   * Ajax callback to retrieve display IDs.
   *
   * @param array $form
   *   The form from which the display IDs are being requested.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   */
  public function ajaxGetDisplayOptions(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $form_state_keys = array_slice($trigger['#parents'], 0, -1);
    $form_state_value = $form_state->getValue($form_state_keys);
    $display_options = $trigger['#field_item']->getDisplayOptions($form_state_value['target_id']);

    $html = '';
    foreach ($display_options as $key => $value) {
      $html .= '<option value="' . $key . '">' . $value . '</option>';
    }
    $html = '<optgroup>' . $html . '</optgroup>';

    // Create a class selector for Ajax response.
    $selector = '.' . $this->createDisplayClass($form_state_keys);
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand($selector, $html));
    $response->addCommand(new InvokeCommand($selector, 'ajaxEnableElements'));
    $form_state->setRebuild(TRUE);

    return $response;
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
  protected function createDisplayClass(array $components) {
    return implode('-', $components) . '-display-id';
  }

}
