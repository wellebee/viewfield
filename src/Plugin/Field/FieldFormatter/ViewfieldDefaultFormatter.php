<?php

/**
 * @file
 * Contains \Drupal\viewfield\Plugin\Field\FieldFormatter\ViewfieldDefaultFormatter.
 */


namespace Drupal\viewfield\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\views\Views;

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
      /* @var $item \Drupal\Core\Field\FieldItemBase */
      /* @var $entity \Drupal\Core\Entity\Entity */
      $entity = $item->getEntity();
      list($view_name, $view_display) = explode('|', $item->vname, 2);
      $view = Views::getView($view_name);
      $view_args = $this->getViewArgs($item->vargs, $entity);
      // Build the view display's renderable array per item.
      $elements[$delta] = $view->buildRenderable($view_display, $view_args);
      $elements[$delta]['#access'] = $view && $view->access($view_display);
    }
    return $elements;
  }

  /**
   * Parse argument string into an array and perform token replacements.
   *
   * @param string $view_args
   *   The argument string from the entity edit/add form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to which the field is attached.
   *
   * @return array
   *   List of arguments ready to be passed to a rendering method for a view.
   */
  protected function getViewArgs($view_args, EntityInterface $entity) {
    $args = array();
    $token_data = array($entity->getEntityTypeId() => $entity);

    if (!empty($view_args)) {
      $pos = 0;
      while ($pos < strlen($view_args)) {
        $found = FALSE;
        // If string starts with a quote, start after quote and get everything
        // before next quote.
        if (strpos($view_args, '"', $pos) === $pos) {
          if (($quote = strpos($view_args, '"', ++$pos)) !== FALSE) {
            // Skip pairs of quotes.
            while (!(($ql = strspn($view_args, '"', $quote)) & 1)) {
              $quote = strpos($view_args, '"', $quote + $ql);
            }
            $args[] = str_replace('""', '"', substr($view_args, $pos, $quote + $ql - $pos - 1));
            $pos = $quote + $ql + 1;
            $found = TRUE;
          }
        }
        elseif (($comma = strpos($view_args, ',', $pos)) !== FALSE) {
          // Otherwise, get everything before next comma.
          $args[] = substr($view_args, $pos, $comma - $pos);
          // Skip to after comma and repeat
          $pos = $comma + 1;
          $found = TRUE;
        }
        if (!$found) {
          $args[] = substr($view_args, $pos);
          $pos = strlen($view_args);
        }
      }

      $token = \Drupal::token();
      foreach ($args as $key => $value) {
        $args[$key] = $token->replace($value, $token_data);
      }
    }

    return $args;
  }

}
