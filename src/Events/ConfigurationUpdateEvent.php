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

}
