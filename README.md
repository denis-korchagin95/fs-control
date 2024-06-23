# fs-control

fs-control is an analyzer for your directory tree to allow you to keep it under control.

Maybe you have some agreements in your project and rules to the directory tree,
but actually in real life it may not work on 100%.
This tool can allow you to define your variant of configuration and help you to fix that and control.

If your mindset changes about the directory tree, okay, it's cool.
Just edit configuration and fs-control will care about for you.
Also set you free from some "review battles" and allow you to concentrate on more important things.

## Getting started

You can install fs-control via Composer using the main `composer.json`:

```console
composer require --dev denis-korchagin95/fs-control
```

Or you may to install fs-control via Composer in a dedicated `composer.json`,
for example, in the `tools/fs-control` directory:

```console
mkdir -p tools/fs-control
composer require --working-dir=tools/fs-control denis-korchagin95/fs-control
```

## Usage

You need to create a configuration file for a whole project or just its small part.

The basic configuration file may look like:

```yaml
fs_control:
  paths:
    - ./example-fs/Shared
  exclude_paths:
    - ./example-fs/Shared/Infrastructure/ParamConverter/Check
  groups:
    Application: ~
    Domain: ~
    Infrastructure: ~
  bindings:
    $/Application: Application
    $/Domain: Domain
    $/Infrastructure: Infrastructure
  rules:
    Entity:
      - Domain
    ParamConverter:
      - Infrastructure
    Command:
      - Application
```

You can analyze the project using a configuration file for some project.

```console
./vendor/bin/fs-control example-fs-config.yaml
```

See [usage](./docs/usage.md), [tool concepts](./docs/concepts.md),
[built-in extension list](./docs/built_in_extensions.md),
and [config reference](./docs/config_reference.md) documentation for more examples and details.

If you need to solve your specific problems that are not supported by the tool,
you can [create a custom extension](./docs/create_custom_extension.md).

## Contribute

Any contributions are welcome. This repository is open to pull requests.
