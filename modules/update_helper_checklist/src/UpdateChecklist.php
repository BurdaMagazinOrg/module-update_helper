<?php

namespace Drupal\update_helper_checklist;

use Drupal\checklistapi\ChecklistapiChecklist;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\update_helper_checklist\Entity\Update;
use Symfony\Component\Yaml\Yaml;

/**
 * Update checklist service.
 *
 * TODO: Need tests and a lot!
 *
 * @package Drupal\update_helper_checklist
 */
class UpdateChecklist {

  /**
   * Update checklist file for configuration updates.
   *
   * @var string
   */
  public static $updateChecklistFileName = 'updates_checklist.yml';

  /**
   * Site configFactory object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Module installer service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The account object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The Checklist API object.
   *
   * @var \Drupal\checklistapi\ChecklistapiChecklist
   */
  protected $checklist;

  /**
   * Update checklist constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   */
  public function __construct(ConfigFactoryInterface $configFactory, ModuleHandlerInterface $moduleHandler, AccountInterface $account) {
    $this->configFactory = $configFactory;
    $this->moduleHandler = $moduleHandler;
    $this->account = $account;
  }

  /**
   * Get checklist.
   *
   * @return \Drupal\checklistapi\ChecklistapiChecklist|false
   *   Returns checklist.
   */
  protected function getChecklist() {
    if (!$this->checklist) {
      $this->checklist = checklistapi_checklist_load('update_helper_checklist');
    }

    return $this->checklist;
  }

  /**
   * Marks a list of updates as successful.
   *
   * @param array $names
   *   Array of update ids per module.
   * @param bool $checkListPoints
   *   Indicates the corresponding checkbox should be checked.
   */
  public function markUpdatesSuccessful(array $names, $checkListPoints = TRUE) {
    $this->setSuccessfulByHook($names, TRUE);

    if ($checkListPoints) {
      $this->checkListPoints($names);
    }
  }

  /**
   * Marks a list of updates as failed.
   *
   * @param array $names
   *   Array of update ids per module.
   */
  public function markUpdatesFailed(array $names) {
    $this->setSuccessfulByHook($names, FALSE);
  }

  /**
   * Marks a list of updates.
   *
   * @param bool $status
   *   Checkboxes enabled or disabled.
   */
  public function markAllUpdates($status = TRUE) {
    $keys = [];
    foreach ($this->getChecklist()->items as $version_items) {
      foreach ($version_items as $key => $item) {
        if (is_array($item)) {
          $keys[] = $key;
        }
      }
    }

    $this->setSuccessfulByHook($keys, $status);
    $this->checkAllListPoints($status);
  }

  /**
   * Set status for update keys.
   *
   * @param array $module_update_list
   *   Keys for update entries per module.
   * @param bool $status
   *   Status that should be set.
   */
  protected function setSuccessfulByHook(array $module_update_list, $status = TRUE) {
    $checklist_keys = $this->getFlatChecklistKeys($module_update_list);

    foreach ($checklist_keys as $update_key) {
      if ($update = Update::load($update_key)) {
        $update->setSuccessfulByHook($status)->save();
      }
      else {
        Update::create(
          [
            'id' => $update_key,
            'successful_by_hook' => $status,
          ]
        )->save();
      }
    }
  }

  /**
   * Get flat list of checklist keys for module updates.
   *
   * @param array $module_update_list
   *   Keys for update entries per module.
   *
   * @return array
   *   Returns flattened array of checklist update entries.
   */
  public function getFlatChecklistKeys(array $module_update_list) {
    $flatKeys = [];

    foreach ($module_update_list as $module_name => $updates) {
      foreach ($updates as $update) {
        $flatKeys[] = str_replace('.', '_', $module_name . ':' . $update);
      }
    }

    return $flatKeys;
  }

  /**
   * Checks an array of bulletpoints on a checklist.
   *
   * @param array $module_update_list
   *   Array of the bulletpoints.
   */
  protected function checkListPoints(array $module_update_list) {
    /** @var \Drupal\Core\Config\Config $update_check_list */
    $update_check_list = $this->configFactory
      ->getEditable('checklistapi.progress.update_helper_checklist');

    $user = $this->account->id();
    $time = time();

    $checklist_keys = $this->getFlatChecklistKeys($module_update_list);
    foreach ($checklist_keys as $name) {
      if ($update_check_list && !$update_check_list->get(ChecklistapiChecklist::PROGRESS_CONFIG_KEY . ".#items.$name")) {
        $update_check_list
          ->set(ChecklistapiChecklist::PROGRESS_CONFIG_KEY . ".#items.$name", [
            '#completed' => time(),
            '#uid' => $user,
          ]);
      }
    }

    $update_check_list
      ->set(ChecklistapiChecklist::PROGRESS_CONFIG_KEY . '.#completed_items', count($update_check_list->get(ChecklistapiChecklist::PROGRESS_CONFIG_KEY . ".#items")))
      ->set(ChecklistapiChecklist::PROGRESS_CONFIG_KEY . '.#changed', $time)
      ->set(ChecklistapiChecklist::PROGRESS_CONFIG_KEY . '.#changed_by', $user)
      ->save();
  }

  /**
   * Checks all the bulletpoints on a checklist.
   *
   * @param bool $status
   *   Checkboxes enabled or disabled.
   */
  protected function checkAllListPoints($status = TRUE) {
    /** @var \Drupal\Core\Config\Config $update_check_list */
    $update_check_list = $this->configFactory
      ->getEditable('checklistapi.progress.update_helper_checklist');

    $user = $this->account->id();
    $time = time();

    $update_check_list
      ->set(ChecklistapiChecklist::PROGRESS_CONFIG_KEY . '.#changed', $time)
      ->set(ChecklistapiChecklist::PROGRESS_CONFIG_KEY . '.#changed_by', $user);

    $exclude = [
      '#title',
      '#description',
      '#weight',
    ];

    foreach ($this->getChecklist()->items as $version_items) {
      foreach ($version_items as $item_name => $item) {
        if (!in_array($item_name, $exclude)) {
          if ($status) {
            $update_check_list
              ->set(ChecklistapiChecklist::PROGRESS_CONFIG_KEY . ".#items.$item_name", [
                '#completed' => $time,
                '#uid' => $user,
              ]);
          }
          else {
            $update_check_list
              ->clear(ChecklistapiChecklist::PROGRESS_CONFIG_KEY . ".#items.$item_name");
          }
        }
      }
    }

    $all_items = $update_check_list->get(ChecklistapiChecklist::PROGRESS_CONFIG_KEY . ".#items");
    $update_check_list
      ->set(ChecklistapiChecklist::PROGRESS_CONFIG_KEY . '.#completed_items', empty($all_items) ? 0 : count($all_items))
      ->save();
  }

  /**
   * Get update version from update checklist file.
   *
   * @param string $module
   *   Module name.
   *
   * @return array
   *   Returns update versions from update checklist file.
   */
  public function getUpdateVersions($module) {
    $module_directories = $this->moduleHandler->getModuleDirectories();

    if (empty($module_directories[$module])) {
      return [];
    }

    $updates_file = $module_directories[$module] . DIRECTORY_SEPARATOR . static::$updateChecklistFileName;
    if (!is_file($updates_file)) {
      return [];
    }

    $updates_checklist = Yaml::parse(file_get_contents($updates_file));

    return array_keys($updates_checklist);
  }

}
