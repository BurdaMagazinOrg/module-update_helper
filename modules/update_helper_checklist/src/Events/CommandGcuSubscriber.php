<?php

namespace Drupal\update_helper_checklist\Events;

use Drupal\Console\Utils\TranslatorManager;
use Drupal\update_helper\Events\CommandConfigureEvent;
use Drupal\update_helper\Events\CommandExecuteEvent;
use Drupal\update_helper\Events\UpdateHelperEvents;
use Drupal\update_helper\Events\CommandInteractEvent;
use Drupal\update_helper_checklist\Generator\ConfigurationUpdateGenerator;
use Drupal\update_helper_checklist\UpdateChecklist;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for "generate:configuration:update" command.
 */
class CommandGcuSubscriber implements EventSubscriberInterface {

  /**
   * Key for update version option.
   *
   * @var string
   */
  protected static $updateVersionName = 'update-version';

  /**
   * Key for update description.
   *
   * @var string
   */
  protected static $updateDescription = 'update-description';

  /**
   * Key for success message command option.
   *
   * @var string
   */
  protected static $successMessageName = 'success-message';

  /**
   * Key for failure message command option.
   *
   * @var string
   */
  protected static $failureMessageName = 'failure-message';

  /**
   * Checklist entry generator for configuration update command.
   *
   * @var \Drupal\update_helper_checklist\Generator\ConfigurationUpdateGenerator
   */
  protected $generator;

  /**
   * Console translator manager service.
   *
   * @var \Drupal\Console\Utils\TranslatorManager
   */
  protected $translatorManager;

  /**
   * Update checklist service.
   *
   * @var \Drupal\update_helper_checklist\UpdateChecklist
   */
  protected $updateChecklist;

  /**
   * CommandGcuSubscriber constructor.
   *
   * @param \Drupal\update_helper_checklist\Generator\ConfigurationUpdateGenerator $generator
   *   Code generator service.
   * @param \Drupal\Console\Utils\TranslatorManager $translator_manager
   *   Translator manager service.
   * @param \Drupal\update_helper_checklist\UpdateChecklist $update_checklist
   *   Update checklist service.
   */
  public function __construct(ConfigurationUpdateGenerator $generator, TranslatorManager $translator_manager, UpdateChecklist $update_checklist) {
    $this->generator = $generator;
    $this->translatorManager = $translator_manager;
    $this->updateChecklist = $update_checklist;

    // Init required options for this subscriber to work.
    $translator_manager->addResourceTranslationsByExtension('update_helper_checklist', 'module');
    $this->generator->addSkeletonDir(__DIR__ . '/../../templates/console');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      UpdateHelperEvents::COMMAND_GCU_CONFIGURE => [
        ['onConfigure', 10],
      ],
      UpdateHelperEvents::COMMAND_GCU_INTERACT => [
        ['onInteract', 10],
      ],
      UpdateHelperEvents::COMMAND_GCU_EXECUTE => [
        ['onExecute', 10],
      ],
    ];
  }

  /**
   * Get options for "generate:configuration:update" relevant for checklist.
   *
   * @param \Drupal\update_helper\Events\CommandConfigureEvent $configure_event
   *   Command options event.
   */
  public function onConfigure(CommandConfigureEvent $configure_event) {
    $configure_event->addOption(
      static::$updateVersionName,
      NULL,
      InputOption::VALUE_OPTIONAL,
      $configure_event->getCommand()
        ->trans('commands.generate.configuration.update.checklist.options.update-version'),
      ''
    );

    $configure_event->addOption(
      static::$updateDescription,
      NULL,
      InputOption::VALUE_REQUIRED,
      $configure_event->getCommand()
        ->trans('commands.generate.configuration.update.checklist.options.update-description')
    );

    $configure_event->addOption(
      static::$successMessageName,
      NULL,
      InputOption::VALUE_REQUIRED,
      $configure_event->getCommand()
        ->trans('commands.generate.configuration.update.checklist.options.success-message')
    );

    $configure_event->addOption(
      static::$failureMessageName,
      NULL,
      InputOption::VALUE_REQUIRED,
      $configure_event->getCommand()
        ->trans('commands.generate.configuration.update.checklist.options.failure-message')
    );
  }

  /**
   * Handle on interactive mode for getting command options.
   *
   * @param \Drupal\update_helper\Events\CommandInteractEvent $interact_event
   *   Event.
   */
  public function onInteract(CommandInteractEvent $interact_event) {
    $command = $interact_event->getCommand();
    $input = $interact_event->getInput();

    /** @var \Drupal\Console\Core\Style\DrupalStyle $output */
    $output = $interact_event->getOutput();

    $update_version = $input->getOption(static::$updateVersionName);
    $update_description = $input->getOption(static::$updateDescription);
    $success_message = $input->getOption(static::$successMessageName);
    $failure_message = $input->getOption(static::$failureMessageName);

    // Get update version.
    if (!$update_version) {
      $update_versions = $this->updateChecklist->getUpdateVersions($input->getOption('module'));
      // Set internal pointer to end, to get last update version.
      end($update_versions);

      $update_version = $output->ask(
        $command->trans('commands.generate.configuration.update.checklist.questions.update-version'),
        (empty($update_versions)) ? '8.x-1.0' : current($update_versions)
      );
      $input->setOption(static::$updateVersionName, $update_version);
    }

    // Get update description for checklist.
    if (!$update_description) {
      $update_description = $output->ask(
        $command->trans('commands.generate.configuration.update.checklist.questions.update-description'),
        $command->trans('commands.generate.configuration.update.checklist.defaults.update-description')
      );
      $input->setOption(static::$updateDescription, $update_description);
    }

    // Get success message for checklist.
    if (!$success_message) {
      $success_message = $output->ask(
        $command->trans('commands.generate.configuration.update.checklist.questions.success-message'),
        $command->trans('commands.generate.configuration.update.checklist.defaults.success-message')
      );
      $input->setOption(static::$successMessageName, $success_message);
    }

    // Get failure message for checklist.
    if (!$failure_message) {
      $failure_message = $output->ask(
        $command->trans('commands.generate.configuration.update.checklist.questions.failure-message'),
        $command->trans('commands.generate.configuration.update.checklist.defaults.failure-message')
      );
      $input->setOption(static::$failureMessageName, $failure_message);
    }
  }

  /**
   * Handles configuration update generation.
   *
   * @param \Drupal\update_helper\Events\CommandExecuteEvent $execute_event
   *   Event.
   */
  public function onExecute(CommandExecuteEvent $execute_event) {
    // If command that triggered this event wasn't successful, then nothing
    // should be created.
    if (!$execute_event->getSuccessful()) {
      return;
    }

    // Get options provided by command as options or in interactive mode.
    $options = $execute_event->getOptions();

    $this->generator->generate(
      $execute_event->getModule(),
      $execute_event->getUpdateNumber(),
      $options[static::$updateVersionName],
      $options['description'],
      $options[static::$updateDescription],
      $options[static::$successMessageName],
      $options[static::$failureMessageName]
    );
  }

}
