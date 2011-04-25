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
    foreach ($_viewfield_stack['entity_stacks'] as $entity_type => $entity_type_entry) {
      if (isset($query->table_queue[$entity_type])) {
        $field = $query->table_queue[$entity_type]['alias'] . '.' . $entity_type_entry['entity_id_key'];
        $values = $entity_type_entry['entity_id_set'];
        $query->add_where(0, $field, $values, 'not in');
      }
    }
  }
}
