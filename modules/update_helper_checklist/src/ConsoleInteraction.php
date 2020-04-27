<?php

namespace Drupal\update_helper_checklist;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The console Interaction helper.
 *
 * @package Drupal\update_helper
 */
class ConsoleInteraction {

  /**
   * Update checklist service.
   *
   * @var \Drupal\update_helper_checklist\UpdateChecklist
   */
  protected $updateChecklist;

  /**
   * ConsoleInteraction constructor.
   *
   * @param \Drupal\update_helper_checklist\UpdateChecklist $updateChecklist
   *   The update checklist service.
   */
  public function __construct(UpdateChecklist $updateChecklist) {
    $this->updateChecklist = $updateChecklist;
  }

  /**
   * Interaction for the generate:configuration:update command.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   The CLI input.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The CLI output.
   */
  public function interactGenerateConfigurationUpdate(InputInterface $input, OutputInterface $output): void {
    $outputStyle = new SymfonyStyle($input, $output);

    $updateVersion = $input->getOption('update-version');
    $updateDescription = $input->getOption('update-description');
    $successMessage = $input->getOption('success-message');
    $failureMessage = $input->getOption('failure-message');

    if (empty($updateVersion)) {
      $updateVersions = $this->updateChecklist->getUpdateVersions($input->getOption('module'));

      $updateVersion = $outputStyle->ask('Please enter a update version for checklist collection', $updateVersions[array_key_last($updateVersions)]);
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
