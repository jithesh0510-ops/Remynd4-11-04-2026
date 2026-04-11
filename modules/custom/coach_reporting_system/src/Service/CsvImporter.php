<?php

namespace Drupal\coach_reporting_system\Service;

/**
 * Simple CSV reader & normalizer.
 */
class CsvImporter {

  /**
   * Read a CSV file (path) into an array of associative arrays using header row.
   * Normalizes header keys to lowercase snake-case and trims BOM.
   *
   * @param string $path
   * @param string $delimiter
   * @return array<int, array<string, string>>
   */
  public function readAssoc(string $path, string $delimiter = ','): array {
    $out = [];
    $handle = fopen($path, 'r');
    if (!$handle) {
      return $out;
    }
    $headers = [];
    if (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
      $headers = array_map(function ($h) {
        $h = preg_replace('/^\xEF\xBB\xBF/', '', (string) $h); // strip UTF-8 BOM
        $h = trim($h);
        return $this->normalizeKey($h);
      }, $row);
    }
    while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
      $row = array_map(static fn($v) => is_string($v) ? trim($v) : $v, $row);
      // Ensure row length matches headers length.
      if (count($row) < count($headers)) {
        $row = array_pad($row, count($headers), '');
      }
      $out[] = array_combine($headers, array_slice($row, 0, count($headers)));
    }
    fclose($handle);
    return $out;
  }

  /** Map assorted header spellings to canonical keys. */
  public function normalizeKey(string $key): string {
    $k = strtolower($key);
    $map = [
      'employee id' => 'employee_id',
      'employee_id' => 'employee_id',
      'userid' => 'employee_id',
      'user id' => 'employee_id',
      'first name' => 'first_name',
      'first_name' => 'first_name',
      'surname' => 'last_name',
      'last name' => 'last_name',
      'last_name' => 'last_name',
      'target forecasted' => 'target_forecasted',
      'target_forecasted' => 'target_forecasted',
      'target achieved' => 'target_achieved',
      'target_achieved' => 'target_achieved',
      'serial number' => 'serial_number',
      'grade/10.00' => 'grade',
      'grade' => 'grade',
      'job position' => 'job_position',
      'job' => 'job_position',
    ];
    return $map[$k] ?? preg_replace('/\s+/', '_', $k);
  }
}
