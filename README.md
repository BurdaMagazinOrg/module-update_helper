# Update Helper

This module offers supporting functionalities to make configuration updates more easy.

### Updating existing configuration (with using of generated configuration changes)

Update helper module provides drupal console command that will generate update configuration changes (it's called configuration update definition or CUD). Configuration update definition (CUD) will be stored in `config/update` directory of the module and it can be easily executed with update helper.

It's sufficient to execute `drupal generate:update_helper:update` and follow instructions.
There are several information that has to be filled, like module name where all generated data will be saved (CUD file and update hook function), than description for update hook and so on.

Command will generate CUD file and save it in `config/update` folder of module and it will create update hook function in `<module_name>.install` file.

Additional information about command are provided with `drupal generate:update_helper:update --help` and it's also possible to provide all information directly in command line without using the wizard.
