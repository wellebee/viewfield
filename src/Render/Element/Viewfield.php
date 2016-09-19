<?php

/**
 * @file
 * Contains \Drupal\Core\Render\Element\Viewfield.
 */

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Element;

/**
 * Renders a view of the selected view.
 *
 * @RenderElement("viewfield")
 */
class Viewfield extends RenderElement {

  public function getInfo() {
    $class = get_class($this);
    return array(
      '#input' => TRUE,
      '#pre_render' => array(
        array($class, 'preRenderTextfield'),
      ),
      '#theme' => 'viewfield_formatter_default',
      '#post_render' => array(
        array($class, 'postRenderViewfield'),
      ),
    );
  }

  public static function preRenderViewfield($element) {
    $stack = &drupal_static('viewfield_stack', array());

    // Abort rendering in case the view could not be loaded.
    if (empty($element['#view'])) {
      // @todo Output an error message?
      $element['#printed'] = TRUE;
    }
    // Abort rendering in case of recursion.
    elseif (isset($stack[$element['#entity_type']][$element['#entity_id']])) {
      $element['#printed'] = TRUE;
    }
    // Otherwise, add the rendered entity to the stack to prevent recursion.
    else {
      $stack[$element['#entity_type']][$element['#entity_id']] = TRUE;

      // Override the view's path to the current path. Otherwise, exposed
      // views filters would submit to the front page.
      $url = Url::fromRoute('<current>');
      $current_path = $url->toString();
      $element['#view']->override_path = $current_path;

      // @todo Store views arguments serialized.
      $element['#view_arguments'] = Viewfield::getViewArgs($element['#view_arguments'], $element['#entity_type'], $element['#entity']);
    }
    return $element;
  }

  public static function postRenderViewfield($content, $element) {
    $stack = &drupal_static('viewfield_stack', array());
    unset($stack[$element['#entity_type']][$element['#entity_id']]);
    return $content;
  }

  /**
   * Perform argument replacement
   */
  protected function getViewArgs($vargs, $entity_type, $entity) {
    $args = array();

    if (!empty($vargs)) {
      $pos = 0;
      while ($pos < strlen($vargs)) {
        $found = FALSE;
        // If string starts with a quote, start after quote and get everything
        // before next quote.
        if (strpos($vargs, '"', $pos) === $pos) {
          if (($quote = strpos($vargs, '"', ++$pos)) !== FALSE) {
            // Skip pairs of quotes.
            while (!(($ql = strspn($vargs, '"', $quote)) & 1)) {
              $quote = strpos($vargs, '"', $quote + $ql);
            }
            $args[] = str_replace('""', '"', substr($vargs, $pos, $quote + $ql - $pos - 1));
            $pos = $quote + $ql + 1;
            $found = TRUE;
          }
        }
        elseif (($comma = strpos($vargs, ',', $pos)) !== FALSE) {
          // Otherwise, get everything before next comma.
          $args[] = substr($vargs, $pos, $comma - $pos);
          // Skip to after comma and repeat
          $pos = $comma + 1;
          $found = TRUE;
        }
        if (!$found) {
          $args[] = substr($vargs, $pos);
          $pos = strlen($vargs);
        }
      }

      list($entity_id) = $entity->id();
      $entity = entity_load($entity_type, $entity_id);
      $token_data = array($entity_type => $entity);

      $token = Drupal::token();
      foreach ($args as $key => $value) {
        $args[$key] = $token->replace($value, $token_data);
      }
    }

    return $args;
  }

}
