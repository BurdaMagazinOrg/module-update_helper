<?php

namespace Drupal\update_helper\Generators;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\update_helper\ConfigHandler;
use Drupal\update_helper\Events\CommandExecuteEvent;
use Drupal\update_helper\Events\CommandInteractEvent;
use Drupal\update_helper\Events\UpdateHelperEvents;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use DrupalCodeGenerator\Command\DrupalGenerator;
use DrupalCodeGenerator\Asset\AssetCollection;

/**
 * Implements update_helper:configuration-update command.
 */
class ConfigurationUpdate extends DrupalGenerator {

  /**
   * {@inheritdoc}
   */
  protected string $name = 'update_helper:configuration-update';

  /**
   * {@inheritdoc}
   */
  protected string $description = 'Generates a configuration update';

  /**
   * {@inheritdoc}
   */
  protected string $alias = 'config-update';

  /**
   * The module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $extensionList;

  /**
   * Drupal\update_helper\ConfigHandler definition.
   *
   * @var \Drupal\update_helper\ConfigHandler
   */
  protected $configHandler;

  /**
   * Drupal\Core\Extension\ModuleHandler definition.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public function __construct(ModuleExtensionList $extension_list, EventDispatcherInterface $event_dispatcher, ModuleHandlerInterface $module_handler, ConfigHandler $config_handler) {
    parent::__construct($this->name);

    $this->extensionList = $extension_list;
    $this->eventDispatcher = $event_dispatcher;
    $this->configHandler = $config_handler;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars): void {
    $extensions = $this->getExtensions();
    $question = new Question('Enter a module/profile');
    $question->setAutocompleterValues(array_keys($extensions));
    $question->setValidator(function ($module_name) use ($extensions) {
      if (empty($module_name) || !array_key_exists($module_name, $extensions)) {
        throw new \InvalidArgumentException(
          sprintf(
            'The module name "%s" is not valid',
            $module_name
          )
        );
      }
      return $module_name;
    });

    $vars['module'] = $this->io->askQuestion($question);

    /** @var \Drupal\Core\Update\UpdateHookRegistry $service */
    $service = \Drupal::service('update.update_hook_registry');
    $lastUpdate = $service->getInstalledVersion($vars['module']);
    $nextUpdate = $lastUpdate > 0 ? ($lastUpdate + 1) : 8001;

    $vars['update-n'] = $this->ask('Please provide the number for update hook to be added', $nextUpdate, function ($update_number) use ($lastUpdate) {
      if ($update_number === NULL || $update_number === '' || !is_numeric($update_number) || $update_number <= $lastUpdate) {
        throw new \InvalidArgumentException(
          sprintf(
            'The update number "%s" is not valid',
            $update_number
          )
        );
      }
      return $update_number;
    });

    $vars['description'] = $this->ask('Please enter a description text for update. This will be used as the comment for update hook.', 'Configuration update.', '::validateRequired');

    $enabled_modules = array_filter($this->moduleHandler->getModuleList(), function (Extension $extension) {
      return ($extension->getType() === 'module' || $extension->getType() === 'profile');
    });
    $enabled_modules = array_keys($enabled_modules);

    $question = new Question('Provide a comma-separated list of modules which configurations should be included in update.', implode(',', $enabled_modules));
    $question->setNormalizer(function ($input) {
      return explode(',', $input);
    });
    $question->setValidator(function ($modules) use ($enabled_modules) {
      $not_enabled_modules = array_diff($modules, $enabled_modules);
      if ($not_enabled_modules) {
        throw new \InvalidArgumentException(
          sprintf(
            'These modules are not enabled: %s',
            implode(', ', $not_enabled_modules)
          )
        );
      }
      return $modules;
    });
    $vars['include-modules'] = $this->io->askQuestion($question);

    $vars['from-active'] = $this->confirm('Generate update from active configuration in database to configuration in Yml files?');

    // Get additional options provided by other modules.
    $event = new CommandInteractEvent($vars);
    $this->eventDispatcher->dispatch(UpdateHelperEvents::COMMAND_GCU_INTERACT, $event);

    foreach ($event->getQuestions() as $key => $question) {
      $vars[$key] = $this->io->askQuestion($question);
    }

    // Get patch data and save it into file.
    $patch_data = $this->configHandler->generatePatchFile($vars['include-modules'], $vars['from-active']);

    if (!empty($patch_data)) {

      // Get additional options provided by other modules.
      $event = new CommandExecuteEvent($vars);
      $this->eventDispatcher->dispatch(UpdateHelperEvents::COMMAND_GCU_EXECUTE, $event);

      foreach ($event->getTemplatePaths() as $path) {
        $this->getHelper('renderer')->prependPath($path);
      }

      $this->assets = new AssetCollection($event->getAssets());

      $patch_file_path = $this->configHandler->getPatchFile($vars['module'], static::getUpdateFunctionName($vars['module'], $vars['update-n']), TRUE);

      // Add the patchfile.
      $this->addFile($patch_file_path)
        ->content($patch_data);
    }
    else {
      $this->io->write('There are no configuration changes that should be exported for the update.', TRUE);
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

  /**
   * Get update hook function name.
   *
   * @param string $module_name
   *   Module name.
   * @param string $update_number
   *   Update number.
   *
   * @return string
   *   Returns update hook function name.
   */
  public static function getUpdateFunctionName($module_name, $update_number) {
    return $module_name . '_update_' . $update_number;
  }

}
