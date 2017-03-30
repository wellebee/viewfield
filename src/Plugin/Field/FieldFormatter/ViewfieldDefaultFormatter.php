<?php

/**
 * @file
 * Contains \Drupal\viewfield\Plugin\Field\FieldFormatter\ViewfieldDefaultFormatter.
 */


namespace Drupal\viewfield\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\views\Views;
use Drupal\Component\Serialization\Json;

/**
 * Plugin implementation of the 'viewfield_default' formatter.
 *
 * @FieldFormatter(
 *   id = "viewfield_default",
 *   label = @Translation("Viewfield default formatter"),
 *   field_types = {
 *     "viewfield"
 *   }
 * )
 */
class ViewfieldDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = array();

    foreach ($items as $delta => $item) {
      $entity = $item->getEntity();
      list($view_name, $view_display) = explode('|', $item->vname, 2);
      $view = Views::getView($view_name);
      $settings = [];
      if (!empty($item->settings)) {
        $settings = Json::decode($item->settings);
      }
      $elements[$delta] = array(
        '#type' => 'viewfield',
        '#view' => $view,
        '#access' => $view && $view->access($view_display),
        '#view_name' => $view_name,
        '#view_display' => $view_display,
        '#view_arguments' => $item->vargs,
        '#entity_type' => $entity->getEntityTypeId(),
        '#entity_id' => $entity->id(),
        '#entity' => $entity,
        '#exposed_settings' => $settings,
        '#theme' => 'viewfield_formatter_default',
      );
    }
    return $elements;
  }

}
