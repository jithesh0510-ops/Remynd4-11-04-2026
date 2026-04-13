<?php

namespace Drupal\coach_csv_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

class CoachImportDownloadController extends ControllerBase {

  public function key() {

    // Get selected company from URL
    $company = \Drupal::request()->query->get('company');

    $connection = \Drupal::database();

    // Build query
    $query = $connection->select('users_field_data', 'u');
    $query->fields('u', ['uid', 'mail']);

    // Join profile table
    $query->innerJoin('profile', 'p', 'p.uid = u.uid');

    // Join company field
    $query->innerJoin('profile__field_company', 'c', 'c.entity_id = p.id');



    // Return CSV response
    $response = new Response($output);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="coach_key.csv"');

    return $response;
  }

}