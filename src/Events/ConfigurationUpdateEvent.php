<?php

namespace Drupal\update_helper\Events;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class ConfigurationUpdateFinishedEvent.
 *
 * @package Drupal\update_helper\Events
 */
class ConfigurationUpdateEvent extends Event {

  protected $module;

  protected $updateName;

  protected $successful;

  /**
   * Configuration update event.
   *
   * @param string $module
   *   Module name.
   * @param string $updateName
   *   Update name.
   * @param bool $successful
   *   Is update finished successfully or not.
   */
  public function __construct($module, $updateName, $successful) {
    $this->module = $module;
    $this->updateName = $updateName;
    $this->successful = $successful;
  }

  /**
   * Get module name.
   *
   * @return string
   *   Returns module name.
   */
  public function getModule() {
    return $this->module;
  }

  /**
   * Get update name.
   *
   * @return string
   *   Returns update name.
   */
  public function getUpdateName() {
    return $this->updateName;
  }

  /**
   * Get status for configuration update.
   *
   * @return bool
   *   Returns status for configuration update.
   */
  public function isSuccessful() {
    return $this->successful;
  }

}
