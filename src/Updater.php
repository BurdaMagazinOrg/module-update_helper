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
use Drupal\user\SharedTempStoreFactory;
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
   * Temp store factory.
   *
   * @var \Drupal\user\SharedTempStoreFactory
   */
  protected $tempStoreFactory;

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
   * @param \Drupal\user\SharedTempStoreFactory $tempStoreFactory
   *   A temporary key-value store service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $moduleInstaller
   *   Module installer service.
   * @param \Drupal\config_update\ConfigRevertInterface $configReverter
   *   Config reverter service.
   * @param \Drupal\update_helper\ConfigHandler $configHandler
   *   Configuration handler service.
   * @param \Drupal\update_helper\UpdateLogger $logger
   *   Update logger.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher.
   */
  public function __construct(SharedTempStoreFactory $tempStoreFactory, ConfigFactoryInterface $configFactory, ModuleInstallerInterface $moduleInstaller, ConfigRevertInterface $configReverter, ConfigHandler $configHandler, UpdateLogger $logger, EventDispatcherInterface $event_dispatcher) {
    $this->tempStoreFactory = $tempStoreFactory;
    $this->configFactory = $configFactory;
    $this->moduleInstaller = $moduleInstaller;
    $this->configReverter = $configReverter;
    $this->configHandler = $configHandler;
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
    $modulesInstalledSuccessfully = TRUE;

    foreach ($modules as $module) {
      try {
        if ($this->moduleInstaller->install([$module])) {
          $this->logger->info($this->t('Module @module is successfully enabled.', ['@module' => $module]));
        }
        else {
          $this->logger->warning($this->t('Unable to enable @module.', ['@module' => $module]));
          $modulesInstalledSuccessfully = FALSE;
        }
      }
      catch (MissingDependencyException $e) {
        $this->logger->warning($this->t('Unable to enable @module because of missing dependencies.', ['@module' => $module]));
        $modulesInstalledSuccessfully = FALSE;
      }
    }

    return $modulesInstalledSuccessfully;
  }

  /**
   * {@inheritdoc}
   */
  public function importConfigs(array $config_List) {
    $successfulImport = TRUE;

    // Import configurations.
    foreach ($config_List as $fullConfigName) {
      try {
        $configName = ConfigName::createByFullName($fullConfigName);

        if (!$this->configReverter->import($configName->getType(), $configName->getName())) {
          throw new \Exception('Config not found');
        }
        $this->logger->info($this->t('Configuration @full_name has been successfully imported.', [
          '@full_name' => $fullConfigName,
        ]));
      }
      catch (\Exception $e) {
        $successfulImport = FALSE;

        $this->logger->warning($this->t('Unable to import @full_name config.', [
          '@full_name' => $fullConfigName,
        ]));
      }
    }

    return $successfulImport;
  }

  /**
   * {@inheritdoc}
   */
  public function executeUpdate($module, $update_definition_name) {
    $successfulUpdate = TRUE;

    $updateDefinitions = $this->configHandler->loadUpdate($module, $update_definition_name);
    foreach ($updateDefinitions as $configName => $configChange) {
      $expectedConfig = $configChange['expected_config'];
      $updateActions = $configChange['update_actions'];

      // Define configuration keys that should be deleted.
      $deleteKeys = [];
      if (isset($updateActions['delete'])) {
        $deleteKeys = $this->getFlatKeys($updateActions['delete']);
      }

      $newConfig = [];
      // Add configuration that is changed.
      if (isset($updateActions['change'])) {
        $newConfig = NestedArray::mergeDeep($newConfig, $updateActions['change']);
      }

      // Add configuration that is added.
      if (isset($updateActions['add'])) {
        $newConfig = NestedArray::mergeDeep($newConfig, $updateActions['add']);
      }

      if ($this->updateConfig($configName, $newConfig, $expectedConfig, $deleteKeys)) {
        $this->logger->info($this->t('Configuration @configName has been successfully updated.', ['@configName' => $configName]));
      }
      else {
        $successfulUpdate = FALSE;
        $this->logger->warning($this->t('Unable to update configuration for @configName.', ['@configName' => $configName]));
      }
    }

    // Dispatch event after update has finished.
    $event = new ConfigurationUpdateEvent($module, $update_definition_name, $successfulUpdate);
    $this->eventDispatcher->dispatch(UpdateHelperEvents::CONFIGURATION_UPDATE, $event);

    return $successfulUpdate;
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
   * @param array $nestedArray
   *   Array with nested keys.
   *
   * @return array
   *   List of flattened keys.
   */
  protected function getFlatKeys(array $nestedArray) {
    $keys = [];
    foreach ($nestedArray as $key => $value) {
      if (is_array($value) && !empty($value)) {
        $listOfSubKeys = $this->getFlatKeys($value);

        foreach ($listOfSubKeys as $subKeys) {
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
   * @param string $configName
   *   Configuration name that should be updated.
   * @param array $configuration
   *   Configuration array to update.
   * @param array $expectedConfiguration
   *   Only if current config is same like old config we are updating.
   * @param array $deleteKeys
   *   List of parent keys to remove. @see NestedArray::unsetValue()
   *
   * @return bool
   *   Returns TRUE if update of configuration was successful.
   */
  protected function updateConfig($configName, array $configuration, array $expectedConfiguration = [], array $deleteKeys = []) {
    $config = $this->configFactory->getEditable($configName);

    $configData = $config->get();

    // Check that configuration exists before executing update.
    if (empty($configData)) {
      return FALSE;
    }

    // Check if configuration is already in new state.
    $mergedData = NestedArray::mergeDeep($expectedConfiguration, $configuration);
    if (empty(DiffArray::diffAssocRecursive($mergedData, $configData))) {
      return TRUE;
    }

    if (!empty($expectedConfiguration) && DiffArray::diffAssocRecursive($expectedConfiguration, $configData)) {
      return FALSE;
    }

    // Delete configuration keys from config.
    if (!empty($deleteKeys)) {
      foreach ($deleteKeys as $keyPath) {
        NestedArray::unsetValue($configData, $keyPath);
      }
    }

    $config->setData(NestedArray::mergeDeep($configData, $configuration));
    $config->save();

    return TRUE;
  }

}
