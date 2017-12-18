<?php

namespace Drupal\update_helper\Generator;

use Drupal\Console\Core\Generator\Generator;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\update_helper\ConfigHandler;

/**
 * Configuration update generator for generate:configuration:update command.
 *
 * @package Drupal\update_helper\Generator
 */
class ConfigurationUpdateGenerator extends Generator {

  /**
   * Drupal\update_helper\ConfigHandler definition.
   *
   * @var \Drupal\update_helper\ConfigHandler
   */
  protected $configHandler;

  /**
   * Drupal\Core\Extension\ModuleHandler definition.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Configuration update generator.
   *
   * @param \Drupal\update_helper\ConfigHandler $config_handler
   *   Config handler service.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   Module handler service.
   */
  public function __construct(
    ConfigHandler $config_handler,
    ModuleHandler $module_handler
  ) {
    $this->configHandler = $config_handler;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Get update hook function name.
   *
   * @param string $module_name
   *   Module name.
   * @param string $update_number
   *   Update number.
   *
   * @return string
   *   Returns update hook function name.
   */
  protected function getUpdateFunctionName($module_name, $update_number) {
    return $module_name . '_update_' . $update_number;
  }

  /**
   * Generate patch file for listed modules in module defined for command.
   *
   * @param string $module_name
   *   Module name where patch will be placed.
   * @param string $update_number
   *   Update number that will be used.
   * @param string $module_list
   *   Comma separated list of modules.
   * @param bool $from_active
   *   Flag if configuration should be updated from active to Yml file configs.
   *
   * @return bool
   *   Return if patch file is generated.
   */
  public function generate($module_name, $update_number, $module_list, $from_active) {
    if ($module_list) {
      $modules = explode(',', $module_list);
    }
    else {
      $modules = array_filter($this->moduleHandler->getModuleList(), function (Extension $extension) {
        return ($extension->getType() == 'module');
      });
      $modules = array_keys($modules);
    }

    // Get patch data and save it into file.
    $patch_data = $this->configHandler->generatePatchFile($modules, $from_active);
    if (!empty($patch_data)) {
      $patch_file_path = $this->configHandler->getPatchFile($module_name, $this->getUpdateFunctionName($module_name, $update_number), TRUE);

      if (file_put_contents($patch_file_path, $patch_data)) {
        $this->fileQueue->addFile($patch_file_path);
        $new_code_line = count(file($patch_file_path));

        $this->countCodeLines->addCountCodeLines($new_code_line);

        return TRUE;
      }
    }

    return FALSE;
  }

}
