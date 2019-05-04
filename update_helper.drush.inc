<?php

/**
 * @file
 * Update helper drush command.
 */

use Drupal\update_helper\Utility\CommandHelper;

/**
 * Implements hook_drush_command().
 */
function update_helper_drush_command() {

  $commands['update-helper-apply-update'] = [
    'description' => 'Apply config updates.',
    'aliases' => ['uhau'],
    'arguments' => [
      'module' => 'Module name.',
      'updateName' => 'Update name.',
    ],
    'options' => [
      'force' => FALSE,
    ],
    'examples' => [
      'drush uhau <module> <updateName>' => 'Apply the update <updateName> from <module>',
      'drush uhau --force <module> <updateName>' => 'Force apply the update <updateName> from <module>',
    ],
  ];

  return $commands;
}

/**
 * Drush command logic.
 *
 * The drush_[MODULE_NAME]_[COMMAND_NAME]().
 */
function drush_update_helper_apply_update($module = "", $update_hook = "") {
  $commandhelper = _update_helper_drush_command_helper();
  $force = drush_get_option('force', FALSE);
  $commandhelper->apply_update($module, $update_hook, $force);
}

/**
 * Returns an instance of the command helper.
 *
 * @return \Drupal\update_helper\Utility\CommandHelper
 *   An instance of the command helper class.
 */
function _update_helper_drush_command_helper() {
  $command_helper = new CommandHelper();
  $command_helper->setLogger(\Drupal::logger('update_helper'));
  return $command_helper;
}
