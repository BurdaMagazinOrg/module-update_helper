<?php

namespace Drupal\update_helper;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Interact {

  /**
   * The input interface.
   *
   * @var \Symfony\Component\Console\Input\InputInterface
   */
  protected $input;

  /**
   * The Output decorator.
   *
   * @var \Symfony\Component\Console\Style\SymfonyStyle
   */
  protected $outputStyle;

  /**
   * Interact constructor.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   The input interface.
   * @param \Symfony\Component\Console\Style\SymfonyStyle $outputStyle
   *   The output interface.
   */
  public function __construct(InputInterface $input, SymfonyStyle $outputStyle) {
    $this->input = $input;
    $this->outputStyle = $outputStyle;
  }

  /**
   * Interaction for the generate:configuration:update command.
   */
  public function interactGenerateConfigurationUpdate(): void {
    $module = $this->input->getOption('module');
    $updateNumber = $this->input->getOption('update-n');
    $description = $this->input->getOption('description');
    $includeModules = $this->input->getOption('include-modules');

    if (empty($module)) {
      $module = $this->outputStyle->ask('Enter a module');
      $this->input->setOption('module', $module);
    }

    if (empty($updateNumber)) {
      $updateNumber = $this->outputStyle->ask('Please provide the number for update hook to be added');
      $this->input->setOption('update-n', $updateNumber);
    }

    if (empty($description)) {
      $description = $this->outputStyle->ask('Please enter a description text for update. This will be used as the comment for update hook.', 'Configuration update.');
      $this->input->setOption('description', $description);
    }

    if (empty($includeModules)) {
      $includeModules = $this->outputStyle->ask(' Provide a comma-separated list of modules which configurations should be included in update (empty for all).');
      $this->input->setOption('include-modules', $includeModules);
    }

  }

}
