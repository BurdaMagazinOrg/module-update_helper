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
          'tour',
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

    /** @var \Drupal\update_helper\ConfigHandler $config_handler */
    $config_handler = \Drupal::service('update_helper.config_handler');
    $patch_file_path = $config_handler->getPatchFile('update_helper', 'test_updater', TRUE);

    /** @var \Drupal\Core\Serialization\Yaml $yml_serializer */
    $yml_serializer = \Drupal::service('serialization.yaml');
    file_put_contents($patch_file_path, $yml_serializer->encode($this->getUpdateDefinition()));

    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $module_handler */
    $module_handler = \Drupal::moduleHandler();
    $this->assertFalse($module_handler->moduleExists('tour'), 'Module "tour" should not be installed.');

    $update_helper->executeUpdate('update_helper', 'test_updater');

    $this->assertEquals($expected_config_data, $config_factory->get('field.storage.node.body')->get());
    $this->assertTrue($module_handler->moduleExists('tour'), 'Module "tour" should be installed.');
  }

}
