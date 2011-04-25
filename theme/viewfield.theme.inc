<?php

/**
 * @file
 * Theme functions.
 */

/**
 * Return a themed view avoiding viewfield recursion.
 */
function theme_viewfield_formatter_default($variables) {
  // $_viewfield_stack keeps a record of the current entity to prevent infinite
  // recursion during the view rendering process. See also
  // viewfield_views_query_alter() in includes/viewfield.views.inc.
  global $_viewfield_stack;
  if (empty($_viewfield_stack)) {
    $_viewfield_stack = _viewfield_stack_create();
  }

  $item = $variables['item'];
  $entity_type = $variables['entity_type'];
  $entity = $variables['entity'];
  $entity_info = entity_get_info($entity_type);
  $entity_id_key = $entity_info['entity keys']['id'];
  $entity_id = $entity->{$entity_id_key};

  if (!empty($item['vname']) && _viewfield_stack_check($_viewfield_stack, $entity_type, $entity_id)) {
    // Push id of current entity unless it's a new entity being previewed.
    if ($entity_id) {
      _viewfield_stack_push($_viewfield_stack, $entity_type, $entity_id_key, $entity_id);
    }
    list($view_name, $display) = explode('|', $item['vname'], 2);
    $view_args = _viewfield_get_view_args($item['vargs'], $entity_type, $entity_id_key, $entity);

    // Render the view like Views would do.
    // @see views_embed_view()
    $view = views_get_view($view_name);
    if ($view && $view->access($display)) {
      // Override the view's path to the current path. Otherwise, exposed views
      // filters would submit to the front page.
      $view->override_path = $_GET['q'];

      $output = $view->preview($display, $view_args);
    }

    // Pop current entity.
    if ($entity_id) {
      _viewfield_stack_pop($_viewfield_stack, $entity_type);
    }

    // Only return an actual view result to not break empty value behavior.
    if (isset($output)) {
      return $output;
    }
  }
}

/**
 * Perform argument replacement
 */
function _viewfield_get_view_args($vargs, $entity_type, $entity_id_key, $entity) {
  $args = array();
  // Prevent token_replace() from running this function a second time before it
  // completes the first time.
  static $replacing_tokens = FALSE;
  if (!$replacing_tokens && !empty($vargs)) {
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

    $replacing_tokens = TRUE;
    $token_data = array($entity_type => $entity);
    $entity_loaded = FALSE;
    foreach ($args as $key => $value) {
      // Load the full entity only when required.
      if (!$entity_loaded && _viewfield_token_requires_load($value, $entity_type, $entity_id_key)) {
        $id = $entity->{$entity_id_key};
        $entities = entity_load($entity_type, array($id));
        $token_data = array($entity_type => $entities[$id]);
        $entity_loaded = TRUE;
      }
      $args[$key] = token_replace($value, $token_data);
    }
    $replacing_tokens = FALSE;
  }
  return $args;
}

/**
 *  Detect if a token requires a full entity to be loaded.
 *
 *  We conservatively require a load if a token refers to the entity type but
 *  the token key is not the entity id. For example, if the $entity_type == '
 *  node' and $entity_id_key == 'nid', we are searching for tokens of the
 *  form [node:some-key] where some-key != 'nid'.
 *  Global tokens (e.g., [current-user:uid]) never require entity loading.
 */
function _viewfield_token_requires_load($text, $entity_type, $entity_id_key) {
  $pattern = '/^\[' . $entity_type . ':(.*)\]$/';
  if (preg_match($pattern, $text, $matches)) {
    return ($entity_id_key !== $matches[1]);
  }
  return FALSE;
}

function theme_viewfield_select($element) {
  if (!empty($element['#children'])) {
    $field = $element['#field'];
    if ($field['multiple'] && $element['#delta'] == 0) {
      // This is needed only for multiple viewfields.
      drupal_add_css(drupal_get_path('module', 'viewfield') . '/theme/viewfield.css');
    }
    return '<div class="viewfield-select">' . $element['#children'] . '</div>';
  }
}
