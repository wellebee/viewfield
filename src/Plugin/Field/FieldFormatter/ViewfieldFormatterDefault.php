<?php

namespace Drupal\viewfield\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
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
  public static function defaultSettings() {
    return array(
      'always_build_output' => 0,
      'hide_field_label' => 0,
      'include_view_title' => 0,
      'show_empty_view_title' => 0,
    ) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['always_build_output'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Always build output'),
      '#default_value' => $this->getSetting('always_build_output'),
      '#description' => $this->t('Produce rendered output even if the view produces no results.<br>This option may be useful for some specialized cases, e.g., to force rendering of an attachment display even if there are no view results.'),
    );

    $form['hide_field_label'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Hide field label'),
      '#default_value' => $this->getSetting('hide_field_label'),
      '#description' => $this->t('Hide the label (name) of the field when the field is rendered.<br>This option may be useful when including view display titles.'),
    );

    $form['include_view_title'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Include view title'),
      '#default_value' => $this->getSetting('include_view_title'),
      '#description' => $this->t('Include the view display title in the output.'),
    );

    $form['show_empty_view_title'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Show empty view title'),
      '#default_value' => $this->getSetting('show_empty_view_title'),
      '#description' => $this->t('Show the view title even when the view produces no results.<br>This option has an effect only when <em>Always build output</em> is also selected.'),
      '#states' => array('visible' => array(':input[name="settings[include_view_title]"]' => array('checked' => TRUE))),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getSettings();
    $summary = array();

    $summary[] = $this->t('Always build output: @always_build_output', array(
      '@always_build_output' => $this->getCheckboxLabel($settings['always_build_output']),
    ));
    $summary[] = $this->t('Hide field label: @hide_field_label', array(
      '@hide_field_label' => $this->getCheckboxLabel($settings['hide_field_label']),
    ));
    $summary[] = $this->t('Include view title: @include_view_title', array(
      '@include_view_title' => $this->getCheckboxLabel($settings['include_view_title']),
    ));
    $summary[] = $this->t('Show empty view title: @show_empty_view_title', array(
      '@show_empty_view_title' => $this->getCheckboxLabel($settings['show_empty_view_title']),
    ));

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function view(FieldItemListInterface $items, $langcode = NULL) {
    $elements = parent::view($items, $langcode);
    if ($this->getSetting('hide_field_label')) {
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

    $always_build_output = $this->getSetting('always_build_output');
    $include_view_title = $this->getSetting('include_view_title');
    $show_empty_view_title = $this->getSetting('show_empty_view_title');
    $elements = array();
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
        $elements[$delta] = array(
          '#theme' => 'viewfield_item',
          '#content' => $view->buildRenderable($display_id, $arguments),
          '#delta' => $delta,
        );
        if ($include_view_title && (!empty($view->result) || $show_empty_view_title)) {
          $elements[$delta]['#title'] = $view->getTitle() ?: NULL;
        }
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

  /**
   * Get a printable label for a checkbox value.
   *
   * @param string $value
   *   The checkbox value.
   *
   * @return string
   *   The label for the checkbox value.
   */
  protected function getCheckboxLabel($value) {
    return !empty($value) ? $this->t('Yes') : $this->t('No');
  }
}
