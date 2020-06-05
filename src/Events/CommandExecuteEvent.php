<?php

namespace Drupal\update_helper\Events;

use DrupalCodeGenerator\Asset;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event for command execute.
 *
 * @package Drupal\update_helper\Events
 */
class CommandExecuteEvent extends Event {

  /**
   * The collected variables.
   *
   * @var array
   */
  protected $vars;

  /**
   * Assets that should be generated.
   *
   * @var array
   */
  protected $assets = [];

  protected $templatePaths = [];

  /**
   * Command execute event constructor.
   *
   * @param array $vars
   *   The collected vars.
   */
  public function __construct(array $vars) {
    $this->vars = $vars;
  }

  /**
   * Get the collected vars.
   *
   * @return array
   *   All the collected vars.
   */
  public function getVars() {
    return $this->vars;
  }

  /**
   * Get the assets that should be generated.
   *
   * @return array
   *   Assets that should be generated.
   */
  public function getAssets() {
    return $this->assets;
  }

  /**
   * Add an asset.
   *
   * @param \DrupalCodeGenerator\Asset $asset
   *   The asset to add to the array.
   *
   * @return $this
   */
  public function addAsset(Asset $asset) {
    $this->assets[] = $asset;
    return $this;
  }

  public function addTemplatePath($template_path) {
    $this->templatePaths[] = $template_path;
    return $this;
  }

  public function getTemplatePaths() {
    return $this->templatePaths;
  }

}
