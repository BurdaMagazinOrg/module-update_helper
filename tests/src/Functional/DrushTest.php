<?php

namespace Drupal\Tests\update_helper\Functional;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\update_helper\Kernel\ConfigHandlerTest;
use Drush\TestTraits\DrushTestTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Automated tests for Drush commands.
 *
 * Note this has to be a functional test for \Drush\TestTraits\DrushTestTrait to
 * work.
 *
 * @group update_helper
 *
 * @covers \Drupal\update_helper\ConfigHandler
 */
class DrushTest extends BrowserTestBase {
  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $configSchemaCheckerExclusions = [
    'field.storage.node.body',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'update_helper',
    'node',
    'test_node_config',
  ];

  /**
   * {@inheritdoc}
   */
  protected function prepareEnvironment() {
    parent::prepareEnvironment();
    mkdir($this->siteDirectory . '/modules/test_node_config', 0775, TRUE);
    $info = [
      'name' => 'Node config test module',
      'type' => 'module',
      'core_version_requirement' => '*',
      'package' => 'Testing',
    ];
    file_put_contents($this->siteDirectory . '/modules/test_node_config/test_node_config.info.yml', Yaml::encode($info));
  }

  /**
   * Tests `drush generate configuration-update`.
   */
  public function testGeneratePatchFileFromActiveConfigUsingDrush() {

    // Copy the node module so we can modify config for testing.
    $file_system = new Filesystem();
    $file_system->mirror('core/modules/node/config/install', $this->siteDirectory . '/modules/test_node_config/config/install');

    /** @var \Drupal\Core\Config\ConfigFactory $configFactory */
    $configFactory = \Drupal::service('config.factory');
    $config = $configFactory->getEditable('field.storage.node.body');
    $configData = $config->get();
    $configData['status'] = FALSE;
    $configData['type'] = 'text';
    unset($configData['cardinality']);
    $configData['settings'] = ['max_length' => 123];
    $configData['lost_config'] = 'text';

    $config->setData($configData)->save(TRUE);
    $optionsExample['answers'] = json_encode([
      'module' => 'test_node_config',
      'update-n' => 9001,
      'description' => 'Some description',
      'include-modules' => ['test_node_config'],
      'from-active' => TRUE,
    ]);
    $optionsExample['yes'] = NULL;

    $install_file = $this->siteDirectory . '/modules/test_node_config/test_node_config.install';
    $update_file = $this->siteDirectory . '/modules/test_node_config/config/update/test_node_config_update_9001.yml';
    $this->assertFileNotExists($install_file);
    $this->assertFileNotExists($update_file);

    $this->drush('generate', ['configuration-update'], $optionsExample, NULL, NULL, 0, NULL, ['SHELL_INTERACTIVE' => 1]);

    $this->assertFileExists($install_file);
    $this->assertFileExists($update_file);
    $this->assertEquals(ConfigHandlerTest::getUpdateDefinition(), file_get_contents($update_file));
  }

}
