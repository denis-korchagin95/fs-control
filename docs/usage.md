# Usage

Assume that we have a file structure, for example:

```console
example-fs
├── Book
│   └── Application
│       └── Book
│           └── Command
└── Shared
    ├── Application
    │   └── Command
    ├── Domain
    │   └── Entity
    └── Infrastructure
        └── ParamConverter
        └── Service
```

And config `test-config.yaml`:
```yaml
fs_control:
  parameters:
    deny_nested_rules: true
  paths:
    - ./example-fs/Shared
  groups:
    Application: ~
    Domain: ~
    Infrastructure: ~
  bindings:
    $/Application: Application
    $/Domain: Domain
    $/Infrastructure: Infrastructure
  rule_attributes:
    _defaults:
      allowed_subdirectory_level: 2
      treat_exceed_subdirectory_level_as_fault: true
  rules:
    Entity:
      - Domain
    ParamConverter:
      - Infrastructure
    Command:
      - Application
    Service:
      - Infrastructure
      - Application
      - Domain
```

Let's go over this example to highlight the key points of usage by modifying the structure and configuration.

## Violation paths and explain

The most important part of error correction is resolving violations
by either fixing the file structure or ignoring the error.

Add one folder to your structure:
```console
example-fs
├── Book
│   └── Application
│       └── Book
│           └── Command
└── Shared
    ├── Application
    │   └── Command
    ├── Domain
    │   └── Entity
    └── Infrastructure
        └── ParamConverter
            └── Service
        └── Service
```

Let's run it:
```console
./vendor/bin/fs-control test-config.yaml
```

And we immediately have a violation:
```console
Violation Paths:
/path/to/your/project/example-fs/Shared/Infrastructure/ParamConverter/Service

Violation Paths: 1
Uncovered Paths: 0
Unbounded Paths: 0
Excluded Paths: 0
Bounded Paths: 3
Allowed Paths: 4
```

If we cannot understand what's going on, then we just repeat the command with `--explain` flag.

```console
./vendor/bin/fs-control test-config.yaml --explain
```

For now, it's clearer by explanation provided by the tool (nested set error):
```console
Violation Paths:
/path/to/your/project/example-fs/Shared/Infrastructure/ParamConverter/Service
  The path attempts to share a few rules (ParamConverter, Service) when nested rules were denied


Violation Paths: 1
Uncovered Paths: 0
Unbounded Paths: 0
Excluded Paths: 0
Bounded Paths: 3
Allowed Paths: 4
```

Okay, this is one of possible errors. (rule nested denied)
Let's change the file structure up to:

```console
example-fs
├── Book
│   └── Application
│       └── Book
│           └── Command
└── Shared
    ├── Application
    │   └── Command
    ├── Domain
    │   └── Entity
    │   └── ParamConverter
    └── Infrastructure
        └── Service
```

Run:
```console
./vendor/bin/fs-control test-config.yaml --explain
```

And now we will get another type of errors (rule-breaking):

```console
Violation Paths:
/path/to/your/project/example-fs/Shared/Domain/ParamConverter
  The path is permitted under the "ParamConverter" rule to be part of groups (Infrastructure), but it is located in the "Domain" group


Violation Paths: 1
Uncovered Paths: 0
Unbounded Paths: 0
Excluded Paths: 0
Bounded Paths: 3
Allowed Paths: 3
```

You can fix this or just and the exclude path to the config if you are not ready to fix this at the moment.

```yaml
fs_control:
  parameters:
    deny_nested_rules: true
  paths:
    - ./example-fs/Shared
  exclude_paths:
    - ./example-fs/Shared/Domain/ParamConverter
  groups:
    Application: ~
    Domain: ~
    Infrastructure: ~
  bindings:
    $/Application: Application
    $/Domain: Domain
    $/Infrastructure: Infrastructure
  rule_attributes:
    _defaults:
      allowed_subdirectory_level: 2
      treat_exceed_subdirectory_level_as_fault: true
  rules:
    Entity:
      - Domain
    ParamConverter:
      - Infrastructure
    Command:
      - Application
    Service:
      - Infrastructure
      - Application
      - Domain
```

And now we have no errors:

```console
Violation Paths: 0
Uncovered Paths: 0
Unbounded Paths: 0
Excluded Paths: 1
Bounded Paths: 3
Allowed Paths: 3
```

Congratulations, now you know that how to solve the most important errors using `fs-control`.

## Unbounded paths

The unbounded paths means that tool cannot do analysis because there are no bindings and the rules.

Change the config up to

```yaml
fs_control:
  parameters:
    deny_nested_rules: true
  paths:
    - ./example-fs/Shared
  exclude_paths:
    - ./example-fs/Shared/Domain/ParamConverter
  groups:
    Application: ~
    Infrastructure: ~
  bindings:
    $/Application: Application
    $/Infrastructure: Infrastructure
  rule_attributes:
    _defaults:
      allowed_subdirectory_level: 2
      treat_exceed_subdirectory_level_as_fault: true
  rules:
    ParamConverter:
      - Infrastructure
    Command:
      - Application
    Service:
      - Infrastructure
      - Application
```

And run the tool:
```console
./vendor/bin/fs-control test-config.yaml --show-unbounded-paths
```

and we will see:

```console
Unbounded Paths:
/path/to/your/project/example-fs/Shared/Domain
/path/to/your/project/example-fs/Shared/Domain/Entity

Violation Paths: 0
Uncovered Paths: 0
Unbounded Paths: 2
Excluded Paths: 1
Bounded Paths: 2
Allowed Paths: 2
```

You need to create a bindings to allow the tool to start analysis of your file tree.

## Uncovered paths

The uncovered paths means that there is no any rule covering the specific path.

Change your config to check it:

```yaml
fs_control:
  parameters:
    deny_nested_rules: true
  paths:
    - ./example-fs/Shared
  exclude_paths:
    - ./example-fs/Shared/Domain/ParamConverter
  groups:
    Application: ~
    Infrastructure: ~
  bindings:
    $/Application: Application
    $/Infrastructure: Infrastructure
  rule_attributes:
    _defaults:
      allowed_subdirectory_level: 2
      treat_exceed_subdirectory_level_as_fault: true
  rules:
    ParamConverter:
      - Infrastructure
    Service:
      - Infrastructure
      - Application
```

And run the tool:

```console
./vendor/bin/fs-control test-config.yaml --show-uncovered-paths
```

You should see something like this:
```console
Uncovered Paths:
/path/to/your/project/example-fs/Shared/Application/Command

Violation Paths: 0
Uncovered Paths: 1
Unbounded Paths: 2
Excluded Paths: 1
Bounded Paths: 2
Allowed Paths: 1
```

You need to create a dedicated rule to allow starting analysis of this file structure.

## Bounded paths, Allowed Paths, Excluded Paths

Just for your information purpose, you can run:

```console
./vendor/bin/fs-control --show-bounded-paths --show-allowed-paths --show-excluded-paths
```

And it will show you paths output look like:
```console
Excluded Paths:
/path/to/your/project/example-fs/Shared/Domain/ParamConverter

Bounded Paths:
/path/to/your/project/example-fs/Shared/Application
/path/to/your/project/example-fs/Shared/Infrastructure
/path/to/your/project/example-fs/Shared/Domain

Allowed Paths:
/path/to/your/project/example-fs/Shared/Domain/Entity
/path/to/your/project/example-fs/Shared/Infrastructure/Service
/path/to/your/project/example-fs/Shared/Application/Command

Violation Paths: 0
Uncovered Paths: 0
Unbounded Paths: 0
Excluded Paths: 1
Bounded Paths: 3
Allowed Paths: 3
```

Typically, it can be more useful for extension developers and maintainers,
and people who want to understand how tool works deeply.

## CI - recommendation launch

We recommend setting up `deny_nested_parameters` as true in the parameters section.
And also to include `_defaults` section with `allow_subdirectory_level` as `2`
and `treat_exceed_subdirectory_level_as_fault` as true to `rule_attributes` section.

```yaml
fs_control:
  paths:
    - ./example-fs/Shared
  parameters:
    deny_nested_rules: true
  groups:
    Application: ~
  bindings:
    $/Application: Application
  rule_attributes:
    _defaults:
      allowed_subdirectory_level: 2
      treat_exceed_subdirectory_level_as_fault: true
  rules:
    Permission:
      - Application
```

And after that strict rules for CLI launch:

```console
./vendor/bin/fs-control example-fs-config.yaml --fail-on-uncovered-paths --fail-on-unbounded-paths
```

If you have difficulty understanding why a particular path is in a specific section,
you can use the `--explain` flag, and `fs-control` will try to add explanations for the paths.

```console
./vendor/bin/fs-control example-fs-config.yaml --fail-on-uncovered-paths --fail-on-unbounded-paths --explain
```

## Exit Codes

* `0` - OK.
* `1` - General errors (for example PHP fatal errors)
* `2` - Found violation paths
* `3` - Found uncovered paths (only with flag `--fail-on-uncovered-paths`)
* `4` - Found unbounded paths (only with flag `--fail-on-unbounded-paths`)
* `5` - Extension raised an error (only when an extension activated in the config)
