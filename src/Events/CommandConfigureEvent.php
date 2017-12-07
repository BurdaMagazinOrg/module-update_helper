<?php

namespace Drupal\update_helper\Events;

use Drupal\Console\Core\Command\Command;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event for command configure.
 *
 * @package Drupal\update_helper\Events
 */
class CommandConfigureEvent extends Event {

  /**
   * Console command for this event.
   *
   * @var \Drupal\Console\Core\Command\Command
   */
  protected $command;

  /**
   * CommandOptionsEvent constructor.
   *
   * @param \Drupal\Console\Core\Command\Command $command
   *   Command that for which this event is triggered.
   */
  public function __construct(Command $command) {
    $this->command = $command;
  }

  /**
   * Command that for what this event is triggered.
   *
   * @return \Drupal\Console\Core\Command\Command
   *   Returns command.
   */
  public function getCommand() {
    return $this->command;
  }

  /**
   * Add option to command.
   *
   * @param string $name
   *   The option name.
   * @param string $shortcut
   *   The shortcut (can be null).
   * @param int $mode
   *   The option mode: One of the InputOption::VALUE_* constants.
   * @param string $description
   *   A description text.
   * @param mixed $default
   *   The default value (must be null for InputOption::VALUE_NONE).
   */
  public function addOption($name, $shortcut = NULL, $mode = NULL, $description = '', $default = NULL) {
    $this->command->addOption($name, $shortcut, $mode, $description, $default);
  }

}
