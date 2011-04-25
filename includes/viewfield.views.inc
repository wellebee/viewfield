<?php

/**
 * @file
 * Views functions.
 */

/**
 * Implementation of hook_views_query_alter().
 *
 * Prevent views from loading the node containing the view.
 */
function viewfield_views_query_alter(&$view, &$query) {
  global $_viewfield_stack;

  if (!empty($_viewfield_stack) && isset($query->table_queue['node'])) {
    $field = $query->table_queue['node']['alias'] . '.nid';
    $values = array_values($_viewfield_stack);
    $query->add_where(0, $field, $values, 'not in');
  }
}
