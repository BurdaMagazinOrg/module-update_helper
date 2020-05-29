<?php

namespace Drupal\update_helper_checklist\Events;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\update_helper\Events\CommandExecuteEvent;
use Drupal\update_helper\Events\UpdateHelperEvents;
use Drupal\update_helper\Events\CommandInteractEvent;
use Drupal\update_helper_checklist\UpdateChecklist;
use Symfony\Component\Console\Question\Question;
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
   * Update checklist service.
   *
   * @var \Drupal\update_helper_checklist\UpdateChecklist
   */
  protected $updateChecklist;

  /**
   * Drupal\Core\Extension\ModuleHandler definition.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * CommandGcuSubscriber constructor.
   *
   * @param \Drupal\update_helper_checklist\UpdateChecklist $update_checklist
   *   Update checklist service.
   */
  public function __construct(UpdateChecklist $update_checklist, ModuleHandlerInterface $module_handler) {
    $this->updateChecklist = $update_checklist;
    $this->moduleHandler = $module_handler;

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      UpdateHelperEvents::COMMAND_GCU_INTERACT => [
        ['onInteract', 10],
      ],
      UpdateHelperEvents::COMMAND_GCU_EXECUTE => [
        ['onExecute', 10],
      ],
    ];
  }

  /**
   * Handle on interactive mode for getting command options.
   *
   * @param \Drupal\update_helper\Events\CommandInteractEvent $interact_event
   *   Event.
   */
  public function onInteract(CommandInteractEvent $interact_event) {


    $update_versions = $this->updateChecklist->getUpdateVersions($interact_event->getVars()['module']);
    // Set internal pointer to end, to get last update version.
    end($update_versions);
    $questions[static::$updateVersionName] = new Question('Please enter a update version for checklist collection', (empty($update_versions)) ? '8.x-1.0' : current($update_versions));


    $questions[static::$updateDescription] = new Question('Please enter a detailed update description that will be used for checklist', 'This configuration update will update site configuration to newly provided configuration');
    $questions[static::$successMessageName] = new Question('Please enter a detailed update description that will be used for checklist', 'Configuration is successfully updated.');
    $questions[static::$failureMessageName] = new Question('Please enter a message that will be displayed in checklist entry when the update has failed', 'Update of configuration has failed.');

    $interact_event->setQuestions($questions);


  }

  /**
   * Handles configuration update generation.
   *
   * @param \Drupal\update_helper\Events\CommandExecuteEvent $execute_event
   *   Event.
   */
  public function onExecute(CommandExecuteEvent $execute_event) {

    $module_path = $this->moduleHandler->getModule($execute_event->getVars()['module'])->getPath();
    $checklist_file = $module_path . DIRECTORY_SEPARATOR . UpdateChecklist::$updateChecklistFileName;
    $update_versions = $this->updateChecklist->getUpdateVersions($execute_event->getVars()['module']);
    end($update_versions);
    $last_update_version = current($update_versions);

    $parameters = [
      'file_exists' => file_exists($checklist_file),
      'update_version' => ($execute_event->getVars()[static::$updateVersionName] === $last_update_version) ? '' : $execute_event->getVars()[static::$updateVersionName],
    ];




  }

}
