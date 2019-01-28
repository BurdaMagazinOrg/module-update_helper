<?php

namespace Drupal\update_helper;

use Drupal\Component\Utility\NestedArray;
use Drupal\config_update\ConfigRevertInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\update_helper\Events\ConfigurationUpdateEvent;
use Drupal\update_helper\Events\UpdateHelperEvents;
use Drupal\Component\Utility\DiffArray;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Helper class to update configuration.
 */
class Updater implements UpdaterInterface {

  use StringTranslationTrait;

  /**
   * Site configFactory object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Module installer service.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected $moduleInstaller;

  /**
   * Config reverter service.
   *
   * @var \Drupal\config_update\ConfigRevertInterface
   */
  protected $configReverter;

  /**
   * Configuration handler service.
   *
   * @var \Drupal\update_helper\ConfigHandler
   */
  protected $configHandler;

  /**
   * Logger service.
   *
   * Note: Instead of using this service directly, use provided wrappers for it:
   * - logWarning - for logging warnings, it will mark executed update as failed
   * - logInfo    - for logging information.
   *
   * @var \Drupal\update_helper\UpdateLogger
   */
  protected $logger;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs the PathBasedBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $module_installer
   *   Module installer service.
   * @param \Drupal\config_update\ConfigRevertInterface $config_reverter
   *   Config reverter service.
   * @param \Drupal\update_helper\ConfigHandler $config_handler
   *   Configuration handler service.
   * @param \Drupal\update_helper\UpdateLogger $logger
   *   Update logger.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleInstallerInterface $module_installer, ConfigRevertInterface $config_reverter, ConfigHandler $config_handler, UpdateLogger $logger, EventDispatcherInterface $event_dispatcher) {
    $this->configFactory = $config_factory;
    $this->moduleInstaller = $module_installer;
    $this->configReverter = $config_reverter;
    $this->configHandler = $config_handler;
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Keeps the record of total warnings occurred during update execution.
   *
   * @var int
   */
  protected $warningCount = 0;

  /**
   * {@inheritdoc}
   */
  public function logger() {
    return $this->logger;
  }

  /**
   * Log warning message with internal count for reporting update failures.
   *
   * @param string $message
   *   The message used for logging of warning.
   */
  protected function logWarning($message) {
    $this->warningCount++;

    $this->logger->warning($message);
  }

  /**
   * Log information message, that will be displayed on update execution.
   *
   * @param string $message
   *   The message used for logging of info.
   */
  protected function logInfo($message) {
    $this->logger->info($message);
  }

  /**
   * {@inheritdoc}
   */
  public function executeUpdate($module, $update_definition_name) {
    $this->warningCount = 0;

    $update_definitions = $this->configHandler->loadUpdate($module, $update_definition_name);
    if (isset($update_definitions[UpdateDefinitionInterface::GLOBAL_ACTIONS])) {
      $this->executeGlobalActions($update_definitions[UpdateDefinitionInterface::GLOBAL_ACTIONS]);

      unset($update_definitions[UpdateDefinitionInterface::GLOBAL_ACTIONS]);
    }

    if (!empty($update_definitions)) {
      $this->executeConfigurationActions($update_definitions);
    }

    // Dispatch event after update has finished.
    $event = new ConfigurationUpdateEvent($module, $update_definition_name, $this->warningCount);
    $this->eventDispatcher->dispatch(UpdateHelperEvents::CONFIGURATION_UPDATE, $event);

    return $this->warningCount === 0;
  }

  /**
   * Get array with defined global actions.
   *
   * Global actions can be:
   * - install_modules: list of modules to install
   * - import_configs: list of configurations to import.
   *
   * @param array $global_actions
   *   Array with list of global actions.
   */
  protected function executeGlobalActions(array $global_actions) {
    if (isset($global_actions[UpdateDefinitionInterface::GLOBAL_ACTION_INSTALL_MODULES])) {
      $this->installModules($global_actions[UpdateDefinitionInterface::GLOBAL_ACTION_INSTALL_MODULES]);
    }

    if (isset($global_actions[UpdateDefinitionInterface::GLOBAL_ACTION_IMPORT_CONFIGS])) {
      $this->importConfigs($global_actions[UpdateDefinitionInterface::GLOBAL_ACTION_IMPORT_CONFIGS]);
    }
  }

  /**
   * Execute configuration update definitions for configurations.
   *
   * @param array $update_definitions
   *   List of configurations with update definitions for them.
   */
  protected function executeConfigurationActions(array $update_definitions) {
    foreach ($update_definitions as $configName => $configChange) {
      $update_actions = $configChange['update_actions'];

      $delete_keys = [];
      // Define configuration keys that should be deleted.
      if (isset($update_actions['delete'])) {
        $delete_keys = $this->getFlatKeys($update_actions['delete']);
      }

      $new_config = [];
      // Add configuration that is changed.
      if (isset($update_actions['change'])) {
        $new_config = NestedArray::mergeDeep($new_config, $update_actions['change']);
      }

      // Add configuration that is added.
      if (isset($update_actions['add'])) {
        $new_config = NestedArray::mergeDeep($new_config, $update_actions['add']);
      }

      if ($this->updateConfig($configName, $new_config, $configChange['expected_config'], $delete_keys)) {
        $this->logInfo($this->t('Configuration @configName has been successfully updated.', ['@configName' => $configName]));
      }
      else {
        $this->logWarning($this->t('Unable to update configuration for @configName.', ['@configName' => $configName]));
      }
    }
  }

  /**
   * Installs modules.
   *
   * @param array $modules
   *   List of module names.
   */
  protected function installModules(array $modules) {
    foreach ($modules as $module) {
      try {
        if ($this->moduleInstaller->install([$module])) {
          $this->logInfo($this->t('Module @module is successfully enabled.', ['@module' => $module]));
        }
        else {
          $this->logWarning($this->t('Unable to enable @module.', ['@module' => $module]));
        }
      }
      catch (MissingDependencyException $e) {
        $this->logWarning($this->t('Unable to enable @module because of missing dependencies.', ['@module' => $module]));
      }
    }
  }

  /**
   * Imports configurations.
   *
   * @param array $config_list
   *   List of full configuration names.
   */
  protected function importConfigs(array $config_list) {
    // Import configurations.
    foreach ($config_list as $full_config_name) {
      $config_name = ConfigName::createByFullName($full_config_name);

      if (!empty($this->configFactory->get($full_config_name)->getRawData())) {
        $this->logWarning($this->t('Importing of @full_name config will be skipped, because configuration already exists.', [
          '@full_name' => $full_config_name,
        ]));

        continue;
      }

      if (!$this->configReverter->import($config_name->getType(), $config_name->getName())) {
        $this->logWarning($this->t('Unable to import @full_name config, because configuration file is not found.', [
          '@full_name' => $full_config_name,
        ]));

        continue;
      }

      $this->logInfo($this->t('Configuration @full_name has been successfully imported.', [
        '@full_name' => $full_config_name,
      ]));
    }
  }

  /**
   * Get flatten array keys as list of paths.
   *
   * Example:
   *   $nestedArray = [
   *      'a' => [
   *          'b' => [
   *              'c' => 'c1',
   *          ],
   *          'bb' => 'bb1'
   *      ],
   *      'aa' => 'aa1'
   *   ]
   *
   * Result: [
   *   ['a', 'b', 'c'],
   *   ['a', 'bb']
   *   ['aa']
   * ]
   *
   * @param array $nested_array
   *   Array with nested keys.
   *
   * @return array
   *   List of flattened keys.
   */
  protected function getFlatKeys(array $nested_array) {
    $keys = [];
    foreach ($nested_array as $key => $value) {
      if (is_array($value) && !empty($value)) {
        $list_of_sub_keys = $this->getFlatKeys($value);

        foreach ($list_of_sub_keys as $subKeys) {
          $keys[] = array_merge([$key], $subKeys);
        }
      }
      else {
        $keys[] = [$key];
      }
    }

    return $keys;
  }

  /**
   * Update configuration.
   *
   * It's possible to provide expected configuration that should be checked,
   * before new configuration is applied in order to ensure existing
   * configuration is expected one.
   *
   * @param string $config_name
   *   Configuration name that should be updated.
   * @param array $configuration
   *   Configuration array to update.
   * @param array $expected_configuration
   *   Only if current config is same like old config we are updating.
   * @param array $delete_keys
   *   List of parent keys to remove. @see NestedArray::unsetValue()
   *
   * @return bool
   *   Returns TRUE if update of configuration was successful.
   */
  protected function updateConfig($config_name, array $configuration, array $expected_configuration = [], array $delete_keys = []) {
    $config = $this->configFactory->getEditable($config_name);

    $config_data = $config->get();

    // Check that configuration exists before executing update.
    if (empty($config_data)) {
      return FALSE;
    }

    // Check if configuration is already in new state.
    $merged_data = NestedArray::mergeDeep($expected_configuration, $configuration);
    if (empty(DiffArray::diffAssocRecursive($merged_data, $config_data))) {
      return TRUE;
    }

    if (!empty($expected_configuration) && DiffArray::diffAssocRecursive($expected_configuration, $config_data)) {
      return FALSE;
    }

    // Delete configuration keys from config.
    if (!empty($delete_keys)) {
      foreach ($delete_keys as $key_path) {
        NestedArray::unsetValue($config_data, $key_path);
      }
    }

    $config->setData(NestedArray::mergeDeep($config_data, $configuration));
    $config->save();

    return TRUE;
  }

}
