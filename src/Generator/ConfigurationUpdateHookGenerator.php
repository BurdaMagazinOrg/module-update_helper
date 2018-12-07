<?php

namespace Drupal\update_helper\Generator;

use Drupal\Console\Core\Generator\Generator;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Update hook generator for generate:configuration:update console command.
 *
 * @package Drupal\update_helper\Generator
 */
class ConfigurationUpdateHookGenerator extends Generator {

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * AuthenticationProviderGenerator constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Extension manager.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
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
    $module_path = $this->moduleHandler->getModule($module)->getPath();
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
