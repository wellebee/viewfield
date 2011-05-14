<?php

/**
 * @file
 * Views functions.
 */

/**
 * Implements hook_views_query_alter().
 *
 * Prevent views from loading the node containing the view.
 */
function viewfield_views_query_alter(&$view, &$query) {
  global $_viewfield_stack;
  if (!empty($_viewfield_stack)) {
    foreach ($_viewfield_stack as $entity_type => $entity_ids) {
      $entity_info = entity_get_info($entity_type);
      if (!empty($entity_ids) && isset($query->table_queue[$entity_info['base table']])) {
        $field = $query->table_queue[$entity_info['base table']]['alias'] . '.' . $entity_info['entity keys']['id'];
        $values = $entity_ids;
        $query->add_where(0, $field, $values, 'not in');
      }
    }
  }
}
