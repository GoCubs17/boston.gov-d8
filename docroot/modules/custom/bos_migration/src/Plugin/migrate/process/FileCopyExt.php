<?php

namespace Drupal\bos_migration\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\FileCopy;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;

/**
 * Extends the file_copy plugin (class fileCopy).
 *
 * @code
 * process:
 *   path_to_file:
 *     plugin: file_copy_ext
 *     source:
 *       - /path/to/file.png
 *       - public://new/path/to/file.png
 * @endcode
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "file_copy_ext"
 * )
 */
class FileCopyExt extends FileCopy {

  /**
   * Extend the actual copy action to squash file-not-found errors.
   *
   * Replaces the fileCopy->transform funcion.
   *
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // If we're stubbing a file entity, return a uri of NULL so it will get
    // stubbed by the general process.
    if ($row->isStub()) {
      return NULL;
    }

    list($source, $destination) = $value;

    // Ovveride the config with state settings.
    if (\Drupal::state()->get("bos_migration.active", 0) == 1) {
      if (\Drupal::state()->get("bos_migration.fileOps", "none") != "none") {
        $this->configuration['copy'] = (\Drupal::state()->get("bos_migration.fileOps") == "copy" ? "true" : "false");
        $this->configuration['move'] = (\Drupal::state()->get("bos_migration.fileOps") == "move" ? "true" : "false");
      }
    }

    // If we don't have an actual file action, then dont do anything.
    if ((!isset($this->configuration['move']) || $this->configuration['move'] === "false") && (!isset($this->configuration['copy']) || $this->configuration['copy'] === "false")) {
      return $destination;
    }

    // Map our reote_source path prefix onto the source so we can download it.
    if (isset($this->configuration["remote_source"])) {
      $source = $this->configuration["remote_source"] . $source;
      $source = preg_replace("~([A-Za-z0-9])//~", "$1/", $source);
    }

    if (parent::isLocalUri($source) && !file_exists($source)) {
      $fid = $row->getSource()['fid'];
      $migrate_executable->saveMessage("File (fid:$fid) '$source' does not exist", MigrationInterface::MESSAGE_NOTICE);
      return $destination;
    }

    // Save the newly created source.
    $value[0] = $source;

    return parent::transform($value, $migrate_executable, $row, $destination_property);

  }

  /**
   * Tries to move or copy a file.  Extended to do neither.
   *
   * Replaces the fileCopy->writeFile funcion.
   *
   * {@inheritdoc}
   */
  protected function writeFile($source, $destination, $replace = FILE_EXISTS_REPLACE) {
    // Check if there is a destination available for copying. If there isn't,
    // it already exists at the destination and the replace flag tells us to not
    // replace it. In that case, return the original destination.
    if (!($final_destination = file_destination($destination, $replace))) {
      return $destination;
    }
    if (!empty($this->configuration['move']) || !empty($this->configuration['copy'])) {
      $function = 'file_unmanaged_' . ($this->configuration['move'] ? 'move' : 'copy');
    }
    return $function($source, $destination, $replace);
  }

}