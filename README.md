# Update Helper

This module offers supporting functionalities to make configuration updates easier.

### Important notes

This module has Drupal Console command. In order to execute it properly, you have to use Drupal console installed with your project.
In case of composer build, it's: `[project directory]/vendor/bin/drupal`

Drupal console version has to be greater then 1.2.0.

### Provided features

Update helper module provides Drupal console command that will generate update configuration changes (it's called configuration update definition or CUD). Configuration update definition (CUD) will be stored in `config/update` directory of the module and it can be easily executed with update helper.

It's sufficient to execute `drupal generate:configuration:update` and follow instructions.
There are several information that has to be filled, like module name where all generated data will be saved (CUD file and update hook function), then description for update hook and so on.
Command will generate CUD file and save it in `config/update` folder of module and it will create update hook function in `<module_name>.install` file.

Additional information about command is provided with `drupal generate:configuration:update --help` and it's also possible to provide all information directly in command line without using the interactive mode.

### Checklist integration

Additionally, to the generation of configuration update definition and execution of it in update hooks, it's also possible to generate checklist entries for every executed configuration update. In order to use that functionality `update_helper_checklist` module has to be enabled. That module hooks in `generate:configuration:update` over events and it will automatically provide additional options and checklist entry generation.

This functionality is really helpful for distributions and could be interesting for modules that comes with a lot of new configuration changes or update hooks.
For distributions, there is a proposal to use one single module for collection of updates. That module would contain all generated configuration update definitions (CUDs), all update hooks and also `updates_checklist.yml` file for all generated updates.

### How to prepare environment to create configuration update

Workflow to generate configuration update for a module is following:
1. Export configuration files included in module with new changes (commit that to custom branch or stage it)
2. Make clean installation of Drupal with the previous version of the module (version for which one you want to create configuration update).
3. When module is installed and old configuration imported, make code update to previously created brunch or un-stage changes (with code update also configuration files will be updated, but not active configuration in database)
4. Execute update hooks if it's necessary (for example: in case when there are dependency updates for module and/or core)
5. Now is a moment to generate configuration update (CUD) file and update hook code. For that we have provided following drupal console command: `drupal generate:configuration:update --module=<module name> --include-modules=<module name>`. Command will generate CUD file and save it in `config/update` folder of the module and it will create update hook function in `<module_name>.install` file.
6. After the command has finished it will display what files are modified and generated. It's always good to make an additional check of generated code.

Workflow to generate configuration update for a distribution is following:
1. Export configuration files included in distribution with new changes (commit that to custom branch or stage it)
2. Make clean installation of the previous version of the distribution (version for which one you want to create configuration update, for example `8.x-1.x` branch).
3. When distribution is installed and old configuration imported, make code update to previously created brunch or un-stage changes (with code update also configuration files will be updated, but not active configuration in database)
4. Execute update hooks if it's necessary (for example: in case when there are dependency updates for module and/or core)
5. Now is a moment to generate configuration update (CUD) file and update hook code. For that we have provided following drupal console command: `drupal generate:configuration:update --include-modules=<comma separated list of modules with changed configurations>`. Command will generate CUD file with all configuration changes for distribution and save it in `config/update` folder of the module you have provided and it will create update hook function in `<module_name>.install` file.
6. After the command has finished it will display what files are modified and generated. It's always good to make an additional check of generated code.
