<?php

namespace Drupal\update_helper\Events;

use DrupalCodeGenerator\Command\BaseGenerator;
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
   * @var \DrupalCodeGenerator\Command\BaseGenerator
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
   * The command questions.
   *
   * @var array
   */
  protected $questions = [];

  /**
   * The collected variables.
   *
   * @var array
   */
  protected $vars;

  /**
   * Command interact event constructor.
   *
   * @param \DrupalCodeGenerator\Command\BaseGenerator $command
   *   Command that for which this event is triggered.
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   Input interface.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Output interface.
   * @param array $questions
   *   Te interact questions.
   */
  public function __construct(BaseGenerator $command, InputInterface $input, OutputInterface $output, array $vars) {
    $this->command = $command;
    $this->input = $input;
    $this->output = $output;
    $this->vars = $vars;
  }

  /**
   * Command that for what this event is triggered.
   *
   * @return \DrupalCodeGenerator\Command\BaseGenerator
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

  /**
   * The command questions.
   *
   * @return array
   *   All the questions.
   */
  public function getQuestions() {
    return $this->questions;
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

  public function setVars(array $vars) {
    $this->vars = $vars;
  }

  public function setQuestions($questions) {
    $this->questions = $questions;
  }
}
