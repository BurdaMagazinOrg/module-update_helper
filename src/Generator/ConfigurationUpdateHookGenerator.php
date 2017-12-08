<?php

namespace Drupal\update_helper\Generator;

use Drupal\Console\Core\Generator\Generator;
use Drupal\Console\Extension\Manager;

/**
 * Update hook generator for generate:configuration:update console command.
 *
 * @package Drupal\update_helper\Generator
 */
class ConfigurationUpdateHookGenerator extends Generator {

  /**
   * Extension manager.
   *
   * @var \Drupal\Console\Extension\Manager
   */
  protected $extensionManager;

  /**
   * AuthenticationProviderGenerator constructor.
   *
   * @param \Drupal\Console\Extension\Manager $extension_manager
   *   Extension manager.
   */
  public function __construct(
    Manager $extension_manager
  ) {
    $this->extensionManager = $extension_manager;
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
   * Generator Update N function.
   *
   * @param string $module
   *   Module name where update will be generated.
   * @param string $update_number
   *   Update number that will be used.
   * @param string $description
   *   Description displayed for update hook function.
   */
  public function generate($module, $update_number, $description = '') {
    $module_path = $this->extensionManager->getModule($module)->getPath();
    $update_file = $module_path . '/' . $module . '.install';

    $this->addSkeletonDir(__DIR__ . '/../../templates/console');

    $parameters = [
      'description' => $description,
      'module' => $module,
      'update_hook_name' => $this->getUpdateFunctionName($module, $update_number),
      'file_exists' => file_exists($update_file),
    ];

    $this->renderFile(
      'configuration_update_hook.php.twig',
      $update_file,
      $parameters,
      FILE_APPEND
    );
  }

}
