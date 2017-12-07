<?php

namespace Drupal\update_helper\Events;

use Drupal\Console\Core\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event for command interactive.
 *
 * @package Drupal\update_helper\Events
 */
class CommandInteractEvent extends Event {

  /**
   * Console command for this event.
   *
   * @var \Drupal\Console\Core\Command\Command
   */
  protected $command;

  /**
   * Input interface for command.
   *
   * @var \Symfony\Component\Console\Input\InputInterface
   */
  protected $input;

  /**
   * Output interface.
   *
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

  /**
   * Command interact event constructor.
   *
   * @param \Drupal\Console\Core\Command\Command $command
   *   Command that for which this event is triggered.
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   Input interface.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Output interface.
   */
  public function __construct(Command $command, InputInterface $input, OutputInterface $output) {
    $this->command = $command;
    $this->input = $input;
    $this->output = $output;
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
   * Get console command input.
   *
   * @return \Symfony\Component\Console\Input\InputInterface
   *   Input interface for command.
   */
  public function getInput() {
    return $this->input;
  }

  /**
   * Get console command output.
   *
   * @return \Symfony\Component\Console\Output\OutputInterface
   *   Output interface for command.
   */
  public function getOutput() {
    return $this->output;
  }

}
