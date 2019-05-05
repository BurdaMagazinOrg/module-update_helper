<?php

namespace Drupal\update_helper\Utility;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class CommandHelper implements LoggerAwareInterface {

  use LoggerAwareTrait;

  public function __construct() {

  }

  /**
   * applying an (optional) update hook (function) from module install file
   *
   * @param string $module - drupal module name
   * @param string $update_hook - name of update_hook to apply
   * @param bool $force - force the update
   */
  public function apply_update($module = '', $update_hook = '', $force = FALSE) {
    if (!$update_hook || !$module) {
      $this->logger->error(dt('Please provide a module name and an update hook. Example: drush uhau <module> <update_hook>'));
      return;
    }

    $updateHelper = \Drupal::service('update_helper.updater');
    $updateHelper->executeUpdate($module, $update_hook, $force);
    return $updateHelper->logger()->output();
  }

}