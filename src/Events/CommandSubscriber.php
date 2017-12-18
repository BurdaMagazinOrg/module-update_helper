<?php

namespace Drupal\update_helper\Events;

use Drupal\update_helper\Generator\ConfigurationUpdateHookGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for "generate:configuration:update" command.
 */
class CommandSubscriber implements EventSubscriberInterface {

  /**
   * Update hook generator for configuration update command.
   *
   * @var \Drupal\update_helper\Generator\ConfigurationUpdateHookGenerator
   */
  protected $generator;

  /**
   * Command subscriber class.
   *
   * @param \Drupal\update_helper\Generator\ConfigurationUpdateHookGenerator $generator
   *   Update hook generator service.
   */
  public function __construct(ConfigurationUpdateHookGenerator $generator) {
    $this->generator = $generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      UpdateHelperEvents::COMMAND_GCU_EXECUTE => [
        ['onExecute', 10],
      ],
    ];
  }

  /**
   * Handles execute for configuration update generation to create update hook.
   *
   * @param \Drupal\update_helper\Events\CommandExecuteEvent $execute_event
   *   Command execute event.
   */
  public function onExecute(CommandExecuteEvent $execute_event) {
    // If command that triggered this event wasn't successful, then nothing
    // should be created.
    if (!$execute_event->getSuccessful()) {
      return;
    }

    // Get options provided by command as options or in interactive mode.
    $options = $execute_event->getOptions();

    // Generate update hook entry.
    $this->generator->generate(
      $execute_event->getModule(),
      $execute_event->getUpdateNumber(),
      $options['description']
    );
  }

}
