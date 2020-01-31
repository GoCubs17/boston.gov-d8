<?php

namespace Drupal\bos_core;

use Drupal\Core\Database\Database;
use Drupal\Core\State\State;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Exception;

/**
 * Class BosCoreSyncIconManifestService.
 *
 *    Reads a manifest file and creates media (icon) entries assoc with files.
 *    Drush command to switch is: drush import-icon-manifest (alias biim)
 *
 * @see modules/custom/bos_core/src/Commands/BosCoreCommands.php
 *
 * @package Drupal\bos_core
 */
class BosCoreSyncIconManifestService {

  // Constants defined for use here.
  const SYNC_NORMAL = 0;
  const SYNC_IN_MIGRATION = 1;

  // Constants copied from \Drupal\migrate\Plugin\MigrateIdMapInterface.
  const MIGRATE_STATUS_IMPORTED = 0;
  const MIGRATE_ROLLBACK_DELETE = 0;

  /**
   * Read the manifest file and create media and file entities.
   *
   * @param int $mode
   *   Determine if we are using this during a migration.
   *
   * @return bool
   *   Whether the file was sucessfully processed.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function sync($mode = self::SYNC_NORMAL) {

    $moduleHandler = \Drupal::service('module_handler');
    $migration_enabled = FALSE;
    if ($mode == self::SYNC_IN_MIGRATION) {
      $migration_enabled = TRUE;
      if (!$moduleHandler->moduleExists('migrate')) {
        printf("[error] Migration module is not enabled !.\n");
        \Drupal::logger("drush")->error("Migration module is not enabled !");
        return FALSE;
      }
    }

    if (!$migration_enabled) {
      // See if we have a manifest cached.
      $manifest_library = \Drupal::state()
        ->get("bos_core.icon_library.manifest", []);
    }
    // Obtain the manifest file.
    $manifest_file = \Drupal::config("bos_core.settings")->get("icon.manifest");
    if (empty($manifest_file)) {
      $manifest_file = "https://patterns.boston.gov/assets/icons/manifest.txt";
    }
    $manifest = file_get_contents($manifest_file);
    if (empty($manifest)) {
      printf("[warning] Icon manifest file not found !.\n");
      \Drupal::logger("drush")->warning("Icon manifest file not found !");
      return FALSE;
    }
    $manifest = explode("\n", $manifest);

    if (!empty($manifest_library)) {
      // If using cache, then work out which rows in manifest need processing.
      $manifest_clean = array_values($manifest_library);
      $manifest = array_diff($manifest, $manifest_clean);
      if (empty($manifest)) {
        printf("[notice] All icons in manifest are already in the media library !.\n");
        return TRUE;
      }
    }

    /*
     * Functions to manage entity & database activities.
     */
    $updateFilename = function ($fid, $filename) {
      // Update the filename if necessary.
      if (NULL != ($file = File::load($fid))) {
        if ($filename != $file->getFilename()) {
          \Drupal::database()->update("file_managed")
            ->condition("fid", $fid)
            ->fields(["filename" => $filename])
            ->execute();
        }
      }
    };
    $getMediaEntityFromFile = function ($fid) {
      $result = \Drupal::database()->query("SELECT fd.mid, fd.thumbnail__target_id, i.image_title FROM media_field_data fd
          inner join media__image i on fd.mid = i.entity_id
          inner join file_managed f on i.image_target_id = f.fid
          where f.fid = " . $fid . " and i.bundle = 'icon'")
        ->fetchAll();
      return $result;

    };
    $createMediaEntity = function ($fid, $uid, $filename, $mid = 0, $vid = 0) {
      $media_fields = [
        'bundle' => "icon",
        'uid' => $uid ?? 1,
        'status' => 1,
        'name' => $filename,
        'image' => [
          'target_id' => $fid,
        ],
      ];
      if ($mid != 0) {
        // If mid provided then set mid and vid to initially be the same.
        $media_fields["mid"] = $mid;
        $media_fields["vid"] = $mid;
      }
      if ($vid != 0) {
        // If vid provided then specify that.
        $media_fields["vid"] = $mid;
      }
      $media = Media::create($media_fields);
      $media->set("field_media_in_library", 1);
      $media->save();
      return $media;
    };
    $updateMediaLibrary = function ($mid, $in_library = TRUE) {
      \Drupal::database()->update("media__field_media_in_library")
        ->condition("entity_id", $mid)
        ->fields(["field_media_in_library_value" => ($in_library ? 1 : 0)])
        ->execute();
    };
    $createMigrateMap = function () {
      $result = \Drupal::database()->query("SHOW TABLES LIKE 'migrate_map_d7_file';")
        ->fetchAll();
      if (empty($result)) {
        \Drupal::database()->query("CREATE TABLE `migrate_map_d7_file` (
      `source_ids_hash` varchar(64) NOT NULL COMMENT 'Hash of source ids. Used as primary key',
      `sourceid1` int(11) NOT NULL,
      `destid1` int(10) unsigned DEFAULT NULL,
      `source_row_status` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Indicates current status of the source row',
      `rollback_action` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Flag indicating what to do for this item on rollback',
      `last_imported` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'UNIX timestamp of the last time this row was imported',
      `hash` varchar(64) DEFAULT NULL COMMENT 'Hash of source row data, for detecting changes',
      PRIMARY KEY (`source_ids_hash`),
      KEY `source` (`sourceid1`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Mappings from source identifier value(s) to destination…';");
      }
    };
    $createMigrateMessage = function () {
      $result = \Drupal::database()->query("SHOW TABLES LIKE 'migrate_message_d7_file';")
        ->fetchAll();
      if (empty($result)) {
        \Drupal::database()->query("CREATE TABLE `migrate_message_d7_file` (
      `msgid` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `source_ids_hash` varchar(64) NOT NULL COMMENT 'Hash of source ids. Used as primary key',
      `level` int(10) unsigned NOT NULL DEFAULT '1',
      `message` mediumtext NOT NULL,
      PRIMARY KEY (`msgid`)
      ) ENGINE=InnoDB AUTO_INCREMENT=143506 DEFAULT CHARSET=utf8mb4 COMMENT='Messages generated during a migration process';");
      }
    };
    $findLastIds = function (&$last_file_fid, &$last_media_vid, $mode) {
      // Get starting value for the fid in files_managed. We need to make this
      // larger than anything we expect to avoid having these overwritten later,
      // or these overwriting files that will be exported later.
      $offset = 1;
      if ($mode == self::SYNC_IN_MIGRATION) {
        // First, set to a big number.
        $last_file_fid = $last_media_vid = 500000;
        $offset = 100;
      }

      try {
        // Second, get the maximum observed fid for files_managed from d7.
        $result = Database::getConnection("default", "migrate")
          ->query("select max(fid) last_fid from file_managed")
          ->fetchAssoc();
        if (!empty($result)) {
          $last_file_fid = $result["last_fid"] + $offset;
        }
      }
      catch (Exception $e) {
        if ($mode == self::SYNC_IN_MIGRATION) {
          throw new Exception("Cannot access the D7 database");
        }
      }

      // Last, get the maximum observed fid for files_managed from d8 and see
      // if bigger than the number used in d7.
      $result = Database::getConnection("default", "default")
        ->query("select max(fid) last_fid from file_managed")
        ->fetchAssoc();
      if (!empty($result) && $result["last_fid"] >= $last_file_fid) {
        $last_file_fid = $result["last_fid"] + $offset;
        $last_media_vid = $last_file_fid;
      }

      $result = Database::getConnection("default", "default")
        ->query("select max(vid) last_vid from media_field_data")
        ->fetchAssoc();
      if (!empty($result) && $result["last_vid"] >= $last_file_fid) {
        $last_media_vid = $result["last_vid"] + $offset;
      }

    };

    // Process the file.
    $cnt = [
      "Total" => 0,
      "Imported" => 0,
      "Media" => 0,
      "Found" => 0,
      "mFound" => 0,
    ];

    // Create the migrate map and message tables if they are not already there.
    if ($migration_enabled) {
      $createMigrateMap();
      $createMigrateMessage();
    }

    // Find the max fid in the files table and the max vid from the media table.
    $last_fid = $last_vid = 0;
    try {
      $findLastIds($last_fid, $last_vid, $mode);
    }
    catch (Exception $e) {
      printf("[error] %s\n", $e);
      \Drupal::logger("drush")->error($e);
      return FALSE;
    }

    // Process each row of the manifest file in turn.
    foreach ($manifest as $icon_file) {
      if (!empty($icon_file)) {
        $cnt["Total"]++;
        $icon_file = trim($icon_file);
        $icon_filename = self::cleanFilenameFolder($icon_file);

        $manifest_library[$icon_filename] = $icon_file;

        // Try to get the file from file_managed table.
        $file_ids = self::getFileEntities($icon_file);
        $multiple = (!empty($file_ids) && count($file_ids) > 1);
        if (empty($file_ids)) {
          // The file does not exist in file_managed table, so create it.
          $file = self::createFileEntity($icon_file, $last_fid++);
          // Now add the file to the list of files.
          $file_ids[] = $file->id();
          $cnt["Imported"]++;
          if ($migration_enabled) {
            // Need to create a migration id_map for this new file entity.
            try {
              self::createSimpleMappingEntry($file->id(), $file->id(), "d7_file");
            }
            catch (Exception $e) {
              \Drupal::logger("drush")->warning($e->getMessage());
              \Drupal::messenger()->addWarning($e->getMessage());
              continue;
            }
          }
        }
        else {
          // Icon does already exist in the file_managed table.
          $file_ids = array_values($file_ids);
          $cnt["Found"] = $cnt["Found"] + count($file_ids);
          if ($migration_enabled) {
            // The file already exists - check and update filename if needed.
            if (!$multiple) {
              $updateFilename($file_ids[0], $icon_filename);
            }
            else {
              foreach ($file_ids as $file_id) {
                $updateFilename($file_id, $icon_filename);
              }
            }
          }
        }

        // We should now have files in file_managed for all $file_ids.
        // Try to find a media entity attached to each file_id.
        foreach ($file_ids as $key => $file_id) {
          $result = $getMediaEntityFromFile($file_id);
          if (empty($result)) {
            // Need to create a matching media entity for this file entity.
            if (NULL == $file || $file->id() != $file_id) {
              $file = File::load($file_id);
            }
            $media = $createMediaEntity($file->id(), $file->getOwnerId(), $icon_filename, $file->id(), $last_vid++);
            $cnt["Media"]++;
            // Only put the first media item into the library.
            $updateMediaLibrary($media->id(), ($key == 0));
          }
          else {
            // Already is a media item for this file.
            foreach ($result as $media_entity) {
              $cnt["mFound"]++;
              $updateMediaLibrary($media_entity->mid, ($key == 0));
              if ($key == 0) {
                break;
              }
            }
          }
          if ($key == 0 && !$migration_enabled) {
            break;
          }
        }
      }
    }

    // Save this manifest for later use (e.g. migration).
    \Drupal::state()->set("bos_core.icon_library.manifest", $manifest_library);

    printf("Manifest defines %d icon files.\n", $cnt["Total"]);
    printf("---------------- ---------- ------- ---------- --------------\n");
    printf("Group            Manifest   Files   Imported   Media Library \n");
    printf("---------------- ---------- ------- ---------- --------------\n");
    printf("Icon Library     %s         %s      %s         %s            \n", $cnt["Total"], $cnt["Found"], $cnt["Imported"], ($cnt["Media"] + $cnt["mFound"]));
    printf("---------------- ---------- ------- ---------- --------------\n");

    return TRUE;

  }

  /**
   * Makes a clean filename with its containing folder.
   *
   * @param string $path
   *   The filename and full path.
   *
   * @return string
   *   The filename and its containing folder.
   */
  private static function cleanFilenameFolder($path) {
    $filename = self::cleanFilename($path);
    $path_bits = explode("/", $path);
    $path_bits = array_reverse($path_bits);
    if (!empty($path_bits[1])) {
      $filename = self::cleanFilename($path_bits[1]) . " " . $filename;
    }
    return $filename;
  }

  /**
   * Attempts to build a meaningful filename from a given file path and name.
   *
   * Note: This is a copy of FilesystemReorganizationTrait:cleanFilename().
   *
   * @param string $path
   *   The filename and path.
   *
   * @return string
   *   Reformatted filename.
   */
  private static function cleanFilename($path) {
    $filename = explode("/", $path);
    $extension = end(explode(".", end($filename)));
    $filename = array_pop($filename);
    $filename = str_replace([
      "icons",
      "logo",
      ".svg",
      ".jpg",
      ".gif",
      ".jpeg",
      ".png",
      ".txt",
      ".xlsx",
      ".pdf",
    ], "", $filename);
    $filename = str_replace("icon", "", $filename);
    $filename = str_replace(["-", "_", "."], " ", $filename);
    $filename = preg_replace("~\s+~", " ", $filename);
    if (in_array($extension, ["pdf", "xls", "xlsx", "txt"])) {
      $filename .= " (" . $extension . ")";
    }
    return strtolower($filename);
  }

  /**
   * Retrieves fid(s) for corresponding file entity/ies.
   *
   * Note: This is a copy of FilesystemReorganizationTrait:getFileEntities().

   * Best effort search.
   * Assumes the uri have not changed from d7 to d8 during migration, which is
   * just an heuristic.
   *
   * @param string $uri
   *   File uri.
   *
   * @return \Drupal\file\Entity\File|null
   *   File entity object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getFileEntities($uri) {
    $query = \Drupal::entityQuery("file")
      ->condition("uri", $uri, "=");
    $entities = $query->execute();
    if (empty($entities)) {
      return NULL;
    }
    return $entities;
  }

  /**
   * Create a new File object and save (in table file_managed).
   *
   * Note: This is a copy of FilesystemReorganizationTrait:createFileEntity().
   *
   * @param string $uri
   *   The uri to create in the file_managed table.
   * @param int $fid
   *   Force the fid value.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The file object just created.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function createFileEntity(string $uri, int $fid = 0) {
    $filename = self::cleanFilename($uri);
    $fields = [
      'uri' => $uri,
      'uid' => '1',
      'filename' => $filename,
      'status' => '1',
    ];
    if ($fid != 0) {
      $fields["fid"] = $fid;
    }
    $file_entity = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->create($fields);
    $file_entity->save();
    return $file_entity;
  }

  /**
   * Creates simple source:dest entry in the migrate_map_xxx table for lookups.
   *
   * Note: This is a copy of:
   *    FilesystemReorganizationTrait:createSimpleMappingEntry().
   *
   * @param int $sourceid
   *   The oringinal ID to map.
   * @param string $destid
   *   The new ID to map.
   * @param string $mig_type
   *   The table type to write to.
   * @param string $hash
   *   The hash (optional) - Note row hash is created in-function.
   *
   * @throws \Exception
   */
  public static function createSimpleMappingEntry($sourceid, $destid, $mig_type, $hash = "") {
    try {
      $fields["sourceid1"] = $sourceid;
      $fields += [
        'source_row_status' => self::MIGRATE_STATUS_IMPORTED,
        'rollback_action' => self::MIGRATE_ROLLBACK_DELETE,
        'hash' => $hash,
      ];
      $fields["destid1"] = $destid;
      $fields["last_imported"] = 0;
      $row_hash = hash('sha256', serialize(array_map('strval', [$sourceid])));
      $keys = ["source_ids_hash" => $row_hash];

      $table_name = "migrate_map_" . $mig_type;

      \DRUPAL::database()->delete($table_name)
        ->condition("sourceid1", $fields["sourceid1"])
        ->condition("destid1", $destid)
        ->execute();

      \DRUPAL::database()->merge($table_name)
        ->key($keys)
        ->fields($fields)
        ->execute();
    }
    catch (\Exception $e) {
      // Got an error. SQL-23000 is a duplicate row entry, thats OK so allow
      // it but dont allow anything else.
      if ($e->getCode() != 23000) {
        throw new Exception($e->getMessage(), $e->getCode());
      }
    }
  }

}
