<?php

namespace Drupal\update_helper_checklist\Commands;

use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Consolidation\AnnotatedCommand\AnnotationData;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateHelperChecklistCommands extends DrushCommands {

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
    $outputStyle = new SymfonyStyle($input, $output);

    $updateVersion = $this->input->getOption('update-version');
    $updateDescription = $this->input->getOption('update-description');
    $successMessage = $this->input->getOption('success-message');
    $failureMessage = $this->input->getOption('failure-message');

    if (empty($updateVersion)) {
      $updateVersion = $outputStyle->ask('Please enter a update version for checklist collection');
      $input->setOption('update-version', $updateVersion);
    }

    if (empty($updateDescription)) {
      $updateDescription = $outputStyle->ask('Please enter a detailed update description that will be used for checklist', 'This configuration update will update site configuration to newly provided configuration.');
      $input->setOption('update-description', $updateDescription);
    }

    if (empty($successMessage)) {
      $successMessage = $outputStyle->ask('Please enter a message that will be displayed in checklist entry when the update is successful', 'The configuration was successfully updated.');
      $input->setOption('success-message', $successMessage);
    }

    if (empty($failureMessage)) {
      $failureMessage = $outputStyle->ask('Please enter a message that will be displayed in checklist entry when the update has failed', 'The update of the configuration has failed.');
      $input->setOption('failure-message', $failureMessage);
    }
  }

}
