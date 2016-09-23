<?php

namespace Drupal\viewfield\Plugin\views\display;

use Drupal\views\Plugin\views\display\DisplayPluginBase;

/**
 * The plugin that handles an embed display.
 *
 * @ingroup views_display_plugins
 *
 * @todo: Wait until annotations/plugins support access methods.
 * no_ui => !\Drupal::config('views.settings')->get('ui.show.display_embed'),
 *
 * @ViewsDisplay(
 *   id = "viewfield",
 *   title = @Translation("Viewfield"),
 *   help = @Translation("Provide a display which can be embedded using the views api."),
 *   theme = "views_view",
 *   uses_menu_links = FALSE
 * )
 */
class ViewFieldDisplay extends DisplayPluginBase {

  public function displaysExposed() {
    return FALSE;
  }

}
