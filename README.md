# fs-control

fs-control is an analyzer for you directory tree to allow you to keep it under control.

Maybe you have some agreements in your project and rules to directory tree, but actually in real life it may not work on 100%.
This tool can allow you to define your variant of configuration and help you to fix that and control.

If you mindset change about the directory tree, okay it's cool.
Just edit configuration and fs-control will care about for you.
Also set you free from some "review battles" and allow to concentrate on more important things.

## Getting started

You can install fs-control via Composer.

```
composer require --dev denis-korchagin95/fs-control
```

You can analyze the project using a configuration file for some project.

```
./vendor/bin/fs-control example-fs-config.yaml
```

The configuration file may look like:

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

## How to write the configuration file?

The configuration based on simple tree things:

* Groups - some semantic name; name of the part of you project, for example
* Bindings - the binding path where located some elements from one of a group that you specify
* Rules - describes whether in which group the target directory can be placed

You can write the configuration incrementally by set at least one group and at least one binding.
After that try it out with '' flag and it will show you uncovered paths. (because you have no rules yet)

For example, if you edit the `example-fs-config.yaml` to this variant:

```yaml
fs_control:
  paths:
    - ./example-fs/Shared
  groups:
    Application: ~
    Domain: ~
    Infrastructure: ~
  bindings: # <path pattern> -> group
    $/Application: Application
    $/Domain: Domain
    $/Infrastructure: Infrastructure
```

And run it by `./vendor/bin/fs-control example-fs-config.yaml --show-uncovered-paths` you see results:

```text
Uncovered Paths:
/path/to/your/directory/fs-control/example-fs/Shared/Domain/Entity
/path/to/your/directory/fs-control/example-fs/Shared/Infrastructure/ParamConverter
/path/to/your/directory/fs-control/example-fs/Shared/Infrastructure/ParamConverter/Check
/path/to/your/directory/fs-control/example-fs/Shared/Application/Command

Violation Paths: 0
Uncovered Paths: 4
Unbounded Paths: 0
Allowed Paths: 0
Bounded Paths: 3
```

And then decide which directories are acceptable and which one not

## Violations

If you put the breaking change by the rules to your directory tree you will see the violations:

```text
Violation Paths:
/path/to/your/directory/fs-control/example-fs/Shared/Application/ParamConverter

Violation Paths: 1
Uncovered Paths: 0
Unbounded Paths: 0
Allowed Paths: 3
Bounded Paths: 3
```

You can fix it and enjoy of the results ;-)

## CI

The fs-control will return the different codes for some situations,
and you could easily check it and use it as a ci pipeline tool.
