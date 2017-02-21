<?php

namespace Drupal\viewfield\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\views\Views;

/**
 *
 * @FieldFormatter(
 *   id = "viewfield_default",
 *   label = @Translation("Viewfield"),
 *   field_types = {"viewfield"}
 * )
 */
class ViewfieldFormatterDefault extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function view(FieldItemListInterface $items, $langcode = NULL) {
    $elements = parent::view($items, $langcode);
    if ($this->getFieldSetting('hide_field_label')) {
      $elements['#label_display'] = 'hidden';
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $entity = $items->getEntity();

    if ($this->getFieldSetting('force_default')) {
      $values = $this->fieldDefinition->getDefaultValue($entity);
    }
    else {
      $values = array();
      foreach ($items as $delta => $item) {
        $values[$delta] = $item->getValue();
      }
    }

    // @todo Design and implement a caching strategy.
    $elements = array(
      '#cache' => array(
        'max-age' => 0,
      ),
    );

    $always_build_output = $this->getFieldSetting('always_build_output');
    foreach ($values as $delta => $value) {
      $target_id = $value['target_id'];
      $display_id = $value['display_id'];
      $arguments = $arguments = $this->processArguments($value['arguments'], $entity);

      // @see views_embed_view()
      // @see views_get_view_result()
      $view = Views::getView($target_id);
      if (!$view || !$view->access($display_id)) {
        continue;
      }

      $view->setArguments($arguments);
      $view->setDisplay($display_id);
      $view->preExecute();
      $view->execute();

      if ($always_build_output || !empty($view->result)) {
        $elements[$delta] = $view->buildRenderable($display_id, $arguments);
      }
    }

    return $elements;
  }

  /**
   * Perform argument parsing and token replacement.
   *
   * @param string $argument_string
   *   The raw argument string.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity containing this field.
   *
   * @return array
   *   The array of processed arguments.
   */
  protected function processArguments($argument_string, $entity) {
    $arguments = array();

    if (!empty($argument_string)) {
      $pos = 0;
      while ($pos < strlen($argument_string)) {
        $found = FALSE;
        // If string starts with a quote, start after quote and get everything
        // before next quote.
        if (strpos($argument_string, '"', $pos) === $pos) {
          if (($quote = strpos($argument_string, '"', ++$pos)) !== FALSE) {
            // Skip pairs of quotes.
            while (!(($ql = strspn($argument_string, '"', $quote)) & 1)) {
              $quote = strpos($argument_string, '"', $quote + $ql);
            }
            $arguments[] = str_replace('""', '"', substr($argument_string, $pos, $quote + $ql - $pos - 1));
            $pos = $quote + $ql + 1;
            $found = TRUE;
          }
        }
        elseif (($comma = strpos($argument_string, ',', $pos)) !== FALSE) {
          // Otherwise, get everything before next comma.
          $arguments[] = substr($argument_string, $pos, $comma - $pos);
          // Skip to after comma and repeat
          $pos = $comma + 1;
          $found = TRUE;
        }
        if (!$found) {
          $arguments[] = substr($argument_string, $pos);
          $pos = strlen($argument_string);
        }
      }

      $token_service = \Drupal::token();
      $token_data = array($entity->getEntityTypeId() => $entity);
      foreach ($arguments as $key => $value) {
        $arguments[$key] = $token_service->replace($value, $token_data);
      }
    }

    return $arguments;
  }
}
