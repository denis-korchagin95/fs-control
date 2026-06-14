# Config Reference

> [!IMPORTANT]
> 
> This page about all the configuration options fs-control has.
> If you want to learn how to start using the tool,
> see [Getting started](https://github.com/denis-korchagin95/fs-control/tree/master?tab=readme-ov-file#getting-started)
> instead.

## YAML format

The fs-control uses configuration in famous YAML format.
If you aren't familiar with it, you can see [the YAML language specification](https://yaml.org/spec/1.2.2/).

This is how it can look like a simple config file:

```yaml
fs_control:
  paths:
    - ./example-fs/Container
  groups:
    Application: ~
  bindings:
    $/Application: Application
  rules:
    Permission:
      - Application
```

## Config file

A config file should be passed to `fs-control` as a main argument.

```console
./vendor/bin/fs-control example-fs-config.yaml
```

You can look at example config `example-fs-config.yaml` and apply the recommended settings to your project.

## Multiple paths

The `fs-control` don't state the philosophy about 1 config per 1 logic project part.
You can use it to control multiple paths if you have a similar structure to handle it.

To set up multiple paths, place another root directory in the config:

```yaml
fs_control:
  paths:
    - ./example-fs/Container
    - ./example-fs/Module
  groups:
    Application: ~
  bindings:
    $/Application: Application
  rules:
    Permission:
      - Application
```

## Wildcard bindings

A plain binding mounts a layer and expects a rule directory directly under it
(`Domain/Entity`). Real projects often group rules under a sub-domain folder that has no
rule of its own (`Domain/Foo/Entity`). Instead of writing one binding per
sub-domain, you can use a wildcard at the **end** of a binding path:

* `**` — absorbs any number of grouping folders, transparently, up to the first
  recognized rule.
* `*` — absorbs exactly one grouping folder.

```yaml
fs_control:
  paths:
    - ./example-fs/Container
  groups:
    Domain: ~
  bindings:
    $/Domain/**: Domain
  rules:
    Entity:
      - Domain
```

With `$/Domain/**` the following are all covered by that single line, at any depth and
with no config change when a new sub-domain folder is added:

```console
Domain/Entity              -> rule Entity (Domain)
Domain/Foo/Entity          -> rule Entity (Domain)
Domain/Foo/Bar/Entity      -> rule Entity (Domain)
Domain/Foo                 -> transparent mount point (bounded)
```

`$/Domain/*` behaves the same but for exactly one grouping level — a deeper rule
(`Domain/Foo/Bar/Entity`) would stay uncovered.

The wildcard absorbs folders only up to the **first folder whose name is a rule**; that
rule is then validated against the group as usual, and `deny_nested_rules` still applies
to the leaf. A folder that happens to share a rule's name is treated as that rule.

> [!NOTE]
> Wildcards are only allowed as the **last** segment of a binding path
> (`$/Domain/**`, `$/Domain/*`). A wildcard elsewhere (e.g. `$/*/Domain/**`) is rejected.

### Binding precedence

When several bindings match a path, the one with the **longest concrete mount** wins, so
declaration order does not matter. A specific literal binding always overrides a wildcard:

```yaml
bindings:
  $/Domain/**: Domain      # everything under Domain is transparently grouped...
  $/Domain/CQRS: CQRS      # ...except Domain/CQRS/* which is bound to the CQRS group
```

### Sub-contexts (namespace before the layer)

Wildcards absorb folders that sit **after** a layer. If a context places a sub-context
namespace **before** the layer (`Container/SubContextA/Application/...`), point `paths` at
each sub-context root so the layer is again the first segment, and reuse the same
bindings.

A `paths` entry may end with a single `*`, which expands to the **immediate
subdirectories** of that folder — so you do not have to list every sub-context by hand,
and a newly added sub-context is picked up automatically:

```yaml
fs_control:
  paths:
    - ./example-fs/Container/*
  groups:
    Application: ~
    Domain: ~
  bindings:
    $/Application/**: Application
    $/Domain/**: Domain
  rules:
    Service:
      - Application
```

This is equivalent to listing `./example-fs/Container/SubContextA`,
`./example-fs/Container/SubContextB`, ... explicitly. Only a single `*` as the **whole
last segment** is supported for `paths` (`**` and partial wildcards like `Foo*` are
rejected); files and dot-directories are ignored by the expansion.

A broad `*` and a deeper root can coexist — the most specific path wins. When an expansion
yields a directory that a deeper `paths` entry already covers, the broad one is dropped, so the
same directory is never scanned twice:

```yaml
fs_control:
  paths:
    - ./example-fs/Container/*          # every immediate sub-context...
    - ./example-fs/Container/Module/*   # ...but scan Module one level deeper
```

Here `Container/*` would expand to include `Module`; because the deeper `Container/Module/*`
covers it, the broad `Module` root is dropped (no double-scan), while new siblings under
`Container` are still picked up automatically by `Container/*`.

## Excluding paths

To exclude some paths from analysis, place a few of them in the config:

```yaml
fs_control:
  paths:
    - ./example-fs/Container
  exclude_paths:
    - ./example-fs/Container/Infrastructure/ParamConverter/Check
  groups:
    Application: ~
  bindings:
    $/Application: Application
  rules:
    Permission:
      - Application
```

Typically, you can use the exclude path feature to avoid project restructuring when you first tune the `fs-control`,
or to ignore errors until you are ready to fix them.

## Excluding directories

To exclude a directory and everything inside it from analysis, use `exclude_dirs`:

```yaml
fs_control:
  paths:
    - ./example-fs/Container
  exclude_dirs:
    - ./example-fs/Container/Infrastructure/Legacy
  groups:
    Application: ~
  bindings:
    $/Application: Application
  rules:
    Permission:
      - Application
```

Unlike `exclude_paths` which matches a single path exactly, `exclude_dirs` excludes the given directory
and all paths nested under it.

### Glob excludes

Both `exclude_paths` and `exclude_dirs` accept a glob (any entry containing `*`), matched against the
path **relative to the scan root** it lives under. `**` matches any number of directories (including
none), so a single line can exclude a directory wherever it appears:

```yaml
fs_control:
  paths:
    - ./example-fs/Container
  exclude_dirs:
    - '**/Doc'      # every "Doc" directory (and its subtree), at any depth
    - '**/Config'
```

The literal/glob distinction is preserved: an `exclude_paths` glob matches the exact directory only,
while an `exclude_dirs` glob also excludes everything nested under a matched directory. Glob entries are
not resolved on disk, so they need not exist when the config is loaded.

## Parameters

Parameters can be used by built-in `fs-control` functions or its extensions in the section `parameters`.

```yaml
fs_control:
  paths:
    - ./example-fs/Container
  parameters:
    deny_nested_rules: true
  groups:
    Application: ~
  bindings:
    $/Application: Application
  rules:
    Permission:
      - Application
```

Also, you can always to check the excluded paths
in the tool output using the flag `--show-excluded-paths` for some reason.

### Built-in parameters

`deny_nested_rules` (boolean) - It is used as a heuristic rule to help keep
the structure more linear and avoid deep nesting.

## Rule Attributes

Just like parameters, rule attributes can be built-in or supported by extensions under section `rule_attributes`.

An attribute can be set up for specific rule, for example, in the config:

```yaml
fs_control:
  paths:
    - ./example-fs/Container
  parameters:
    deny_nested_rules: true
  groups:
    Application: ~
  bindings:
    $/Application: Application
  rule_attributes:
    Permission:
      symfony_service: false
  rules:
    Permission:
      - Application
```

Or if you want to assign the attributes for all rules, you can set it up under `_defaults` rule:

```yaml
fs_control:
  paths:
    - ./example-fs/Container
  parameters:
    deny_nested_rules: true
  groups:
    Application: ~
  bindings:
    $/Application: Application
  rule_attributes:
    _defaults:
      allow_subdirectory_level: 2
  rules:
    Permission:
      - Application
```

### Built-in rule attributes

* `allow_subdirectory_level` (integer, min: 0) - Allows controlling nesting depth by either expanding
or narrowing it for specific rules, or by allowing only a fixed nesting depth in the _defaults section.
* `treat_exceed_subdirectory_level_as_fault` (boolean) - Allows interpreting exceeding the nesting limits set
in `allow_subdirectory_level` as an error. By default, such a path will be considered uncovered.

## Extensions

To use extensions, they need to be declared in the extensions section:

```yaml
fs_control:
  extensions:
    - FsControl\BuiltInExtension\SymfonyExcludeServiceChecker\Extension
  symfony_exclude_service_checker:
    configs:
      - /path/to/your/symfony_config
  paths:
    - ./example-fs/Container
  groups:
    Application: ~
  bindings:
    $/Application: Application
  rule_attributes:
    Permission:
      symfony_service: false
  rules:
    Permission:
      - Application
```

If they provide additional configuration, fill it out similarly and adjust the attributes for the rules as needed.

You can see the list of [built-in extensions](./built_in_extensions.md)
or [create a custom extension](./create_custom_extension.md) for the tool.
