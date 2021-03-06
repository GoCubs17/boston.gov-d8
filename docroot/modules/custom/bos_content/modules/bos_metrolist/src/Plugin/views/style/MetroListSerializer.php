<?php

namespace Drupal\bos_metrolist\Plugin\views\style;

use Drupal\rest\Plugin\views\style\Serializer;

/**
 * The style plugin for serialized output formats.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "metrolist_serializer",
 *   title = @Translation("MetroList Serializer"),
 *   help = @Translation("Serializes views row data using the MetroList Serializer component."),
 *   display_types = {"data"}
 * )
 */
class MetroListSerializer extends Serializer {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $rows = [];
    // If the Data Entity row plugin is used, this will be an array of entities
    // which will pass through Serializer to one of the registered Normalizers,
    // which will transform it to arrays/scalars. If the Data field row plugin
    // is used, $rows will not contain objects and will pass directly to the
    // Encoder.
    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;

      $row = $this->view->rowPlugin->render($row);

      // Loop through the rows fields and if the string value is a number then set as an int value.
      foreach ($row as $fieldName => $field) {
        if ($fieldName != 'id') {
          $value = is_string($field) ? $field : $field->__toString();

          if ($value === 'null') {
            $row[$fieldName] = NULL;
          }
          else {
            $row[$fieldName] = is_numeric($value) ? intval($value) : $field;
          }

        }
      }
      $rows[] = $row;
    }
    unset($this->view->row_index);

    // Get the content type configured in the display or fallback to the
    // default.
    if ((empty($this->view->live_preview))) {
      $content_type = $this->displayHandler->getContentType();
    }
    else {
      $content_type = !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';
    }
    return $this->serializer->serialize($rows, $content_type, ['views_style_plugin' => $this]);
  }

}
