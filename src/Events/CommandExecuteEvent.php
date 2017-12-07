<?php

namespace Drupal\update_helper\Events;

use Drupal\Console\Core\Command\Command;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event for command execute.
 *
 * @package Drupal\update_helper\Events
 */
class CommandExecuteEvent extends Event {

  /**
   * Console command for this event.
   *
   * @var \Drupal\Console\Core\Command\Command
   */
  protected $command;

  /**
   * Module name.
   *
   * @var string
   */
  protected $module;

  /**
   * Update number.
   *
   * @var int
   */
  protected $updateNumber;

  /**
   * Command options.
   *
   * @var array
   */
  protected $commandOptions;

  /**
   * Flag if execution was successful.
   *
   * @var bool
   */
  protected $successful;

  /**
   * Command execute event constructor.
   *
   * @param \Drupal\Console\Core\Command\Command $command
   *   Command that for which this event is triggered.
   * @param string $module
   *   Module name.
   * @param int $update_number
   *   Update number.
   * @param array $command_options
   *   Command options.
   * @param bool $successful
   *   Successful execution of command.
   */
  public function __construct(Command $command, $module, $update_number, array $command_options, $successful) {
    $this->command = $command;
    $this->module = $module;
    $this->updateNumber = $update_number;
    $this->commandOptions = $command_options;
    $this->successful = $successful;
  }

  /**
   * Get drupal console command.
   *
   * @return \Drupal\Console\Core\Command\Command
   *   Returns drupal console command.
   */
  public function getCommand() {
    return $this->command;
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
   * Get update number.
   *
   * @return int
   *   Returns update number.
   */
  public function getUpdateNumber() {
    return $this->updateNumber;
  }

  /**
   * Get options.
   *
   * @return array
   *   Returns options.
   */
  public function getOptions() {
    return $this->commandOptions;
  }

  /**
   * Returns is command execution successful.
   *
   * @return bool
   *   Returns status of command execution.
   */
  public function getSuccessful() {
    return $this->successful;
  }

}
