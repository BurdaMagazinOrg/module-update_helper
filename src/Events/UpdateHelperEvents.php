<?php

namespace Drupal\update_helper\Events;

/**
 * Class UpdateHelperEvents.
 *
 * @package Drupal\update_helper\Events
 */
final class UpdateHelperEvents {

  const COMMAND_GCU_CONFIGURE = 'update_helper.command.gcu.configure';

  const COMMAND_GCU_INTERACT = 'update_helper.command.gcu.interact';

  const COMMAND_GCU_EXECUTE = 'update_helper.command.gcu.execute';

  const CONFIGURATION_UPDATE = 'update_helper.configuration.update';

}
