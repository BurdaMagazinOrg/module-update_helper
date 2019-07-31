<?php

namespace Drupal\update_helper_checklist\Generator;

use Drupal\Console\Core\Generator\Generator;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\update_helper_checklist\UpdateChecklist;

/**
 * Update hook generator for generate:configuration:update console command.
 *
 * @package Drupal\update_helper_checklist\Generator
 */
class ConfigurationUpdateGenerator extends Generator {

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Update checklist service.
   *
   * @var \Drupal\update_helper_checklist\UpdateChecklist
   */
  protected $updateChecklist;

  /**
   * AuthenticationProviderGenerator constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Extension manager.
   * @param \Drupal\update_helper_checklist\UpdateChecklist $update_checklist
   *   Update checklist service.
   */
  public function __construct(ModuleHandlerInterface $module_handler, UpdateChecklist $update_checklist) {
    $this->moduleHandler = $module_handler;
    $this->updateChecklist = $update_checklist;
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
   * @param string $update_version
   *   Update version for module.
   * @param string $description
   *   Checklist entry title.
   * @param string $update_description
   *   Checklist update description.
   * @param string $success_message
   *   Checklist success message.
   * @param string $failure_message
   *   Checklist failed message.
   */
  public function generate($module, $update_number, $update_version, $description, $update_description, $success_message, $failure_message) {
    $module_path = $this->moduleHandler->getModule($module)->getPath();
    $checklist_file = $module_path . DIRECTORY_SEPARATOR . UpdateChecklist::$updateChecklistFileName;
    $update_versions = $this->updateChecklist->getUpdateVersions($module);
    end($update_versions);
    $last_update_version = current($update_versions);

    $parameters = [
      'update_hook_name' => $module . '_update_' . $update_number,
      'file_exists' => file_exists($checklist_file),
      'checklist_title' => $description,
      'checklist_description' => $update_description,
      'checklist_success' => $success_message,
      'checklist_failed' => $failure_message,
      'update_version' => ($update_version === $last_update_version) ? '' : $update_version,
    ];

    $this->renderFile(
      'configuration_update_checklist.yml.twig',
      $checklist_file,
      $parameters,
      FILE_APPEND
    );
  }

}
