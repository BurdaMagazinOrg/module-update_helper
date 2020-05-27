<?php

namespace Drupal\update_helper\Generators;

use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Validator\Constraints\NotBlankValidator;

/**
 * Implements d8:configuration:update command.
 */
class ConfigurationUpdate extends BaseGenerator {

  /**
   * {@inheritdoc}
   */
  protected $name = 'd8:configuration:update';

  /**
   * {@inheritdoc}
   */
  protected $description = 'Generates a composer.json file';

  /**
   * {@inheritdoc}
   */
  protected $alias = 'config-update';

  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output) {

    $extensions = $this->getExtensions();
    $defaultExtension = NULL;

    $questions['module'] = new Question('Enter a module/profile');
    $questions['module']->setAutocompleterValues(array_keys($extensions));
    $questions['module']->setValidator([Utils::class, 'validateRequired']);

    $vars = $this->collectVars($input, $output, $questions);

    $lastUpdate = drupal_get_installed_schema_version($vars['module']);
    $nextUpdate = $lastUpdate > 0 ? ($lastUpdate + 1) : 8001;

    $questions['update-n'] = new Question('Please provide the number for update hook to be added', $nextUpdate);
    $questions['update-n']->setValidator(static function ($update_number) use ($lastUpdate) {
      if ($update_number === NULL || $update_number === '' || !is_numeric($update_number) || $update_number <= $lastUpdate) {
        throw new \InvalidArgumentException(
          dt(
            'The update number "!number" is not valid',
            ['!number' => $update_number]
          )
        );
      }
      return $update_number;
    });

    $questions['description'] = new Question('Please enter a description text for update. This will be used as the comment for update hook.', 'Configuration update.');

    $questions['include-modules'] = new Question('Provide a comma-separated list of modules which configurations should be included in update (empty for all).', 'Configuration update.');

    $questions['from-active'] = new ConfirmationQuestion('Generate update from active configuration in database to configuration in Yml files?');

    $this->collectVars($input, $output, $questions);




    $this->addFile()
      ->path('{module}_update_{update-n}.yml')
      ->content('dd');
  }

  /**
   * Get installed non_core extensions.
   *
   * @return \Drupal\Core\Extension\Extension[]
   *   The list of installed non-core extensions keyed by the extension name.
   */
  protected function getExtensions(): array {
    $extensions = array_filter(\Drupal::service('extension.list.module')->getList(),
      static function ($extension): bool {
        return ($extension->origin !== 'core');
      });

    ksort($extensions);
    return $extensions;
  }

}
