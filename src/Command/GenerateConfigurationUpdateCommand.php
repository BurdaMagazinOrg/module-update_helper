<?php

namespace Drupal\update_helper\Command;

use Drupal\Console\Command\Shared\ConfirmationTrait;
use Drupal\Console\Command\Shared\ModuleTrait;
use Drupal\Console\Extension\Manager;
use Drupal\Console\Utils\Site;
use Drupal\update_helper\Events\CommandConfigureEvent;
use Drupal\update_helper\Events\CommandExecuteEvent;
use Drupal\update_helper\Events\CommandInteractEvent;
use Drupal\update_helper\Events\UpdateHelperEvents;
use Drupal\update_helper\Generator\ConfigurationUpdateGenerator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\Command;
use Drupal\Console\Core\Style\DrupalStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Generate configuration update command class.
 *
 * TODO: Add handling of event errors.
 *
 * @Drupal\Console\Annotations\DrupalCommand (
 *     extension="update_helper",
 *     extensionType="module"
 * )
 */
class GenerateConfigurationUpdateCommand extends Command {

  use ModuleTrait;
  use ConfirmationTrait;

  /**
   * Extension manager.
   *
   * Extension manager is needed for ModuleTrait.
   *
   * @var \Drupal\Console\Extension\Manager
   */
  protected $extensionManager;

  /**
   * Update generator for configuration update hook.
   *
   * @var \Drupal\update_helper\Generator\ConfigurationUpdateGenerator
   */
  protected $generator;

  /**
   * Site.
   *
   * @var \Drupal\Console\Utils\Site
   */
  protected $site;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Generate configuration update command constructor.
   *
   * @param \Drupal\Console\Extension\Manager $extension_manager
   *   Extension manager.
   * @param \Drupal\update_helper\Generator\ConfigurationUpdateGenerator $generator
   *   Configuration update generator.
   * @param \Drupal\Console\Utils\Site $site
   *   Site.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher.
   */
  public function __construct(
    Manager $extension_manager,
    ConfigurationUpdateGenerator $generator,
    Site $site,
    EventDispatcherInterface $event_dispatcher
  ) {
    $this->extensionManager = $extension_manager;
    $this->generator = $generator;
    $this->site = $site;
    $this->eventDispatcher = $event_dispatcher;

    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('generate:configuration:update')
      ->setDescription($this->trans('commands.generate.configuration.update.description'))
      ->addOption(
        'module',
        NULL,
        InputOption::VALUE_REQUIRED,
        $this->trans('commands.generate.configuration.update.options.module')
      )
      ->addOption(
        'update-n',
        NULL,
        InputOption::VALUE_REQUIRED,
        $this->trans('commands.generate.configuration.update.options.update-n')
      )
      ->addOption(
        'description',
        NULL,
        InputOption::VALUE_REQUIRED,
        $this->trans('commands.generate.configuration.update.options.description')
      )
      ->addOption(
        'include-modules',
        NULL,
        InputOption::VALUE_OPTIONAL,
        $this->trans('commands.generate.configuration.update.options.include-modules'),
        ''
      )
      ->setAliases(['gcu']);

    // Get additional options provided by other modules.
    $event = new CommandConfigureEvent($this);
    $this->eventDispatcher->dispatch(UpdateHelperEvents::COMMAND_GCU_CONFIGURE, $event);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new DrupalStyle($input, $output);

    // @see use Drupal\Console\Command\Shared\ConfirmationTrait::confirmGeneration
    if (!$this->confirmGeneration($io)) {
      return 1;
    }

    $module = $input->getOption('module');
    $include_modules = $input->getOption('include-modules');

    $update_number = $input->getOption('update-n');
    $last_update_number = $this->getLastUpdate($module);
    if ($update_number <= $last_update_number) {
      throw new \InvalidArgumentException(
        sprintf(
          $this->trans('commands.generate.configuration.update.messages.wrong-update-n'),
          $update_number
        )
      );
    }

    // Execute configuration update generation.
    $successful = $this->generator->generate($module, $update_number, $include_modules);

    // Get additional options provided by other modules.
    $event = new CommandExecuteEvent($this, $module, $update_number, $input->getOptions(), $successful);
    $this->eventDispatcher->dispatch(UpdateHelperEvents::COMMAND_GCU_EXECUTE, $event);

    if ($successful) {
      $io->info($this->trans('commands.generate.configuration.update.messages.success'));
    }
    else {
      $io->info($this->trans('commands.generate.configuration.update.messages.no-update'));
    }

    return 0;
  }

  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output) {
    $io = new DrupalStyle($input, $output);

    $this->site->loadLegacyFile('/core/includes/update.inc');
    $this->site->loadLegacyFile('/core/includes/schema.inc');

    $module = $input->getOption('module');
    $update_number = $input->getOption('update-n');
    $description = $input->getOption('description');

    // TODO: Get information about optional arguments from command definition!
    // If at least one required value is requested by interactive mode, then
    // request optional values too.
    $use_interact_for_optional = empty($module) || empty($update_number) || empty($description);

    // Get module name where update will be saved.
    if (!$module) {
      // TODO: Get only modules that have some configuration changes.
      // @see Drupal\Console\Command\Shared\ModuleTrait::moduleQuestion
      $module = $this->moduleQuestion($io);
      $input->setOption('module', $module);
    }

    // Get Update N number.
    $last_update_number = $this->getLastUpdate($module);
    $next_update_number = $last_update_number ? ($last_update_number + 1) : 8001;
    if (!$update_number) {
      $update_number = $io->ask(
        $this->trans('commands.generate.configuration.update.questions.update-n'),
        $next_update_number,
        function ($update_number) use ($last_update_number) {
          if (!is_numeric($update_number)) {
            throw new \InvalidArgumentException(
              sprintf(
                $this->trans('commands.generate.configuration.update.messages.wrong-update-n'),
                $update_number
              )
            );
          }
          else {
            if ($update_number <= $last_update_number) {
              throw new \InvalidArgumentException(
                sprintf(
                  $this->trans('commands.generate.configuration.update.messages.wrong-update-n'),
                  $update_number
                )
              );
            }
            return $update_number;
          }
        }
      );

      $input->setOption('update-n', $update_number);
    }

    // Get description from interactive mode.
    if (!$description) {
      $description = $io->ask(
        $this->trans('commands.generate.configuration.update.questions.description'),
        $this->trans('commands.generate.configuration.update.defaults.description')
      );
      $input->setOption('description', $description);
    }

    // Get list of modules that are included in update.
    $include_modules = $input->getOption('include-modules');
    if (!$include_modules && $use_interact_for_optional) {
      $include_modules = $io->ask(
        $this->trans('commands.generate.configuration.update.questions.include-modules'),
        ' '
      );
      $input->setOption('include-modules', trim($include_modules));
    }

    // Get additional options provided by other modules.
    $event = new CommandInteractEvent($this, $input, $io);
    $this->eventDispatcher->dispatch(UpdateHelperEvents::COMMAND_GCU_INTERACT, $event);
  }

  /**
   * Get last update number.
   *
   * @param string $module
   *   Module name where update hook will placed.
   *
   * @return array|bool|mixed
   *   Returns next update hook number.
   */
  protected function getLastUpdate($module) {
    $this->site->loadLegacyFile('/core/includes/schema.inc');

    return drupal_get_installed_schema_version($module);
  }

}
