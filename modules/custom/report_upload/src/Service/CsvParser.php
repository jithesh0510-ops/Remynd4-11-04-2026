<?php

namespace Drupal\report_upload\Service;

class CsvParser {

  public function parse($filepath) {
    $rows = [];
    if (($handle = fopen($filepath, 'r')) !== FALSE) {
      $header = fgetcsv($handle);
      while (($row = fgetcsv($handle)) !== FALSE) {
        $rows[] = array_combine($header, $row);
      }
      fclose($handle);
    }
    return $rows;
  }

}
