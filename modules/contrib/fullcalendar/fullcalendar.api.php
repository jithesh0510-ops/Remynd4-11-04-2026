<?php

/**
 * @file
 * Hooks provided by the FullCalendar module.
 */

/**
 * @addtogroup hooks
 * @{
 */

use Drupal\Core\Entity\EntityInterface;

/**
 * Constructs CSS classes for an entity.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   Object representing the entity.
 *
 * @return array
 *   Array of CSS classes.
 */
function hook_fullcalendar_classes(EntityInterface $entity): array {
  // Add the entity type as a class.
  return [
    $entity->getEntityTypeId(),
  ];
}

/**
 * Alter the CSS classes for an entity.
 *
 * @param array $classes
 *   Array of CSS classes.
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   Object representing the entity.
 */
function hook_fullcalendar_classes_alter(array &$classes, EntityInterface $entity): void {
  // Remove all classes set by modules.
  $classes = [];
}

/**
 * Declare that you provide a droppable callback.
 *
 * Implementing this hook will cause a checkbox to appear on the view settings,
 * when checked FullCalendar will search for JS callbacks in the form
 * Drupal.fullcalendar.droppableCallbacks.MODULENAME.callback.
 *
 * @see http://arshaw.com/fullcalendar/docs/dropping/droppable
 */
function hook_fullcalendar_droppable(): bool {
  // This hook will never be executed.
  return TRUE;
}

/**
 * Alter the dates after they're loaded, before they're added for rendering.
 *
 * @param string $date1
 *   The start date string.
 * @param string $date2
 *   The end date string.
 * @param array $context
 *   An associative array containing the following key-value pairs:
 *   - entity: The entity object for this date.
 *   - fields: The field info.
 */
function hook_fullcalendar_process_dates_alter(string &$date1, string &$date2, array $context): void {
  // Always display dates only on one day.
  if ($date1 !== $date2) {
    $date2 = $date1;
  }
}

/**
 * @} End of "addtogroup hooks".
 */
