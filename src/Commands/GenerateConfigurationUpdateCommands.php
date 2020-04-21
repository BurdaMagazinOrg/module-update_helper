<?php

namespace Drupal\update_helper\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drupal\update_helper\Interact;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A Drush commandfile.
 */
class GenerateConfigurationUpdateCommands extends DrushCommands {

  /**
   * Generate configuration update.
   *
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
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
   * @command generate:configuration:update
   * @aliases gcu
   */
  public function generateConfigurationUpdate(array $options = [
    'module' => InputOption::VALUE_REQUIRED,
    'update-n' => InputOption::VALUE_REQUIRED,
    'description' => InputOption::VALUE_REQUIRED,
    'include-modules' => InputOption::VALUE_REQUIRED,
    'from-active' => FALSE,
  ]): void {
    $this->logger()->success(print_r($options, TRUE));
    if ($options['from-active']) {
      $this->logger()->success('from active is true');
    }
    else {
      $this->logger()->success('from active is false');
    }

  }

  /**
   * Ask user for required option.
   *
   * @hook interact generate:configuration:update
   */
  public function interact(InputInterface $input, OutputInterface $output, AnnotationData $annotationData): void {
    $outputStyle = new SymfonyStyle($input, $output);
    $interact = new Interact($input, $outputStyle);
    $interact->interactGenerateConfigurationUpdate();
  }

}
