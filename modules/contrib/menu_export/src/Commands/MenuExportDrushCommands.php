<?php

namespace Drupal\menu_export\Commands;

use Drush\Commands\DrushCommands;
use Drupal\menu_export\Form\MenuExportForm;
use Drupal\menu_export\Form\MenuImportForm;

/**
 * Drush commands for importing / exporting menu items from / to config.
 *
 * @package Drupal\menu_export\Commands
 */
class MenuExportDrushCommands extends DrushCommands {

  /**
   * Drush command for exporting menu items to config using default configuration.
   *
   * @command menu_export:export
   * @aliases menu-export-export menu_export-export
   * @usage menu_export:export
   */
  public function export() {
    $form = new MenuExportForm(\Drupal::configFactory());
    if ($form->exportMenus()) {
      $this->output()->writeln(t('Menu(s) exported successfully'));
    }
    else {
      throw new \Exception(t('Menu export did NOT work.'));
    }
  }

  /**
   * Drush command for importing menu items to config using default config.
   *
   * @command menu_export:import
   * @aliases menu-export-import menu_export-import
   * @usage menu_export:import
   */
  public function import() {
    $form = new MenuImportForm(\Drupal::configFactory());
    $invalidMenus = $form->importMenus();

    if (count($invalidMenus) == 0) {
      $this->output()->writeln(t('Menu(s) imported successfully'));
    }
    else {
      throw new \Exception(t('Menu(s) @menus not found',[
          '@menus'=>implode(',',$invalidMenus)
        ]));
    }
  }

}
