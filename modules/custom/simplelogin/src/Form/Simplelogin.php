<?php

/**
 * @file
 * Contains \Drupal\simplelogin\Form\SettingsForm.
 * Simplelogin settings form.
 */

namespace Drupal\simplelogin\Form;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\file\Entity\File;
use Drupal\Core\Render\RendererInterface;

/**
 * Defines a form that configure settings.
 */
class Simplelogin extends ConfigFormBase {

  /**
   * Image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   *   Image factory.
   */
  protected $imageFactory;


  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typed_config_manager, ImageFactory $image_factory, RendererInterface $renderer) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->imageFactory = $image_factory;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('image.factory'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplelogin_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'simplelogin.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array &$form, FormStateInterface $form_state, ?Request $request = NULL) {
    $form = parent::buildForm($form, $form_state, $request);

    if (\Drupal::moduleHandler()->moduleExists('persistent_login')) {
      $form['persistent_login'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Remember me'),
        '#default_value' => 0,
        '#weight' => 90,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $background_image = $form_state->getValue(['background_image']);
    $opacity = $form_state->getValue(['background_opacity']);

    if (empty($background_image) && !empty($opacity)) {
      $form_state->setErrorByName('background_image', "Opacity is applicable only for images. if image empty means we won't need Opacity. Please uncheck");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $image_id = $values['background_image'] ?? [];
    if (!empty($image_id)) {
      $file = File::load($image_id[0]);
      if ($file instanceof File) {
        $file->setPermanent();  // FILE_STATUS_PERMANENT;
        $file->save();
      }
    }

    $this->config('simplelogin.settings')
      ->set('background_active', $values['background_active'])
      ->set('background_image', $image_id)
      ->set('background_color', $values['background_color'])
      ->set('background_opacity', $values['background_opacity'])
      ->set('button_background', $values['button_background'])
      ->set('wrapper_width', $values['wrapper_width'])
      ->set('unset_active_css', $values['unset_active_css'])
      ->set('unset_css', $values['unset_css'])
      ->save();

    drupal_flush_all_caches();
  }
}
