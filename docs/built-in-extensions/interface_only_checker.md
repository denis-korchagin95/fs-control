# Interface Only Checker

This extension enforces that a rule's directory contains **only interfaces** — no concrete
`class`, `trait`, or `enum`. It is useful for keeping contract-only layers (for example
`Repository` interfaces under `Domain`) free of implementations.

It is driven by the `interface_only` rule attribute, in the same attribute-driven style as
the [Symfony Exclude Service Checker](./symfony_exclude_service_checker.md)'s `symfony_service`.

To use it, register the extension and mark a rule with the `interface_only` attribute:

```yaml
fs_control:
  extensions:
    - FsControl\BuiltInExtension\InterfaceOnlyChecker\Extension
  groups:
    Domain: ~
    Infrastructure: ~
  bindings:
    $/Domain/**: Domain
    $/Infrastructure/**: Infrastructure
  rule_attributes:
    Repository:
      interface_only: Domain
  rules:
    Repository:
      - Domain
      - Infrastructure
```

The check fires off the rule classification (the directory must be bound and classified as
that rule), then inspects the `*.php` files directly inside that directory.

## Attribute value

The value scopes enforcement to the binding group(s) under which the rule must be
contract-only:

* `true` — enforce wherever the rule is matched (any group)
* `"Domain"` — enforce only when the rule is matched under the `Domain` group
* `"Domain,Contract"` — enforce under any of the listed groups

In the example above, `Repository` must be interface-only under `Domain`, while concrete
implementations are allowed under `Infrastructure`.

A concrete type is any top-level `class`, `trait`, or `enum` declaration. `Foo::class`
constant expressions and anonymous `new class {}` expressions are ignored.

The output looks like:

```output
Interface-only violations (concrete types in a contract-only rule directory):
  /path/to/your/project/src/Domain/Repository
      UserRepository.php is not an interface
```

## Baseline

This extension takes part in the [baseline](../usage.md#baseline) workflow. Each offending
file is one baseline entry (category `not_interface`), so it ratchets per file — a newly
added concrete file in an already-baselined directory still fails. Run
`--generate-baseline=FILE` to capture the current findings, then `--baseline=FILE` to
suppress them:

```yaml
extensions:
    interface_only_checker:
        not_interface:
            - src/Domain/Repository/UserRepository.php
```
