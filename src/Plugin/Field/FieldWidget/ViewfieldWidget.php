<?php

/**
 * @file
 * Contains \Drupal\viewfield\Plugin\Field\FieldWidget\ViewfieldWidget.
 */

namespace Drupal\viewfield\Plugin\Field\FieldWidget;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use Drupal\Component\Utility\Html;
use Drupal\views\Views;
use Drupal\Component\Serialization\Json;

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

    $id = Html::getUniqueId('state-wrapper-' . $items->getName() . '-' . $delta);

    $element['#field_name'] = $items->getName();
    $element['vname'] = array(
      '#type' => 'select',
      '#title' => t('Views Field'),
      '#options' => $options,
      '#empty_value' => 0,
      '#ajax' => array(
        'callback' => array($this, 'viewsSettings'),
        'event' => 'change',
        'progress' => array(
          'type' => 'throbber',
          'message' => NULL,
        ),
        'wrapper' => $id,
      ),
      '#access' => !$field_settings['force_default'],
      '#default_value' => isset($items[$delta]->vname) ? $items[$delta]->vname : NULL,
    );

    $element['settings_wrapper'] = array(
      '#type' => 'fieldset',
    );

    $element['settings_wrapper']['settings_wrapper_form'] = array(
      '#type' => 'html_tag',
      '#tag'  => 'div',
    );

    $element['settings_wrapper']['settings_wrapper_form']['settings'] = array(
      '#type' => 'html_tag',
      '#tag'  => 'div',
      '#attributes' => array(
        'id' => $id,
      ),
    );

    if (isset($items[$delta]->vname)) {
      $view = explode('|', $items[$delta]->vname);
      $viewInstance = $this->getView($view[0], $view[1]);
      $itemSettings = [];
      if (!empty($items[$delta]->settings)) {
        $itemSettings = Json::decode($items[$delta]->settings);
      }
      if ($viewInstance) {
        $element['settings_wrapper']['settings_wrapper_form']['settings'] = $this->getViewSettings($viewInstance, $view[1], $itemSettings);
        $element['settings_wrapper']['settings_wrapper_form']['settings']['#attributes']['id'] = $id;
      }
    }

    $element['vargs'] = array(
      '#type' => 'textfield',
      '#title' => t('Arguments'),
      '#default_value' => isset($items[$delta]->vargs) ? $items[$delta]->vargs : NULL,
      '#access' => !$field_settings['force_default'],
      '#description' => t('A comma separated list of arguments to pass to the selected view.'),
    );

    return $element;
  }

  /**
   * Helper function for get exposed filter.
   */
  public function getViewSettings($view, $display, $settings) {
    $form_state = new FormState();
    if ($settings) {
      $form_state->setUserInput($settings);
    }
    $view->initHandlers();
    $form = [];
    // Let form plugins know this is for exposed widgets.
    $form_state->set('exposed', TRUE);
    $form['settings'] = [];

    // Go through each handler and let it generate its exposed widget.
    foreach ($view->display_handler->handlers as $type => $value) {
      /** @var \Drupal\views\Plugin\views\ViewsHandlerInterface $handler */
      foreach ($view->$type as $id => $handler) {
        if ($handler->canExpose() && $handler->isExposed()) {
          if ($handler->isAGroup()) {
            $handler->groupForm($form, $form_state);
            $id = $handler->options['group_info']['identifier'];
          }
          else {
            $handler->buildExposedForm($form, $form_state);
          }
          if ($info = $handler->exposedInfo()) {
            $form['#info']["$type-$id"] = $info;
          }
        }
      }
    }
    if (!empty($settings)) {
      foreach ($settings as $name => $set) {
        if ($form[$name]) {
          $form[$name]['#default_value'] = $set;
        }
      }
    }
    if (isset($form['#info'])) {
      foreach ($form['#info'] as $info) {
        if (isset($form[$info['value']])) {
          $form[$info['value']]['#title'] = $info['label'];
        }
      }
    }

    $exposed_form_plugin = $view->display_handler->getPlugin('exposed_form');
    $exposed_form_plugin->exposedFormAlter($form, $form_state);

    unset($form['actions']);
    return $form;
  }

  public function viewsSettings(array $form, FormStateInterface $form_state) {
    $view = $form_state->getTriggeringElement()['#value'];
    $view = explode('|', $view);
    $viewInstance = $this->getView($view[0], $view[1]);
    if ($viewInstance && is_a($viewInstance->getDisplay(), 'Drupal\viewfield\Plugin\views\display\ViewFieldDisplay')) {
      return $this->getViewSettings($viewInstance, $view[1]);
    }
    return [];
  }

  /**
   * Returns a select options list of views displays of enabled and allowed views.
   *
   * @param array $settings
   *   The field settings.
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
    foreach ($views as $view) {
      $displays = $view->get('display');
      foreach ($displays as $display) {
        $options[$view->id() . '|' . $display['id']] = $view->id() . ' - ' . $display['display_title'];
      }
    }
    return $options;
  }

  /**
   * Helper function for retrieve view.
   *
   * @param string $viewId
   *   View machinable name to load.
   * @param string $display
   *   Display plugin to load.
   */
  public function getView($viewId, $display) {
    $view = Views::getView($viewId);
    if ($view) {
      $view->setDisplay($display);
      return $view;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $formState) {
    foreach ($values as $key => $value) {
      $values[$key]['settings'] = Json::encode($value['settings_wrapper']['settings_wrapper_form']['settings']);
    }
    return $values;
  }

}
