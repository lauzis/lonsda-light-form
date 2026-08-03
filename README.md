# lonsda-light-form

Lightweight Carbon Fields form builder for WordPress.

Built on [lauzis/wp-plugin-packages](https://github.com/lauzis/wp-plugin-packages),
so its settings page, logging, notices, toasts and migrations are the same
components the other plugins in this account use.

## Building a form

A form is a title and a list of fields, created under **Lonsda Forms → Add
Form**. Each row in the Fields list becomes one input.

| | |
| --- | --- |
| **Label** | Shown above the input. Required — a row without one is discarded on save. |
| **Name** | Identifier used when the submission is handled. Derived from the label if left blank. Duplicates get a numeric suffix, because two fields sharing a name would silently overwrite each other. |
| **Type** | `text`, `textarea` or `checkbox`. |
| **Placeholder** | Hint inside the empty input. The label stays visible regardless — a placeholder vanishes as soon as someone types, so it is a poor label. Not offered for a checkbox. |
| **Ticked by default** | Checkbox only. Leave off for anything the visitor should actively agree to. |
| **Required** | Rejected when empty, or unticked for a checkbox. |
| **Validation** | None, email, or a custom pattern. Only for single-line text — an email check on a textarea would never be useful, and is cleared if the type changes afterwards. |
| **Pattern** | A regular expression the value must match, written without delimiters, e.g. `[A-Z]{2}[0-9]{4}`. Shown only when Validation is set to a pattern. |
| **Maximum length** | In characters, not bytes. Blank or zero means no limit. |

Validation runs again on the server. What the renderer emits — `required`,
`maxlength`, `type="email"`, `pattern` — is a convenience for the visitor, and
anyone can remove it before posting.

## Putting a form on a page

Either the **Lonsda Form** block, or the shortcode:

```
[lonsda_form id="1"]
```

The id is the one shown in the Forms list. Both render at view time rather than
freezing a copy into post content, so editing a form updates it everywhere.

### Two ids, one of which is the right one

A form is edited as a post and rendered from a row in its own table, so it has a
post id and a form id. The Forms list, the shortcode and the block all use the
form id; the post id only ever appears in the address bar while editing.

They are easy to confuse and the failure is silent — a lookup by the wrong id
simply finds nothing. So a lookup that fails checks whether the id given was a
form's post id, and if it was, the message names the id that should have been
used. That message is shown to administrators only; visitors get nothing rather
than an error printed into the page.

The block reads the form id from an `llf_id` field on the REST response for the
same reason. The editor lists forms over the post REST API, which knows only
post ids, so without it the block would hand a post id to a lookup keyed by the
form id.

## After a submission

Each form carries its own confirmation wording, edited in the visual editor, and
a **Hide the form after submission** setting that is on by default — leaving a
filled-in form under a "thank you" reads as though nothing was sent and invites
a second submission. Switch it off to leave the form in place.

A form saved before these settings existed has neither stored. Both fall back to
the shipped default, so the behaviour is the same as if the defaults had been
chosen deliberately.

## Translating a form's own wording

Labels, the submit button and the confirmation are typed into the editor, so
they are not in the `.pot` file and gettext cannot reach them. Each string
carries a translation key instead.

Keys are generated from the field name — a field named `email` gets
`field_email_label` — and stay in step with it. Rename the field and the key
follows.

Edit a key and it stops following. That is the point: a key you chose is a key
something else may already refer to, so renaming the field must not silently
change it. The plugin can tell the two apart because it records what it last
generated alongside the key; a key that still matches that record is one nobody
has touched. Clearing the box hands it back to automatic.

The submit button works the same way, keyed from the form slug as
`form_{slug}_submit`, and its wording is editable per form.

```
name: email        →  field_email_label        follows the name
name: email        →  contact_email            edited, so left alone
rename to mail     →  contact_email            still left alone
clear the box      →  field_mail_label         back under automatic control
```

Strings are registered with WPML when a form is saved, rather than when one is
rendered — a translator should see a string before anyone has visited the page
it appears on. Any other translation system can hook `lonsda_form_string`, which
receives the text, its key and the context.

## Handling submissions

The plugin validates a submission and then hands it on. It does not store or
email anything itself — that belongs in a theme or a companion plugin, so this
one does not grow a mail stack.

```php
add_action( 'lonsda_form_submitted', function ( $values, $form, $context ) {
    // $values  — field name => submitted value, sanitised and validated
    // $form    — the stored definition: id, title, settings
    // $context — the metadata below
}, 10, 3 );
```

| Hook | When |
| --- | --- |
| `lonsda_form_submitted` | A submission passed every check. |
| `lonsda_form_rejected` | Validation failed; receives the errors. |
| `lonsda_form_validate` | Filter to add errors of your own before acceptance. |
| `lonsda_form_context` | Filter the metadata below. |

### Submission metadata

Gathered once, the same for every form, and passed to both the accepted and the
rejected hook — a rejection is often the more interesting one to look at. A form
does not opt in to any of it.

| Key | What it is |
| --- | --- |
| `form_id` | The form that was submitted. |
| `post_id` | The post or page it was submitted from, or `null` when there is no post — a form in a footer or widget, say. Null rather than `0`, which would read as a real id. |
| `language` | Language code of that post. Detected by the shared package's `Language` component, which handles WPML and Polylang and lets anything else answer through a filter. Falls back to the site locale. |
| `time` | Unix timestamp. |
| `submitted_at` | The same moment as `Y-m-d H:i:s` in UTC, for anything that has to be read by a person. UTC so it does not shift when the site's timezone setting changes. |
| `ip` | `REMOTE_ADDR`. |
| `user_agent` | Self-reported, truncated to 255 characters. |

`X-Forwarded-For` is deliberately **not** consulted. It is set by whoever sent
the request unless a proxy is known to overwrite it, so trusting it by default
would let a submitter choose the IP recorded against them. Behind a proxy that
does overwrite it, supply the real address through `lonsda_form_context`:

```php
add_filter( 'lonsda_form_context', function ( $context ) {
    $context['ip'] = $_SERVER['HTTP_X_FORWARDED_FOR'];  // only where a proxy sets it
    return $context;
} );
```

The same filter adds keys of your own. Note that a submission rejected for a
failed nonce or a tripped spam check does not reach either hook at all — it is
logged and dropped.

## Self tests

**Lonsda Forms → Self Tests** runs the plugin against the site it is installed
on: the real database, the real post type, the real submission handler. That is
deliberate. The failures worth finding are the ones a fixture cannot reproduce —
a table the migration never created, another plugin filtering the post query, a
capability that is not what anyone assumed.

| Scenario | Covers |
| --- | --- |
| Form creation and storage | The post, its projection into the table, both identifiers, editing, deletion. |
| Shortcode and block rendering | Both render paths, the nonce and honeypot, and the message shown when a post id is given instead of a form id. |
| Form submission | Acceptance, the hook and its arguments, the metadata, a stale nonce, a filled honeypot, the confirmation. |
| Field validation | Required, email, pattern, maximum length, required checkbox, and that the rules hold for a request that never saw the form. |
| Clean up leftovers | Removes anything an interrupted run left behind. |

Every form a run creates is titled `LLF Self Test — …` and removed afterwards,
including when a scenario fails or throws: cleanup is in a `finally`, and each
run starts by clearing leftovers so a stale form is never counted as a fresh
one. Cleanup also deletes table rows by title, in case a row outlived the post
that owned it. Nothing else on the site is touched and no email is sent.

## Spam

Every form carries a hidden honeypot and records when it was opened. A
submission that fills the honeypot, or arrives faster than the configured
minimum, is refused. Neither response says which check it tripped — that only
helps the next attempt. Both are configurable under Settings.

**reCAPTCHA v2** is offered per form, but only once both keys are filled in
under Settings. Until then the option is hidden rather than shown-and-broken: an
option that cannot work invites someone to switch it on and assume they are
protected. A form also records reCAPTCHA as off if the keys are later removed,
so it cannot claim protection the site is not configured to provide.

If Google cannot be reached to verify a response, the submission is allowed
through and the failure logged. Failing closed would break every form on the
site the moment Google is slow.

## Storage

Each form is one row in `{prefix}llf_forms` — id, title, and its structure as
JSON. That table is what the front end reads, so rendering a form is a single
indexed lookup.

Editing happens through a hidden post type, because that is what Carbon Fields
attaches a repeatable editor to, and it brings saving, nonces and capability
checks with it. The post is the editing surface; the table is the projection,
rewritten on every save and removed when the form is deleted.

## Upgrades

Schema changes are registered against the version that introduced them and
applied once each, in version order, by the shared migration runner. A fresh
install records the current version on activation instead of replaying the
history against an empty database.

## Development

```
composer install
```

Settings fields live in `config/settings.json`. After changing them, regenerate
the translation manifest so `wp i18n make-pot` can still see the strings:

```
vendor/lauzis/wp-plugin-packages/bin/schema-i18n \
  --domain=lonsda-light-form --out=languages/schema-strings.php config/settings.json
```

The editor script for the block is plain JavaScript using
`wp.element.createElement` rather than JSX, so there is no build step — one
small script is not worth a toolchain someone must install before changing a
label.

## Requirements

- PHP 8.0+
- WordPress 6.0+

## License

MIT
