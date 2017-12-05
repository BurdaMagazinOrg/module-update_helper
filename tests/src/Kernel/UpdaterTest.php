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
    /** @var \Drupal\config_update\ConfigRevertInterface $configReverter */
    $configReverter = \Drupal::service('config_update.config_update');
    $configReverter->import('field_storage_config', 'node.body');

    /** @var \Drupal\Core\Config\ConfigFactory $configFactory */
    $configFactory = \Drupal::service('config.factory');
    $config = $configFactory->getEditable('field.storage.node.body');

    $expectedConfigData = $config->get();

    $configData = $config->get();
    $configData['status'] = FALSE;
    $configData['type'] = 'text';
    unset($configData['cardinality']);
    $configData['settings'] = ['max_length' => 123];
    $configData['lost_config'] = 'text';

    $config->setData($configData)->save(TRUE);

    /** @var \Drupal\update_helper\Updater $updateHelper */
    $updateHelper = \Drupal::service('update_helper.updater');

    /** @var \Drupal\update_helper\ConfigHandler $configHandler */
    $configHandler = \Drupal::service('update_helper.config_handler');
    $patch_file_path = $configHandler->getPatchFile('update_helper', 'test_updater', TRUE);

    /** @var \Drupal\Core\Serialization\Yaml $ymlSerializer */
    $ymlSerializer = \Drupal::service('serialization.yaml');
    file_put_contents($patch_file_path, $ymlSerializer->encode($this->getUpdateDefinition()));

    $updateHelper->executeUpdate('update_helper', 'test_updater');

    $this->assertEquals($expectedConfigData, $configFactory->get('field.storage.node.body')->get());
  }

}
