# lonsda-light-form

Lightweight Carbon Fields form builder for WordPress.

Built on [lauzis/wp-plugin-packages](https://github.com/lauzis/wp-plugin-packages),
so its logging, admin notices, toasts and settings page are the same components
the other plugins use.

## What is here

A working scaffold rather than a finished plugin. The settings page, delivery
and spam configuration, logging and the setup notice are in place. The form
builder itself — defining fields, rendering a form, handling a submission — is
not.

| | |
| --- | --- |
| `classes/Settings.php` | Registers `config/settings.json` plus the package's `logs` schema. |
| `classes/Admin.php` | Admin screen, and a setup notice for anything still unconfigured. |
| `classes/Logs.php` | Facade over the shared logger. |

No AI provider here: this plugin sends email, so it does not register the
package's `llm` schema.

## Install

```
composer install
```

## Next

- A form post type or Carbon Fields container for defining fields.
- A shortcode or block that renders a form.
- Submission handling: nonce, capability, sanitisation per field type, the
  honeypot and minimum-time checks the settings already expose, then email
  and/or storage.
