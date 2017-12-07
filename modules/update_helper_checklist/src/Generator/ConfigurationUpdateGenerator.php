<?php

namespace Drupal\update_helper_checklist\Generator;

use Drupal\Console\Core\Generator\Generator;
use Drupal\Console\Extension\Manager;

/**
 * Update hook generator for generate:configuration:update console command.
 *
 * @package Drupal\update_helper_checklist\Generator
 */
class ConfigurationUpdateGenerator extends Generator {

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
  public function __construct(Manager $extension_manager) {
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
   * Generate Checklist entry for configuration update.
   *
   * @param string $module
   *   Module name where update will be generated.
   * @param string $update_number
   *   Update number that will be used.
   * @param string $description
   *   Checklist entry title.
   * @param string $success_message
   *   Checklist success message.
   * @param string $failure_message
   *   Checklist failed message.
   */
  public function generate($module, $update_number, $description, $success_message, $failure_message) {
    $module_path = $this->extensionManager->getModule($module)->getPath();
    $checklist_file = $module_path . '/updates_checklist.yml';

    $parameters = [
      'update_hook_name' => $module . '_update_' . $update_number,
      'file_exists' => file_exists($checklist_file),
      'checklist_title' => $description,
      'checklist_success' => $success_message,
      'checklist_failed' => $failure_message,
    ];

    $this->renderFile(
      'configuration_update_checklist.yml.twig',
      $checklist_file,
      $parameters,
      FILE_APPEND
    );
  }

}
