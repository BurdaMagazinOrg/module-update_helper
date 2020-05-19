<?php

namespace Drupal\update_helper\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drupal\update_helper\ConsoleCommands;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The Drush command file for the generate:configuration:update command.
 */
class GenerateConfigurationUpdateCommands extends DrushCommands {

  /**
   * The user interaction helper.
   *
   * @var \Drupal\update_helper\ConsoleCommands
   */
  protected $consoleCommands;

  /**
   * GenerateConfigurationUpdateCommands constructor.
   *
   * @param \Drupal\update_helper\ConsoleCommands $consoleCommands
   *   The user interaction helper.
   */
  public function __construct(ConsoleCommands $consoleCommands) {
    parent::__construct();
    $this->consoleCommands = $consoleCommands;
  }

  /**
   * Generate configuration update.
   *
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   * @command generate:configuration:update
   * @option module
   *   The module name where the update definition will be saved and update
   *   hook generated.
   * @option update-n
   *   The module name where the update definition will be saved and update
   *   hook generated.
   * @option description
   *   Description for update (will be used as comment for the update hook).
   * @option include-modules
   *   List of modules which configurations should be included in the update
   *   (empty for all).
   * @option from-active
   *   Generate update from active configuration in database to configuration
   *   in Yml files.
   * @usage drush generate:configuration:update --module="update_helper" --update-n="8001" --description="Configuration update."
   *   Generate a configuration update and update hook with update
   *   number "8001" for the "update_helper" module.
   *
   * @aliases gcu
   *
   * @throws \Drush\Exceptions\UserAbortException
   */
  public function generateConfigurationUpdate(array $options = [
    'module' => InputOption::VALUE_REQUIRED,
    'update-n' => InputOption::VALUE_REQUIRED,
    'description' => InputOption::VALUE_REQUIRED,
    'include-modules' => InputOption::VALUE_OPTIONAL,
    'from-active' => FALSE,
  ]): void {
    $this->logger()->notice(print_r($options, TRUE));
    $this->consoleCommands->executeGenerateConfigurationUpdate($options, $this->io());
  }

  /**
   * Ask user for required option.
   *
   * @hook interact generate:configuration:update
   */
  public function interact(InputInterface $input, OutputInterface $output, AnnotationData $annotationData): void {
    $this->consoleCommands->interactGenerateConfigurationUpdate($input, $this->io());
  }

}
