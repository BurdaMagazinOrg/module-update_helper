<?php

namespace Drupal\Tests\update_helper\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * @covers \Drupal\update_helper\Updater
 *
 * @group update_helper
 *
 * @package Drupal\Tests\update_helper\Kernel
 */
class UpdaterTest extends KernelTestBase {

  /**
   * Config directory path.
   *
   * @var string
   */
  protected $configDir = '';

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler = NULL;

  /**
   * Following configurations will be manipulated during testing.
   *
   * @var string[]
   */
  protected static $configSchemaCheckerExclusions = [
    'field.storage.node.body',
  ];

  protected static $modules = [
    'config_update',
    'update_helper',
    'system',
    'user',
    'text',
    'field',
    'node',
    'tour',
  ];

  /**
   * Get update definition that should be executed.
   *
   * @return array
   *   Update definition array.
   */
  protected function getUpdateDefinition() {
    return [
      '__global_actions' => [
        'install_modules' => [
          'help',
        ],
        'import_configs' => [
          'tour.tour.tour-update-helper-test',
        ],
      ],
      'field.storage.node.body' => [
        'expected_config' => [
          'lost_config' => 'text',
          'settings' => [
            'max_length' => 123,
          ],
          'status' => FALSE,
          'type' => 'text',
        ],
        'update_actions' => [
          'add' => [
            'cardinality' => 1,
          ],
          'change' => [
            'settings' => [],
            'status' => TRUE,
            'type' => 'text_with_summary',
          ],
          'delete' => [
            'lost_config' => 'text',
            'settings' => [
              'max_length' => '123',
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->moduleHandler = \Drupal::moduleHandler();
    $this->configDir = $this->moduleHandler->getModule('update_helper')->getPath() . '/config';

    mkdir($this->configDir . '/install', 0755, TRUE);

    // Prepare config file for testing of configuration import.
    $tour_config = [
      'id' => 'tour-update-helper-test',
      'module' => 'update_helper',
      'label' => 'Tour test Update Helper config import',
      'langcode' => 'en',
      'routes' => [
        ['route_name' => 'update_helper.1'],
      ],
      'tips' => [
        'tour-update-helper-test-1' => [
          'id' => 'update-helper-test-1',
          'plugin' => 'text',
          'label' => 'Update Helper',
          'body' => 'Update helper test tour.',
          'weight' => 1,
        ],
      ],
    ];

    /** @var \Drupal\Core\Serialization\Yaml $yml_serializer */
    $yml_serializer = \Drupal::service('serialization.yaml');
    file_put_contents($this->configDir . '/install/tour.tour.tour-update-helper-test.yml', $yml_serializer->encode($tour_config));
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    $config_dir = $this->moduleHandler->getModule('update_helper')->getPath() . '/config';

    // Remove import file.
    unlink($config_dir . '/install/tour.tour.tour-update-helper-test.yml');
    rmdir($config_dir . '/install');

    // Remove configuration update definition.
    unlink($config_dir . '/update/test_updater.yml');
    rmdir($config_dir . '/update');

    rmdir($config_dir);

    parent::tearDown();
  }

  /**
   * @covers \Drupal\update_helper\Updater::executeUpdate
   */
  public function testExecuteUpdate() {
    /** @var \Drupal\config_update\ConfigRevertInterface $config_reverter */
    $config_reverter = \Drupal::service('config_update.config_update');
    $config_reverter->import('field_storage_config', 'node.body');

    /** @var \Drupal\Core\Config\ConfigFactory $config_factory */
    $config_factory = \Drupal::service('config.factory');
    $config = $config_factory->getEditable('field.storage.node.body');

    $expected_config_data = $config->get();

    $config_data = $config->get();
    $config_data['status'] = FALSE;
    $config_data['type'] = 'text';
    unset($config_data['cardinality']);
    $config_data['settings'] = ['max_length' => 123];
    $config_data['lost_config'] = 'text';

    $config->setData($config_data)->save(TRUE);

    /** @var \Drupal\update_helper\Updater $update_helper */
    $update_helper = \Drupal::service('update_helper.updater');

    /** @var \Drupal\Core\Serialization\Yaml $yml_serializer */
    $yml_serializer = \Drupal::service('serialization.yaml');

    /** @var \Drupal\update_helper\ConfigHandler $config_handler */
    $config_handler = \Drupal::service('update_helper.config_handler');

    $patch_file_path = $config_handler->getPatchFile('update_helper', 'test_updater', TRUE);
    file_put_contents($patch_file_path, $yml_serializer->encode($this->getUpdateDefinition()));

    $this->assertFalse($this->moduleHandler->moduleExists('help'), 'Module "help" should not be installed.');

    // Create some configuration file for tour, so that it can be imported.
    $this->assertEquals(NULL, $config_factory->get('tour.tour.tour-update-helper-test')->get('id'), 'Tour configuration should not exist.');

    // Ensure that configuration had new values.
    $this->assertEquals('text', $config_factory->get('field.storage.node.body')->get('lost_config'));

    $update_helper->executeUpdate('update_helper', 'test_updater');

    $this->assertEquals($expected_config_data, $config_factory->get('field.storage.node.body')->get());
    $this->assertTrue($this->moduleHandler->moduleExists('help'), 'Module "help" should be installed.');
    $this->assertEquals('tour-update-helper-test', $this->container->get('config.factory')->get('tour.tour.tour-update-helper-test')->get('id'), 'Tour configuration should exist.');
  }

}
