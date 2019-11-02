<?php

namespace Drupal\bos_migration;

use Drupal\Core\Database\Database;

/**
 * Trait migrationModerationStateTrait.
 *
 * @package Drupal\bos_migration
 */
trait migrationModerationStateTrait {

  /**
   * Fetches all published and current revisions of this node from D7 DB.
   *
   * @param int $nid
   *   The node id.
   * @param int|null $limit
   *   If provided will select the latest $limit records from the table.
   *
   * @return mixed
   *   An array indexed by vid.
   */
  public static function getModerationAll(int $nid, int $limit = NULL) {
    $connection = Database::getConnection("default", "migrate");
    $query = $connection->select("workbench_moderation_node_history", "history")
      ->fields('history', [
        "nid",
        "vid",
        "from_state",
        "state",
        "published",
        "is_current",
      ]);
    $query->condition("nid", $nid);
    $or = $query->orConditionGroup()
      ->condition("state", "published")
      ->condition("is_current", 1)
      ->condition("published", 1);
    $query->condition($or);
    // If we are trimming, then just get last $limit records.
    if (isset($limit)) {
      $query->range(0, $limit);
      $query->orderBy("stamp", "DESC");
    }
    return $query->execute()->fetchAllAssoc("vid");
  }

  /**
   * Fetches all published revisions of this node from D7 Database source.
   *
   * @param int $nid
   *   The node id.
   *
   * @return mixed
   *   An array indexed by vid.
   */
  public static function getModerationPublished(int $nid) {
    $connection = Database::getConnection("default", "migrate");

    $query = $connection->select("workbench_moderation_node_history", "history")
      ->fields('history', [
        "nid",
        "vid",
        "from_state",
        "state",
        "published",
        "is_current",
      ]);
    $query->condition("nid", $nid);
    $query->condition("state", "published");

    return (array) $query->execute()->fetchAssoc();
  }

  /**
   * Fetches all current revision of this node from D7 Database source.
   *
   * @param int $nid
   *   The node id.
   *
   * @return mixed
   *   An array indexed by vid.
   */
  public static function getModerationCurrent(int $nid) {
    $connection = Database::getConnection("default", "migrate");

    $query = $connection->select("workbench_moderation_node_history", "history")
      ->fields('history', [
        "nid",
        "vid",
        "from_state",
        "state",
        "published",
        "is_current",
      ]);
    $query->condition("nid", $nid);
    $query->condition("is_current", 1);

    return (array) $query->execute()->fetchAssoc();
  }

}
