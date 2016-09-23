<?php

namespace Drupal\viewfield\Plugin\views\exposed_form;

use Drupal\views\Plugin\views\exposed_form\Basic;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;

/**
 * Exposed form plugin that provides a basic exposed form.
 *
 * @ingroup views_exposed_form_plugins
 *
 * @ViewsExposedForm(
 *   id = "viewfield_exposed_form",
 *   title = @Translation("Viewfield Form"),
 *   help = @Translation("Only for field settings")
 * )
 */
class ViewFieldExposedForm extends Basic {
  /**
   * Render the exposed filter form.
   *
   * This actually does more than that; because it's using FAPI, the form will
   * also assign data to the appropriate handlers for use in building the
   * query.
   */
  public function renderExposedForm($block = FALSE) {
    //return [];
    // Deal with any exposed filters we may have, before building.

    $form_state = (new FormState())
      ->setStorage([
        'view' => $this->view,
        'display' => &$this->view->display_handler->display,
        'rerender' => TRUE,
      ])
      ->setMethod('get')
      ->setAlwaysProcess()
      ->disableRedirect();

    // Some types of displays (eg. attachments) may wish to use the exposed
    // filters of their parent displays instead of showing an additional
    // exposed filter form for the attachment as well as that for the parent.
    if (!$this->view->display_handler->displaysExposed() || ($this->view->display_handler->getOption('exposed_block'))) {
      $form_state->set('rerender', NULL);
    }

    $form = \Drupal::formBuilder()->buildForm('\Drupal\views\Form\ViewsExposedForm', $form_state);
    return [];
  }


}
