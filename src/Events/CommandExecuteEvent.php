<?php

namespace Drupal\update_helper\Events;

use Drupal\Console\Core\Command\Command;
use DrupalCodeGenerator\Command\BaseGenerator;
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
   * The collected variables.
   *
   * @var array
   */
  protected $vars;


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
  public function __construct(BaseGenerator $command, array $vars) {
    $this->command = $command;
    $this->vars = $vars;

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
   * The command questions.
   *
   * @return array
   *   All the questions.
   */
  public function getVars() {
    return $this->vars;
  }

}
