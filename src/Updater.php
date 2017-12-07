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
   * {@inheritdoc}
   */
  public function logger() {
    return $this->logger;
  }

  /**
   * {@inheritdoc}
   */
  public function installModules(array $modules) {
    $successful = TRUE;

    foreach ($modules as $module) {
      try {
        if ($this->moduleInstaller->install([$module])) {
          $this->logger->info($this->t('Module @module is successfully enabled.', ['@module' => $module]));
        }
        else {
          $this->logger->warning($this->t('Unable to enable @module.', ['@module' => $module]));
          $successful = FALSE;
        }
      }
      catch (MissingDependencyException $e) {
        $this->logger->warning($this->t('Unable to enable @module because of missing dependencies.', ['@module' => $module]));
        $successful = FALSE;
      }
    }

    return $successful;
  }

  /**
   * {@inheritdoc}
   */
  public function importConfigs(array $config_list) {
    $successful = TRUE;

    // Import configurations.
    foreach ($config_list as $full_config_name) {
      try {
        $config_name = ConfigName::createByFullName($full_config_name);

        if (!$this->configReverter->import($config_name->getType(), $config_name->getName())) {
          throw new \Exception('Config not found');
        }
        $this->logger->info($this->t('Configuration @full_name has been successfully imported.', [
          '@full_name' => $full_config_name,
        ]));
      }
      catch (\Exception $e) {
        $successful = FALSE;

        $this->logger->warning($this->t('Unable to import @full_name config.', [
          '@full_name' => $full_config_name,
        ]));
      }
    }

    return $successful;
  }

  /**
   * {@inheritdoc}
   */
  public function executeUpdate($module, $update_definition_name) {
    $successful = TRUE;

    $update_definitions = $this->configHandler->loadUpdate($module, $update_definition_name);
    foreach ($update_definitions as $configName => $configChange) {
      $expected_config = $configChange['expected_config'];
      $update_actions = $configChange['update_actions'];

      // Define configuration keys that should be deleted.
      $delete_keys = [];
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

      if ($this->updateConfig($configName, $new_config, $expected_config, $delete_keys)) {
        $this->logger->info($this->t('Configuration @configName has been successfully updated.', ['@configName' => $configName]));
      }
      else {
        $successful = FALSE;
        $this->logger->warning($this->t('Unable to update configuration for @configName.', ['@configName' => $configName]));
      }
    }

    // Dispatch event after update has finished.
    $event = new ConfigurationUpdateEvent($module, $update_definition_name, $successful);
    $this->eventDispatcher->dispatch(UpdateHelperEvents::CONFIGURATION_UPDATE, $event);

    return $successful;
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
