<?php

namespace Drupal\menu_export\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\system\Entity\Menu;
use Drupal\menu_link_content\Entity\MenuLinkContent;


/**
 * Configure Menu Export settings.
 */
class MenuImportForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'menu_import_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'menu_export.settings',
      'menu_export.export_data',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $menus = $this->config('menu_export.settings')
      ->get('menus');
    $menuEnt = Menu::loadMultiple($menus);
    $menuData = array_map(function ($menuEnt) {
      return $menuEnt->label();
    }, $menuEnt);
    $form['menus_to_import'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Menus to Import'),
      '#items' => $menuData,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import Menu Links'),
    ];
    return $form;
  }

  /**
   * Custom form validation for email.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    return parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $invalidMenus = $this->importMenus();

    if (count($invalidMenus)) {
      $this->messenger()
        ->addError($this->t('Menu(s) @menus not found', ['@menus' => implode(',', $invalidMenus)]));
    }
    else {
      $this->messenger()->addStatus($this->t('Menu(s) imported successfully'));
    }
  }

  /**
   * Imports menus from config.
   *
   * Returns menus that could not be imported, if it returns an empty array,
   * all went well. If it returns items in the array, those are the menus that
   * could not be imported.
   *
   * @return array
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function importMenus() {
    $invalidMenus = [];
    $menus = $this->config('menu_export.export_data')->get();
    foreach ($menus as $key => $menu) {
      $menu_name = $menu['menu_name']['value'];
      if (!Menu::load($menu_name)) {
        $invalidMenus[] = $menu_name;
        continue;
      }
      unset($menu['id']);
      unset($menu['revision_id']);
      $menuLinkEntity = \Drupal::entityTypeManager()->getStorage('menu_link_content')
            ->loadByProperties(['uuid' => $menu['uuid']]);

      if (!$menuLinkEntity) {
        $menuLinkEntity = MenuLinkContent::create();
      }
      else {
        $menuLinkEntity = reset($menuLinkEntity);
      }
      foreach ($menu as $kkey => $items) {
        $menuLinkEntity->set($kkey, $items);
      }
      $menuLinkEntity->save();
      unset($menuLinkEntity);
    }

    return $invalidMenus;
  }

}
