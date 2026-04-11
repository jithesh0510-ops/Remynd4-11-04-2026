<?php

namespace Drupal\reporting_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\profile\Entity\Profile;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for inline coach assignment updates.
 */
class InlineCoachController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a InlineCoachController object.
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
   * AJAX callback to update coach assignment.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with success/error status.
   */
  public function updateCoach(Request $request) {
    $employee_id = $request->request->get('employee_id');
    $coach_ids = $request->request->all('coach_ids');
    if ($coach_ids === NULL) {
      $coach_ids = [];
    }

    \Drupal::logger('reporting_user')->notice('Coach update request: employee_id=@id, coach_ids=@coaches', [
      '@id' => $employee_id,
      '@coaches' => is_array($coach_ids) ? implode(',', $coach_ids) : $coach_ids,
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

      // Get employee's company.
      $employee_company_id = NULL;
      if ($profile->hasField('field_company') && !$profile->get('field_company')->isEmpty()) {
        $company_entity = $profile->get('field_company')->entity;
        if ($company_entity) {
          $employee_company_id = $company_entity->id();
        }
      }

      // Validate coach IDs and ensure they belong to the same company.
      if (!empty($coach_ids)) {
        $coach_ids = is_array($coach_ids) ? $coach_ids : [$coach_ids];
        $coach_ids = array_filter(array_map('intval', $coach_ids));
        
        // Verify all coach IDs are valid users with coach role and same company.
        $user_storage = $this->entityTypeManager->getStorage('user');
        $coaches = $user_storage->loadMultiple($coach_ids);
        $valid_coach_ids = [];
        
        foreach ($coaches as $coach) {
          if (!$coach->hasRole('coach') || !$coach->isActive()) {
            continue;
          }
          
          // If employee has a company, verify coach belongs to the same company.
          if ($employee_company_id) {
            // Load coach profile to check company.
            $coach_profiles = $profile_storage->loadByProperties([
              'uid' => $coach->id(),
              'type' => 'coach',
              'status' => 1,
            ]);
            
            if (!empty($coach_profiles)) {
              $coach_profile = reset($coach_profiles);
              if ($coach_profile->hasField('field_company') && !$coach_profile->get('field_company')->isEmpty()) {
                $coach_company_entity = $coach_profile->get('field_company')->entity;
                if ($coach_company_entity && $coach_company_entity->id() == $employee_company_id) {
                  $valid_coach_ids[] = $coach->id();
                }
              }
            }
          } else {
            // If employee has no company, allow any coach (for backward compatibility).
            $valid_coach_ids[] = $coach->id();
          }
        }
        
        $coach_ids = $valid_coach_ids;
      } else {
        $coach_ids = [];
      }

      // Update the coach field.
      if ($profile->hasField('field_coach')) {
        // Clear existing values and set new ones.
        $profile->set('field_coach', []);
        if (!empty($coach_ids)) {
          $coach_values = [];
          foreach ($coach_ids as $coach_id) {
            $coach_values[] = ['target_id' => $coach_id];
          }
          $profile->set('field_coach', $coach_values);
        }
        $profile->save();

        \Drupal::logger('reporting_user')->notice('Coach assignment saved: profile_id=@pid, coach_ids=@coaches', [
          '@pid' => $profile->id(),
          '@coaches' => implode(',', $coach_ids),
        ]);

        // Get updated coach names for response.
        $coach_names = [];
        if (!empty($coach_ids)) {
          $coaches = $this->entityTypeManager->getStorage('user')->loadMultiple($coach_ids);
          foreach ($coaches as $coach) {
            $first_name = $coach->hasField('field_first_name') && !$coach->get('field_first_name')->isEmpty()
              ? $coach->get('field_first_name')->value
              : '';
            $last_name = $coach->hasField('field_last_name') && !$coach->get('field_last_name')->isEmpty()
              ? $coach->get('field_last_name')->value
              : '';
            
            $name = trim($first_name . ' ' . $last_name);
            if (empty($name)) {
              $name = $coach->getAccountName();
            }
            $coach_names[] = $name;
          }
        }

        return new JsonResponse([
          'success' => TRUE,
          'message' => 'Coach assignment updated successfully.',
          'coach_names' => $coach_names,
        ]);
      }

      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Employee profile does not have coach field.',
      ], 400);

    }
    catch (\Exception $e) {
      \Drupal::logger('reporting_user')->error('Error updating coach assignment: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'success' => FALSE,
        'message' => 'An error occurred while updating coach assignment.',
      ], 500);
    }
  }

}

