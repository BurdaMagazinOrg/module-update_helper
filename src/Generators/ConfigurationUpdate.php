<?php

namespace Drupal\update_helper\Generators;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\update_helper\ConfigHandler;
use Drupal\update_helper\Events\CommandExecuteEvent;
use Drupal\update_helper\Events\CommandInteractEvent;
use Drupal\update_helper\Events\UpdateHelperEvents;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Implements d8:configuration:update command.
 */
class ConfigurationUpdate extends BaseGenerator {

  /**
   * {@inheritdoc}
   */
  protected $name = 'd8:configuration:update';

  /**
   * {@inheritdoc}
   */
  protected $description = 'Generate a configuration update';

  /**
   * {@inheritdoc}
   */
  protected $alias = 'config-update';

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
    parent::__construct();

    $this->extensionList = $extension_list;
    $this->eventDispatcher = $event_dispatcher;
    $this->configHandler = $config_handler;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output) {

    $extensions = $this->getExtensions();

    $questions['module'] = new Question('Enter a module/profile');
    $questions['module']->setAutocompleterValues(array_keys($extensions));
    $questions['module']->setValidator(function ($module_name) use ($extensions) {
      if (empty($module_name) || !in_array($module_name, array_keys($extensions))) {
        throw new \InvalidArgumentException(
          dt(
            'The module name "!module_name" is not valid',
            ['!module_name' => $module_name]
          )
        );
      }
      return $module_name;
    });

    $vars = $this->collectVars($input, $output, $questions);

    $lastUpdate = drupal_get_installed_schema_version($vars['module']);
    $nextUpdate = $lastUpdate > 0 ? ($lastUpdate + 1) : 8001;

    $questions['update-n'] = new Question('Please provide the number for update hook to be added', $nextUpdate);
    $questions['update-n']->setValidator(function ($update_number) use ($lastUpdate) {
      if ($update_number === NULL || $update_number === '' || !is_numeric($update_number) || $update_number <= $lastUpdate) {
        throw new \InvalidArgumentException(
          dt(
            'The update number "!number" is not valid',
            ['!number' => $update_number]
          )
        );
      }
      return $update_number;
    });

    $questions['description'] = new Question('Please enter a description text for update. This will be used as the comment for update hook.', 'Configuration update.');
    $questions['description']->setValidator([Utils::class, 'validateRequired']);

    $enabled_modules = array_filter($this->moduleHandler->getModuleList(), function (Extension $extension) {
      return ($extension->getType() == 'module');
    });
    $enabled_modules = array_keys($enabled_modules);

    $questions['include-modules'] = new Question('Provide a comma-separated list of modules which configurations should be included in update.', implode(',', $enabled_modules));
    $questions['include-modules']->setNormalizer(function ($input) {
      return explode(',', $input);
    });
    $questions['include-modules']->setValidator(function ($modules) use ($enabled_modules) {
      $not_enabled_modules = array_diff($modules, $enabled_modules);
      if ($not_enabled_modules) {
        throw new \InvalidArgumentException(
          dt(
            'These modules are not enabled: !modules',
            ['!modules' => implode(', ', $not_enabled_modules)]
          )
        );
      }
      return $modules;
    });

    $questions['from-active'] = new ConfirmationQuestion('Generate update from active configuration in database to configuration in Yml files?');
    $questions['from-active']->setValidator([Utils::class, 'validateRequired']);

    $vars = $this->collectVars($input, $output, $questions);

    // Get additional options provided by other modules.
    $event = new CommandInteractEvent($vars);
    $this->eventDispatcher->dispatch(UpdateHelperEvents::COMMAND_GCU_INTERACT, $event);

    $vars = $this->collectVars($input, $output, $event->getQuestions());

    // Get patch data and save it into file.
    $patch_data = $this->configHandler->generatePatchFile($this->vars['include-modules'], $this->vars['from-active']);

    if (!empty($patch_data)) {

      // Get additional options provided by other modules.
      $event = new CommandExecuteEvent($vars);
      $this->eventDispatcher->dispatch(UpdateHelperEvents::COMMAND_GCU_EXECUTE, $event);

      foreach ($event->getTemplatePaths() as $path) {
        $this->getHelper('dcg_renderer')->addPath($path);
      }

      $this->assets = $event->getAssets();

      $patch_file_path = $this->configHandler->getPatchFile($this->vars['module'], static::getUpdateFunctionName($this->vars['module'], $this->vars['update-n']), TRUE);

      // Add the patchfile.
      $this->addFile($patch_file_path)
        ->content($patch_data);
    }
    else {
      $output->write('There are no configuration changes that should be exported for the update.', TRUE);
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
