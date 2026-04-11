<?php

namespace Drupal\reporting_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\profile\Entity\Profile;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for inline job position updates.
 */
class InlineJobPositionController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a InlineJobPositionController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * AJAX callback to update job position.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with success/error status.
   */
  public function updateJobPosition(Request $request) {
    $employee_id = $request->request->get('employee_id');
    $job_position_id = $request->request->get('job_position_id');

    \Drupal::logger('reporting_user')->notice('Job position update request: employee_id=@id, job_position_id=@jp', [
      '@id' => $employee_id,
      '@jp' => $job_position_id,
    ]);

    if (empty($employee_id)) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Employee ID is required.',
      ], 400);
    }

    try {
      // Try to load by profile ID first, then by user UUID.
      $profile_storage = $this->entityTypeManager->getStorage('profile');
      $profile = $profile_storage->load($employee_id);

      // If not found by ID, try by user UUID.
      if (!$profile) {
        $user_storage = $this->entityTypeManager->getStorage('user');
        $users = $user_storage->loadByProperties(['uuid' => $employee_id]);
        if (!empty($users)) {
          $user = reset($users);
          $profiles = $profile_storage->loadByProperties([
            'uid' => $user->id(),
            'type' => 'employee',
            'status' => 1,
          ]);
          $profile = !empty($profiles) ? reset($profiles) : NULL;
        }
      }

      if (!$profile || $profile->bundle() !== 'employee') {
        \Drupal::logger('reporting_user')->error('Employee profile not found: employee_id=@id', [
          '@id' => $employee_id,
        ]);
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Employee profile not found. ID: ' . $employee_id,
        ], 404);
      }

      // Validate job position ID if provided.
      $job_position_name = '';
      if (!empty($job_position_id)) {
        $job_position_id = intval($job_position_id);
        $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
        $term = $term_storage->load($job_position_id);
        
        if (!$term || $term->bundle() !== 'job_position') {
          return new JsonResponse([
            'success' => FALSE,
            'message' => 'Invalid job position.',
          ], 400);
        }
        
        $job_position_name = $term->getName();
      }

      // Update the job position field.
      if ($profile->hasField('field_job_position')) {
        if (!empty($job_position_id)) {
          $profile->set('field_job_position', [
            'target_id' => $job_position_id,
          ]);
        } else {
          $profile->set('field_job_position', NULL);
        }
        $profile->save();

        \Drupal::logger('reporting_user')->notice('Job position saved: profile_id=@pid, job_position_id=@jp', [
          '@pid' => $profile->id(),
          '@jp' => $job_position_id ?: 'none',
        ]);

        return new JsonResponse([
          'success' => TRUE,
          'message' => 'Job position updated successfully.',
          'job_position_name' => $job_position_name,
        ]);
      }

      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Employee profile does not have job position field.',
      ], 400);

    }
    catch (\Exception $e) {
      \Drupal::logger('reporting_user')->error('Error updating job position: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'success' => FALSE,
        'message' => 'An error occurred while updating job position.',
      ], 500);
    }
  }

}

