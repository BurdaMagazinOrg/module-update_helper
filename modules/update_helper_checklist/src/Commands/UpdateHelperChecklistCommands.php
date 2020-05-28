<?php

namespace Drupal\update_helper_checklist\Commands;

use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Consolidation\AnnotatedCommand\AnnotationData;
use Drupal\update_helper_checklist\ConsoleInteraction;
use DrupalCodeGenerator\Command\BaseGenerator;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Extending the generate:configuration:update command.
 */
class UpdateHelperChecklistCommands extends DrushCommands {

  /**
   * The user interaction helper.
   *
   * @var \Drupal\update_helper_checklist\ConsoleInteraction
   */
  protected $consoleInteraction;

  /**
   * UpdateHelperChecklistCommands constructor.
   *
   * @param \Drupal\update_helper_checklist\ConsoleInteraction $consoleCommands
   *   The user interaction helper.
   */
  public function __construct(ConsoleInteraction $consoleCommands) {
    parent::__construct();

    $this->consoleInteraction = $consoleCommands;
  }

  /**
   * Additional options for generate configuration update command.
   *
   * @param \Consolidation\AnnotatedCommand\AnnotatedCommand $command
   *   The command.
   *
   * @hook option generate:configuration:update
   */
  public function options(AnnotatedCommand $command): void {
    $command->addOption(
      'update-version',
      '',
      InputOption::VALUE_REQUIRED,
      'Update version for checklist collection.',
      );

    $command->addOption(
      'update-description',
      '',
      InputOption::VALUE_REQUIRED,
      'Detailed update description for the checklist entry.',
      );

    $command->addOption(
      'failure-message',
      '',
      InputOption::VALUE_REQUIRED,
      'Failure message for the checklist entry.',
      );

    $command->addOption(
      'success-message',
      '',
      InputOption::VALUE_REQUIRED,
      'Success message for checklist entry.',
      );
  }

  /**
   * Ask user for required option.
   *
   * @hook interact generate:configuration:update
   */
  public function interact(InputInterface $input, OutputInterface $output, AnnotationData $annotationData): void {
    $this->consoleInteraction->interactGenerateConfigurationUpdate($input, $output);
  }

}
