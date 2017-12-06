<?php

namespace Drupal\update_helper_checklist\Events;

use Drupal\Console\Utils\TranslatorManager;
use Drupal\update_helper\Events\CommandConfigureEvent;
use Drupal\update_helper\Events\CommandExecuteEvent;
use Drupal\update_helper\Events\UpdateHelperEvents;
use Drupal\update_helper\Events\CommandInteractEvent;
use Drupal\update_helper_checklist\Generator\ConfigurationUpdateGenerator;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class WizardOptionsSubscriber.
 *
 * TODO: Add support for version option, where entry in checklist will be added.
 */
class CommandGcuSubscriber implements EventSubscriberInterface {

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
   * CommandGcuSubscriber constructor.
   *
   * @param \Drupal\update_helper_checklist\Generator\ConfigurationUpdateGenerator $generator
   *   Code generator service.
   * @param \Drupal\Console\Utils\TranslatorManager $translator_manager
   *   Translator manager service.
   */
  public function __construct(ConfigurationUpdateGenerator $generator, TranslatorManager $translator_manager) {
    $this->generator = $generator;
    $this->translatorManager = $translator_manager;

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
   * @param \Drupal\update_helper\Events\CommandConfigureEvent $optionsEvent
   *   Command options event.
   */
  public function onConfigure(CommandConfigureEvent $optionsEvent) {
    $optionsEvent->addOption(
      static::$successMessageName,
      NULL,
      InputOption::VALUE_REQUIRED,
      $optionsEvent->getTarget()
        ->trans('commands.generate.configuration.update.checklist.options.success-message')
    );

    $optionsEvent->addOption(
      static::$failureMessageName,
      NULL,
      InputOption::VALUE_REQUIRED,
      $optionsEvent->getTarget()
        ->trans('commands.generate.configuration.update.checklist.options.failure-message')
    );
  }

  /**
   * Handle on wizard options creation.
   *
   * @param \Drupal\update_helper\Events\CommandInteractEvent $wizardEvent
   *   Event.
   */
  public function onInteract(CommandInteractEvent $wizardEvent) {
    $targetCommand = $wizardEvent->getTarget();
    $input = $wizardEvent->getInput();

    /** @var \Drupal\Console\Core\Style\DrupalStyle $output */
    $output = $wizardEvent->getOutput();

    $success_message = $input->getOption(static::$successMessageName);
    $failure_message = $input->getOption(static::$failureMessageName);

    // Get success message for checklist.
    if (!$success_message) {
      $success_message = $output->ask(
        $targetCommand->trans('commands.generate.configuration.update.checklist.questions.success-message'),
        $targetCommand->trans('commands.generate.configuration.update.checklist.defaults.success-message')
      );
      $input->setOption(static::$successMessageName, $success_message);
    }

    // Get failure message for checklist.
    if (!$failure_message) {
      $failure_message = $output->ask(
        $targetCommand->trans('commands.generate.configuration.update.checklist.questions.failure-message'),
        $targetCommand->trans('commands.generate.configuration.update.checklist.defaults.failure-message')
      );
      $input->setOption(static::$failureMessageName, $failure_message);
    }
  }

  /**
   * Handles configuration update generation.
   *
   * @param \Drupal\update_helper\Events\CommandExecuteEvent $event
   *   Event.
   */
  public function onExecute(CommandExecuteEvent $event) {
    // If triggerer command wasn't successful, then nothing should be created.
    if (!$event->getSuccessful()) {
      return;
    }

    $options = $event->getOptions();

    // TODO: Translate!!!
    $this->generator->generate(
      $event->getModule(),
      $event->getUpdateNumber(),
      $options['description'] ?: 'Title',
      $options[static::$successMessageName],
      $options[static::$failureMessageName]
    );
  }

}
