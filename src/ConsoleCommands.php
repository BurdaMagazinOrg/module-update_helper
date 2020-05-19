<?php

namespace Drupal\update_helper;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\update_helper\Generator\ConfigurationUpdateGenerator;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The console command helper.
 *
 * @package Drupal\update_helper
 */
class ConsoleCommands {

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $extensionList;

  /**
   * Update generator for configuration update hook.
   *
   * @var \Drupal\update_helper\Generator\ConfigurationUpdateGenerator
   */
  protected $generator;

  /**
   * Interact constructor.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $extensionList
   *   The module extension list.
   * @param \Drupal\update_helper\Generator\ConfigurationUpdateGenerator $generator
   *   Configuration update generator.
   */
  public function __construct(ModuleExtensionList $extensionList, ConfigurationUpdateGenerator $generator) {
    $this->extensionList = $extensionList;
    $this->generator = $generator;
  }

  /**
   * Execution of the generate:configuration:update command.
   *
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The CLI output style.
   *
   * @throws \Drush\Exceptions\UserAbortException
   */
  public function executeGenerateConfigurationUpdate(array $options, SymfonyStyle $io): void {
    if (!$io->confirm(dt('Do you want proceed with generating the update?'))) {
      throw new UserAbortException();
    }

    $module = $options['module'];
    $update_number = $options['update-n'];

    $last_update_number = drupal_get_installed_schema_version($module);
    if ($update_number <= $last_update_number) {
      throw new \InvalidArgumentException(
        dt('The update number "!number" is not valid', $update_number)
      );
    }

    $include_modules = $options['include-modules'];
    $from_active = $options['from-active'];

    // Execute configuration update generation.
    if ($this->generator->generate($module, $update_number, $include_modules, $from_active)) {
      $io->note(dt('Configuration update is successfully generated.'));
    }
    else {
      $io->note(dt('There are no configuration changes that should be exported for the update.'));
    }
  }

  /**
   * Interaction for the generate:configuration:update command.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   The CLI input.
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The CLI output style.
   */
  public function interactGenerateConfigurationUpdate(InputInterface $input, SymfonyStyle $io): void {
    $extensions = $this->getExtensions();
    $defaultExtension = NULL;

    foreach ($extensions as $extensionName => $extension) {
      if ($extension->getType() === 'profile') {
        $defaultExtension = $extensionName;
        break;
      }
    }

    $module = $input->getOption('module');
    $updateNumber = $input->getOption('update-n');
    $description = $input->getOption('description');
    $includeModules = $input->getOption('include-modules');

    if (empty($module)) {
      $question = new Question('Enter a module', $defaultExtension);
      $question->setAutocompleterValues(array_keys($extensions));

      $module = $io->askQuestion($question);
      $input->setOption('module', $module);
    }

    if (empty($updateNumber)) {
      $lastUpdate = drupal_get_installed_schema_version($module);
      $nextUpdate = $lastUpdate ? ($lastUpdate + 1) : 8001;

      $updateNumber = $io->ask(
        'Please provide the number for update hook to be added',
        $nextUpdate,
        static function ($update_number) use ($lastUpdate) {
          if (!is_numeric($update_number) || $update_number <= $lastUpdate) {
            throw new \InvalidArgumentException(
              dt(
                'The update number "!number" is not valid',
                ['!number' => $update_number]
              )
            );
          }
          return $update_number;
        }
      );

      $input->setOption('update-n', $updateNumber);
    }

    if (empty($description)) {
      $description = $io->ask('Please enter a description text for update. This will be used as the comment for update hook.', 'Configuration update.');
      $input->setOption('description', $description);
    }

    if (empty($includeModules)) {
      $includeModules = $io->ask(' Provide a comma-separated list of modules which configurations should be included in update (empty for all).', ' ');
      $input->setOption('include-modules', $includeModules);
    }

  }

  /**
   * Get installed non_core extensions.
   *
   * @return \Drupal\Core\Extension\Extension[]
   *   The list of installed non-core extensions keyed by the extension name.
   */
  protected function getExtensions(): array {
    $extensions = array_filter($this->extensionList->getList(),
      static function ($extension): bool {
        return ($extension->origin !== 'core');
      });

    ksort($extensions);
    return $extensions;
  }

}
