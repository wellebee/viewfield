<?php

/**
 * @file
 * Contains \Drupal\viewfield\Plugin\Field\FieldWidget\ViewfieldWidget.
 */

namespace Drupal\viewfield\Plugin\Field\FieldWidget;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\views\Views;

/**
 * Plugin implementation of the 'viewfield' widget.
 *
 * @FieldWidget(
 *   id = "viewfield_select",
 *   label = @Translation("Select List"),
 *   field_types = {
 *     "viewfield"
 *   }
 * )
 */
class ViewfieldWidget extends WidgetBase {


  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $field_settings = $this->getFieldSettings();
    $options = $this->getPotentialReferences($field_settings);

    $element['#field_name'] = $items->getName();
    $element['vname'] = array(
      '#type' => 'select',
      '#title' => t('Views Field'),
      '#options' => $options,
      '#empty_value' => 0,
      '#access' => !$field_settings['force_default'],
      '#default_value' => isset($items[$delta]->vname) ? $items[$delta]->vname : NULL,
    );
    $element['vargs'] = array(
      '#type' => 'textfield',
      '#title' => t('Arguments'),
      '#default_value' => isset($items[$delta]->vargs) ? $items[$delta]->vargs : NULL,
      '#access' => !$field_settings['force_default'],
      '#description' => t('A comma separated list of arguments to pass to the selected view. '),
    );

    return $element;
  }

  /**
   * Returns a select options list of views displays of enabled and allowed views.
   *
   * @param array @settings
   *   The field settings
   *
   * @return array
   *   An array with the allowed and enabled views and displays.
   */
  protected function getPotentialReferences($settings) {
    // Retrieve all currently available views.
    $views = Views::getEnabledViews();
    // Limit to allowed values, if any.
    if (isset($settings['allowed_views']) && is_array($settings['allowed_views'])) {
      // Only intersect if at least one view has been enabled; otherwise, we would
      // end up with empty $views.
      if ($allowed = array_filter($settings['allowed_views'])) {
        $views = array_intersect_key($views, $allowed);
      }
    }
    $options = array();
    foreach ($views as $view_name => $view) {
      $displays = $view->get('display');
      foreach ($displays as $display) {
        $options[$view->id() . '|' . $display['id']] = $view->id() . ' - ' . $display['display_title'];
      }
    }
    return $options;
  }

}
