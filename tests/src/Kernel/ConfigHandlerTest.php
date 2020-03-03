<?php

namespace Drupal\Tests\update_helper\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\update_helper\ConfigHandler;

/**
 * Automated tests for ConfigName class.
 *
 * @group update_helper
 *
 * @covers \Drupal\update_helper\ConfigHandler
 */
class ConfigHandlerTest extends KernelTestBase {

  /**
   * An array of config object names that are excluded from schema checking.
   *
   * @var string[]
   */
  protected static $configSchemaCheckerExclusions = [
    'field.storage.node.body',
  ];

  /**
   * Modules to enable for test.
   *
   * @var array
   */
  protected static $modules = [
    'config_update',
    'update_helper',
    'user',
    'text',
    'field',
    'node',
  ];

  /**
   * Returns update defintion data.
   *
   * @return string
   *   Update definition Yaml string.
   */
  protected function getUpdateDefinition() {
    return 'field.storage.node.body:' . PHP_EOL .
      '  expected_config:' . PHP_EOL .
      '    lost_config: text' . PHP_EOL .
      '    settings:' . PHP_EOL .
      '      max_length: 123' . PHP_EOL .
      '    status: false' . PHP_EOL .
      '    type: text' . PHP_EOL .
      '  update_actions:' . PHP_EOL .
      '    delete:' . PHP_EOL .
      '      lost_config: text' . PHP_EOL .
      '      settings:' . PHP_EOL .
      '        max_length: 123' . PHP_EOL .
      '    add:' . PHP_EOL .
      '      cardinality: 1' . PHP_EOL .
      '    change:' . PHP_EOL .
      '      settings: {  }' . PHP_EOL .
      '      status: true' . PHP_EOL .
      '      type: text_with_summary' . PHP_EOL;
  }

  /**
   * Backup of configuration file that is modified during testing.
   *
   * @var string
   */
  protected $configFileBackup;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->configFileBackup = tempnam(sys_get_temp_dir(), 'update_helper_test_');

    /** @var \Drupal\Core\Config\FileStorage $extensionStorage */
    $extensionStorage = \Drupal::service('config_update.extension_storage');
    $configFilePath = $extensionStorage->getFilePath('field.storage.node.body');

    $this->assertEqual(TRUE, copy($configFilePath, $this->configFileBackup));
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    $moduleHandler = \Drupal::service('module_handler');
    $dirName = $moduleHandler->getModule('node')->getPath() . '/config/update';
    $fileName = 'update_helper__node_test.yml';

    if (is_file($dirName . '/' . $fileName)) {
      unlink($dirName . '/' . $fileName);
    }

    if (is_dir($dirName)) {
      rmdir($dirName);
    }

    /** @var \Drupal\Core\Config\FileStorage $extensionStorage */
    $extensionStorage = \Drupal::service('config_update.extension_storage');
    $configFilePath = $extensionStorage->getFilePath('field.storage.node.body');

    $this->assertEqual(TRUE, copy($this->configFileBackup, $configFilePath));
    unlink($this->configFileBackup);

    parent::tearDown();
  }

  /**
   * @covers \Drupal\update_helper\ConfigHandler::generatePatchFile
   */
  public function testGeneratePatchFileFromActiveConfig() {
    /** @var \Drupal\update_helper\ConfigHandler $configHandler */
    $configHandler = \Drupal::service('update_helper.config_handler');

    /** @var \Drupal\config_update\ConfigRevertInterface $configReverter */
    $configReverter = \Drupal::service('config_update.config_update');
    $configReverter->import('field_storage_config', 'node.body');

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

    // Generate patch after configuration change.
    $data = $configHandler->generatePatchFile(['node'], TRUE);

    $this->assertEquals($this->getUpdateDefinition(), $data);

    // Check that configuration file is not changed.
    /** @var \Drupal\Core\Config\FileStorage $extensionStorage */
    $extensionStorage = \Drupal::service('config_update.extension_storage');
    $this->assertEqual(sha1_file($this->configFileBackup), sha1_file($extensionStorage->getFilePath('field.storage.node.body')));
  }

  /**
   * @covers \Drupal\update_helper\ConfigHandler::generatePatchFile
   */
  public function testGeneratePatchFileWithConfigExport() {
    /** @var \Drupal\update_helper\ConfigHandler $configHandler */
    $configHandler = \Drupal::service('update_helper.config_handler');

    /** @var \Drupal\Component\Serialization\SerializationInterface $yamlSerializer */
    $yamlSerializer = \Drupal::service('serialization.yaml');

    /** @var \Drupal\Core\Config\FileStorage $extensionStorage */
    $extensionStorage = \Drupal::service('config_update.extension_storage');
    $configFilePath = $extensionStorage->getFilePath('field.storage.node.body');

    /** @var \Drupal\config_update\ConfigRevertInterface $configReverter */
    $configReverter = \Drupal::service('config_update.config_update');
    $configReverter->import('field_storage_config', 'node.body');

    /** @var \Drupal\Core\Config\ConfigFactory $configFactory */
    $configFactory = \Drupal::service('config.factory');
    $config = $configFactory->getEditable('field.storage.node.body');
    $configData = $config->get();

    $configData['type'] = 'text';
    $configData['settings'] = ['max_length' => 321];
    $config->setData($configData)->save(TRUE);

    // Check file configuration before export.
    $fileData = $yamlSerializer->decode(file_get_contents($configFilePath));
    $this->assertEqual('text_with_summary', $fileData['type']);
    $this->assertEqual([], $fileData['settings']);

    // Generate patch and export config after configuration change.
    $data = $configHandler->generatePatchFile(['node'], FALSE);

    $this->assertEqual(
      'field.storage.node.body:' . PHP_EOL .
      '  expected_config:' . PHP_EOL .
      '    settings: {  }' . PHP_EOL .
      '    type: text_with_summary' . PHP_EOL .
      '  update_actions:' . PHP_EOL .
      '    change:' . PHP_EOL .
      '      settings:' . PHP_EOL .
      '        max_length: 321' . PHP_EOL .
      '      type: text' . PHP_EOL,
      $data
    );

    // Check newly exported configuration.
    $fileData = $yamlSerializer->decode(file_get_contents($configFilePath));

    $this->assertEqual('text', $fileData['type']);
    $this->assertEqual(['max_length' => 321], $fileData['settings']);
  }

  /**
   * @covers \Drupal\update_helper\ConfigHandler::getPatchFile
   */
  public function testGetPatchFileSerializerSupport() {
    $configList = \Drupal::service('config_update.config_list');
    $configReverter = \Drupal::service('config_update.config_update');
    $configDiffer = \Drupal::service('update_helper.config_differ');
    $configDiffTransformer = \Drupal::service('update_helper.config_diff_transformer');
    $moduleHandler = \Drupal::service('module_handler');
    $configExporter = \Drupal::service('update_helper.config_exporter');

    $configHandlerYaml = new ConfigHandler($configList, $configReverter, $configDiffer, $configDiffTransformer, $moduleHandler, \Drupal::service('serialization.yaml'), $configExporter);
    $this->assertStringEndsWith('config_handler_test.yml', $configHandlerYaml->getPatchFile('update_helper', 'config_handler_test'));

    $configHandlerJson = new ConfigHandler($configList, $configReverter, $configDiffer, $configDiffTransformer, $moduleHandler, \Drupal::service('serialization.json'), $configExporter);
    $this->assertStringEndsWith('config_handler_test.json', $configHandlerJson->getPatchFile('update_helper', 'config_handler_test'));

    $configHandlerPhpSerialize = new ConfigHandler($configList, $configReverter, $configDiffer, $configDiffTransformer, $moduleHandler, \Drupal::service('serialization.phpserialize'), $configExporter);
    $this->assertStringEndsWith('config_handler_test.serialized', $configHandlerPhpSerialize->getPatchFile('update_helper', 'config_handler_test'));
  }

}
