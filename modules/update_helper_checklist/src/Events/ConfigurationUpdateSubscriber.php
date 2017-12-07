<?php

namespace Drupal\update_helper_checklist\Events;

use Drupal\update_helper\Events\ConfigurationUpdateEvent;
use Drupal\update_helper\Events\UpdateHelperEvents;
use Drupal\update_helper_checklist\UpdateChecklist;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Configuration update subscriber.
 *
 * @package Drupal\update_helper_checklist\Events
 */
class ConfigurationUpdateSubscriber implements EventSubscriberInterface {

  protected $updateChecklist;

  /**
   * ConfigurationUpdateSubscriber constructor.
   *
   * @param \Drupal\update_helper_checklist\UpdateChecklist $updateChecklist
   *   Update checklist service.
   */
  public function __construct(UpdateChecklist $updateChecklist) {
    $this->updateChecklist = $updateChecklist;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      UpdateHelperEvents::CONFIGURATION_UPDATE => [
        ['onConfigurationUpdate', 10],
      ],
    ];
  }

  /**
   * Handles on configuration update event.
   *
   * @param \Drupal\update_helper\Events\ConfigurationUpdateEvent $event
   *   Configuration update event.
   */
  public function onConfigurationUpdate(ConfigurationUpdateEvent $event) {
    if ($event->isSuccessful()) {
      $this->updateChecklist->markUpdatesSuccessful([$event->getModule() => [$event->getUpdateName()]]);
    }
    else {
      $this->updateChecklist->markUpdatesFailed([$event->getModule() => [$event->getUpdateName()]]);
    }
  }

}
