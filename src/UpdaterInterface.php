<?php

namespace Drupal\update_helper;

/**
 * Interface for the Update entity.
 */
interface UpdaterInterface {

  /**
   * Get update logger service.
   *
   * @return \Drupal\update_helper\UpdateLogger
   *   Returns update logger.
   */
  public function logger();

  /**
   * Installs modules and works with logger.
   *
   * @param array $modules
   *   List of module names.
   */
  public function installModules(array $modules);

  /**
   * List of full configuration names to import.
   *
   * @param array $config_List
   *   List of configurations.
   *
   * @return bool
   *   Returns if import was successful.
   */
  public function importConfigs(array $config_List);

  /**
   * Execute update of configuration from update definitions.
   *
   * @param string $module
   *   Module name where update definition is saved.
   * @param string $update_definition_name
   *   Update definition name. Usually same name as update hook.
   */
  public function executeUpdate($module, $update_definition_name);

}
