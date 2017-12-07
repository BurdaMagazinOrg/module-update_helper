<?php

namespace Drupal\update_helper;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Helper service for logging in update hooks provided by update helper.
 *
 * It provides output of logs to HTML, when update is executed over update.php.
 * And it also provides output of logs for Drush command, when update is
 * executed over drush command.
 *
 * @package Drupal\update_helper
 */
class UpdateLogger extends AbstractLogger {

  /**
   * Container for logs.
   *
   * @var array
   */
  protected $logs = [];

  /**
   * Mapping from Psr to Drush log level.
   *
   * @var array
   */
  protected static $psrDrushLogLevels = [
    LogLevel::INFO => 'ok',
  ];

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []) {
    $this->logs[] = [$level, $message, $context];
  }

  /**
   * Clear logs and returns currenlty collected logs.
   *
   * @return array
   *   Returns collected logs, since last clear.
   */
  protected function cleanLogs() {
    $logs = $this->logs;
    $this->logs = [];

    return $logs;
  }

  /**
   * Output logs in format suitable for HTML and clear logs too.
   *
   * @return string
   *   Returns HTML.
   */
  protected function outputHtml() {
    $full_log = '';

    $current_logs = $this->cleanLogs();
    foreach ($current_logs as $log_entry) {
      $full_log .= $log_entry[1] . '<br /><br />';
    }

    return $full_log;
  }

  /**
   * Output logs in format suitable for drush command and clear logs too.
   *
   * @throws \RuntimeException
   *   When method is not executed in drush environment.
   */
  protected function outputDrush() {
    // Check for "drush_log" should be done by caller.
    if (!function_exists('drush_log')) {
      throw new \RuntimeException('Required global method "drush_log" is not available.');
    }

    $current_logs = $this->cleanLogs();
    foreach ($current_logs as $log_entry) {
      if (isset(static::$psrDrushLogLevels[$log_entry[0]])) {
        $drush_log_level = static::$psrDrushLogLevels[$log_entry[0]];
      }
      else {
        $drush_log_level = $log_entry[0];
      }

      drush_log($log_entry[1], $drush_log_level);
    }
  }

  /**
   * Output log result, depending on channel used and clean log.
   *
   * @return string
   *   Returns HTML string in case of non drush execution.
   */
  public function output() {
    if (function_exists('drush_log') && PHP_SAPI === 'cli') {
      $this->outputDrush();

      return '';
    }

    return $this->outputHtml();
  }

}
