# lonsda-light-form

Lightweight Carbon Fields form builder for WordPress.

Built on [lauzis/wp-plugin-packages](https://github.com/lauzis/wp-plugin-packages),
so its settings page, logging, notices, toasts and migrations are the same
components the other plugins in this account use.

## What it does

- **Form builder** — fields with labels, placeholders, defaults and validation, edited in a tabbed screen and collapsed to a readable list.
- **Three field types** — single-line text, text area, checkbox.
- **Validation** — required, email, custom regular expression, maximum length. Enforced server-side, not only in the browser.
- **Block and shortcode** — the same form rendered either way, at view time rather than frozen into post content.
- **Confirmation** — per-form wording in a visual editor, and whether the form is hidden once accepted.
- **Notifications** — per-form recipients, subject and message, with any field usable as `{field_name}`.
- **Entries** — every submission stored, listed, filterable, marked New until opened, exportable as CSV.
- **Translations** — labels and buttons carry keys, translated in the browser or through `.po`/`.mo` files, with WPML supported directly.
- **Spam** — honeypot, minimum completion time, and optional per-form reCAPTCHA v2 with a test on the settings page.
- **Import and export** — form definitions as JSON, all or a selection.
- **Self tests** — seven scenarios run against the live install, cleaning up after themselves.
- **Hooks** — every submission is handed on with consistent metadata, so a theme can do anything this does not.

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

## Editing a form

The editor is tabbed, because the field list is what gets worked on repeatedly
and everything else was pushing it off the screen.

| Tab | What is on it |
| --- | --- |
| Fields | The inputs, collapsed to a list of labels so a long form stays readable. |
| Submit button | Button wording and its translation key. |
| Confirmation | The message shown after a submission, and whether the form is hidden. |
| Notifications | Who gets emailed, and whether entries are kept. |
| Protection | reCAPTCHA, when it is configured. |

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

## Notifications

A new form arrives prefilled: recipients from the site administration address
(Settings → General, the only contact address WordPress itself keeps) and the
subject as `{form_title}`.

Prefilled, not assumed. The address sits in the box where it can be read and
changed before the form is saved, rather than a form quietly mailing an address
nobody chose. Clearing the field sends nothing.

- Several addresses, separated by commas. Anything that is not an address is
  dropped rather than handed to `wp_mail()`, where one bad entry can lose the
  whole send.
- Both the subject and the message accept placeholders. **Any field can be used
  by its Name** — a field named `surname` becomes `{surname}`. That is the Name,
  not the Label. A blank answer comes out as nothing; a checkbox as Yes or No.
- `{all_fields}` expands to every field and its answer, one per line, in form
  order. Leave the message empty and that is what you get, wrapped in the
  metadata below.
- Also available: `{form_title}`, `{site_name}`, `{site_url}`, `{submitted_at}`,
  `{page_title}`, `{page_url}`, `{language}`, `{ip}`, `{user_agent}`. A field
  whose Name matches one of these does not displace it — the fixed set has to
  mean the same thing on every form.
- The subject default is `{form_title}` rather than the title itself because a
  field default is fixed when the field is registered and has no form to ask.
  It also keeps up with a form that is later renamed. An empty subject falls
  back to `New submission: <form title>`.
- Naming a field in **Reply-To field** makes replies go to whoever submitted it.
- The message is plain text — it is read, not designed, and plain text cannot
  render wrongly or be held back as suspicious markup.

Because a test form is now a form that would email the administrator, the self
tests cancel every send at a priority nothing else uses. A full run makes zero
`wp_mail()` calls.

Both sending and storing are listeners on `lonsda_form_submitted`, with no more
access than a theme doing the same job. `lonsda_form_notification` filters the
mail before it goes; returning an empty array sends nothing.

## Entries

Submissions are stored in `{prefix}llf_entries` and listed under **Lonsda Forms
→ Entries**: filter by form or status, expand one to see every answer plus the
page, language, IP and user agent, delete individually, or download the lot as
CSV.

An entry arrives **New** and becomes **Viewed** when it is opened — opening it
is the only evidence of being read that exists, so that is what marks it. The
count of unread ones sits in the admin menu the way WordPress shows anything
else pending; without it a stored entry is only found by going to look. A row
opened by mistake can be marked unread again.

The status is written before the page reads its rows back, so the badge and the
count reflect the click that caused them rather than lagging a request behind.

Keeping them is on by default. A notification that never arrives is otherwise a
lost enquiry, and mail is the part most likely to break quietly. It can be
switched off per form on the Notifications tab.

An entry stores each field's **label and type alongside its value**, rather than
a reference back to the form. Fields get renamed, retyped and deleted; an entry
has to stay readable as what was actually asked and answered at the time. The
form title is stored for the same reason — entries outlive the form that
collected them, and still say where they came from.

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

### Delivering the translations

Two routes, tried in that order.

**WPML String Translation.** Strings are registered when a form is saved rather
than when one is rendered — a translator should see a string before anyone has
visited the page it appears on.

**Gettext files**, for everyone else. `wp i18n make-pot` scans source and these
strings are rows in a table, so nothing can discover them — **Lonsda Forms →
Translations** works from the stored forms instead.

Translate in the browser: pick a language and a form, fill in the boxes, save.
Both a `.mo` and a `.po` are written, so a translation started here can be
carried on in Poedit or handed to someone else, and one done elsewhere can be
uploaded back. A `.po` is compiled to `.mo` on the way in, so either will do.

Saving merges rather than replaces. The editor shows one form at a time, so
treating a save as "these are all the translations there are" would wipe every
other form's work. An emptied box removes that translation rather than storing
an empty one, which gettext would treat as untranslated anyway while leaving a
misleading entry in the file. A key whose original no longer exists is dropped —
there is nothing left for it to translate.

The POT download is still there for translating outside WordPress entirely.

Files live in `wp-content/languages/lonsda-light-form/`, not in the plugin
folder — WordPress replaces that folder on every update and would take the
translations with it. They can equally be dropped there over FTP.

The translation key is the gettext **context** and the label is the **msgid**,
so an untranslated string falls back to the label somebody wrote rather than to
`field_email_label`. The file has to be named for the locale WordPress serves
the page in, or gettext never looks for it; the page shows the current locale
and lists what is installed.

WPML is consulted first and gettext fills in whatever it has no translation for.
Anything else can hook `lonsda_form_string`, which receives the text, its key
and the context.

## Spam

Every form carries a hidden honeypot and records when it was opened. A
submission that fills the honeypot, or arrives faster than the configured
minimum, is refused. Neither response says which check it tripped — that only
helps the next attempt. Both are configurable under Settings.

**reCAPTCHA v2** is offered per form, but only once both keys are filled in
under Settings. Whether it can run is decided when the form is rendered and
again when a submission is validated, not when the form was saved — the keys are
a site setting that changes independently, and a form saved before they existed
would otherwise stay recorded as not using reCAPTCHA until someone happened to
re-save it. Until then the option is hidden rather than shown-and-broken: an
option that cannot work invites someone to switch it on and assume they are
protected. A form also records reCAPTCHA as off if the keys are later removed,
so it cannot claim protection the site is not configured to provide.

If Google cannot be reached to verify a response, the submission is allowed
through and the failure logged. Failing closed would break every form on the
site the moment Google is slow.

### Testing the keys

The reCAPTCHA tab has a live test: the real tick box, using the saved keys, and
a **Test** button that verifies the token through the same code a submission
uses — a test with its own request would be testing itself.

Both halves matter. The box appearing proves the **Site Key** is accepted for
this domain; the check that follows proves the **Secret Key** is too. Only doing
both distinguishes "the box appears" from "the box actually protects anything",
and pasting the Site Key into both boxes is an easy mistake that shows up
nowhere else until a form is already live.

The test reads what is *stored*, so the settings have to be saved first.

## Settings

**Lonsda Forms → Settings**, in tabs:

| Tab | What is on it |
| --- | --- |
| Delivery | Site-wide defaults for where submissions go. |
| Spam | Honeypot and minimum completion time. |
| Google reCAPTCHA v2 | The two keys, links to Google's console, and a live test. |
| Import / Export | Form definitions as JSON. |
| Logging | Whether the plugin keeps a log, shared with the other plugins here. |

The settings page is rendered by the shared package from `config/settings.json`,
so it looks and behaves like every other plugin in this account.

Two of those tabs are panels rather than fields, and both are driven by
`assets/js/settings.js` rather than by script in the markup. Carbon Fields
renders an html field through React's `dangerouslySetInnerHTML`, which inserts
markup **without executing any script inside it** — anything inline there is
dead code that looks perfectly fine.

## Import and export

**Settings → Import / Export** moves form definitions between sites as JSON.
Tick the forms to include, or take all of them.

What travels is what a form *is* — fields, wording, notification settings — not
where it lives. No ids, no post references, no entries: an id means nothing on
the site it lands on, and entries are a record of what people sent rather than
part of the design.

Import always **creates**. Matching by title would be the only way to update,
and two forms may legitimately share one — quietly replacing the wrong form is
worse than leaving a duplicate to delete. Files are checked for a format marker
before anything is read, so a stray JSON is refused rather than half-applied.

Imported forms are written through the same path the editor uses, so one that
arrived from a file is indistinguishable from one built by hand. A translation
key someone chose is carried across as chosen, rather than being treated as
generated and rewritten the first time a field is renamed.

The panel sits inside the settings form, and HTML forbids nesting one form in
another — so export is an ordinary link, which still works with JavaScript off,
and import posts on its own.

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
| Stored entries | Storage, status, the unread count, filtering, CSV, deletion. |
| Notification emails | Recipients, placeholders, Reply-To, and that nothing is actually sent. |
| Clean up leftovers | Removes anything an interrupted run left behind. |

Because a new form is prefilled with the site's own address, a test form is one
that would email the administrator — so every send is cancelled for the duration
of a run, at a priority nothing else uses. A full run makes zero `wp_mail()`
calls, which is asserted rather than assumed.

Every form a run creates is titled `LLF Self Test — …` and removed afterwards,
including when a scenario fails or throws: cleanup is in a `finally`, and each
run starts by clearing leftovers so a stale form is never counted as a fresh
one. Cleanup also deletes table rows by title, in case a row outlived the post
that owned it. Nothing else on the site is touched and no email is sent.

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

They run on `init` at priority 20, not on `plugins_loaded`. Carbon Fields
registers its fields on `init` at priority 0, and anything that rebuilds a form
definition has to be able to read them — reading earlier returns nothing, which
is indistinguishable from a form with no fields.

Nothing writes a definition unless `FormBuilder::ready()` confirms the fields
are registered. Writing an empty definition over a good one destroys the form
while leaving the post it was built from untouched, which looks like data loss
and is not — but the form stops rendering all the same.

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
