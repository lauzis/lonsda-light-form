# lonsda-light-form

Lightweight Carbon Fields form builder for WordPress.

Built on [lauzis/wp-plugin-packages](https://github.com/lauzis/wp-plugin-packages),
so its settings page, logging, notices, toasts and migrations are the same
components the other plugins in this account use.

## Why it exists

Our site ran on Gravity Forms, which is an excellent plugin. Nothing here is a
complaint about it.

When its licence came up for renewal we looked at what we were actually using,
and it was a very small part of what it offers: receive a contact form, store
what was submitted, send an email about it. Conditional logic, multi-page forms,
payment gateways, calculations, the integrations directory — all of it real,
well built, and none of it in use on our site. Renewing would have paid for a
great deal we had never touched.

So this does the part we needed. It is not a replacement for Gravity Forms and
should not be chosen over it by anyone using more than a fraction of it — the
comparison below is there to make that easy to check. What it is instead is
small: one person can read the whole of it, there is no licence to renew, and
nothing in it is trying to sell you the paid version.

### Against Gravity Forms

| | Lonsda | Gravity Forms |
| --- | --- | --- |
| Field types | text, textarea, checkbox | 30-odd, including file upload, date, address, payment |
| Validation | required, email, regex, max length | the above plus per-field rules and custom validators |
| Conditional logic | — | fields, pages and notifications |
| Multi-page forms | — | yes |
| Entries | stored, listed, filterable, CSV | the above plus notes, editing, bulk actions, partial entries |
| Notifications | one per form, per-form recipients | many per form, routed by conditional logic |
| Auto reply | yes | yes |
| Spam | honeypot, minimum completion time, reCAPTCHA v2 | the above plus Akismet and reCAPTCHA v3 |
| Translations | keys generated per field, `.po`/`.mo` or WPML | WPML/Polylang integration |
| Import/export | JSON | JSON |
| Integrations | a hook | Mailchimp, Stripe, HubSpot, Zapier and many more |
| Licence | none | annual |

The right-hand column is why Gravity Forms costs what it does, and it is worth
the money to anyone using it. If you need any of that, buy it — this is not a
substitute. The point of the table is the left-hand column: for our project,
that narrow list was the whole requirement.

What replaced the integrations is one action. A submission is handed to
`lonsda_form_submitted` and the theme decides what it means — here, adding the
address to the right Mailchimp-style audience for the language it was submitted
in. That is about thirty lines, and it is exactly as much integration as the
site needed.

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
- **Self tests** — ten scenarios, around 145 assertions, run against the live install and cleaning up after themselves.
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

### A submission that was not accepted

**Whatever the reason, the form comes back filled in.** Nobody retypes anything:
a failed validation redisplays every answer and marks only the fields at fault,
a tripped spam check hands the whole lot back, and so does a form that had been
open too long for its nonce to still verify — the case where the loss would hurt
most, since it is nobody's mistake and can swallow a message somebody spent ten
minutes on. That last one redisplays with a fresh nonce, so sending again works
rather than failing the same way.

The answers are read and sanitised *before* the nonce is checked, which is what
makes the expired case possible. They are unverified at that point, so they are
sanitised by field type exactly as an accepted submission's are, kept only for
fields the form actually has, and escaped again by the renderer on the way out —
a form to press send on, not content the page has taken on trust. Nothing is
stored and no hook fires: a submission that failed the nonce or a spam check
still reaches neither `lonsda_form_submitted` nor `lonsda_form_rejected`.

A submission that *succeeded* is the one case the answers are dropped. Leaving
them under a "thank you" reads as though nothing was sent.

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
- `{all_fields}` expands to every field and its answer, in form order: the label
  in bold, the answer on the line beneath, a blank line before the next. Leave
  the message empty and that is what you get, wrapped in the metadata below.
- Notifications are sent as HTML, so a label can be bold and an answer can sit
  under it rather than beside it. The markup is spare on purpose — paragraphs, a
  rule, small text — because heavy markup is what gets a message held back as
  suspicious. A message you write yourself goes through `wpautop`, so the line
  breaks you typed into the box survive.
The form editor lists them all in a **Placeholders** panel under Publish,
including this form's own field tokens by name and label — which is the half
that cannot be documented, since it depends on the form. Click one to copy it.

- `{submission_details}` is the small block of where and when it came from —
  page, language, IP, timestamp — with any of those it does not have left out.
  That conditional part is a placeholder rather than code so the default message
  can be a single string, which is what makes it translatable.
- Also available: `{form_title}`, `{site_name}`, `{site_url}`, `{submitted_at}`,
  `{page_title}`, `{page_url}`, `{language}`, `{locale}`, `{ip}`, `{user_agent}`. A field
  whose Name matches one of these does not displace it — the fixed set has to
  mean the same thing on every form.
- The subject default is `{form_title}` rather than the title itself because a
  field default is fixed when the field is registered and has no form to ask.
  It also keeps up with a form that is later renamed. An empty subject falls
  back to `New submission: <form title>`.
- Naming a field in **Reply-To field** makes replies go to whoever submitted it.
- Everything a visitor typed is escaped on the way in. It is the one place a
  stranger's words are placed into markup that lands in someone's inbox.

Because a test form is now a form that would email the administrator, the self
tests cancel every send at a priority nothing else uses. A full run makes zero
`wp_mail()` calls.

Both sending and storing are listeners on `lonsda_form_submitted`, with no more
access than a theme doing the same job. `lonsda_form_notification` filters the
mail before it goes; returning an empty array sends nothing.

## Entries

Submissions are stored in `{prefix}llf_entries` and listed under **Lonsda Forms
→ Entries**: filter by form, status or language, sort by any column, expand one
to see every answer plus the page, language, IP and user agent, delete
individually, or download the lot as CSV.

The language filter offers only languages entries were actually submitted in —
so a language nobody has used is not listed, and one since removed from the site
still is, because its entries did not go with it. Sorting is restricted to a
fixed set of columns: an `ORDER BY` column is an identifier rather than a value,
so it cannot be passed through `prepare()` and has to be a name this code chose.
The current filter and sort are carried on every link, so opening an entry or
paging does not quietly reset them.

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
| `language` | Language code of that post — a bare `lv`. What WPML and Polylang natively report, and what groups a submission with others like it. |
| `locale` | The same language in full, `lv_LV`. Resolved *from the language*, not read off the request, so an admin viewing the entry in English does not change the answer. This is the form the Translations screen names its files in, the only one that tells `en_US` from `en_GB`, and the one the [auto reply is sent in](#which-language-it-is-sent-in). |
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

The same filter adds keys of your own — and changing `locale` there changes the
language the auto reply goes out in. Note that a submission rejected for a
failed nonce or a tripped spam check does not reach either hook at all — it is
logged and dropped.

## Translating a form's own wording

Labels, the submit button and the confirmation are typed into the editor, so
they are not in the `.pot` file and gettext cannot reach them. Each string
carries a translation key instead.

Keys are derived from the field name and cannot be edited: a field named `email`
gets `field_email_label` for its label and `field_email_placeholder` for its
hint. Rename the field and both follow.

An editable key was one more box on every field to serve a case almost nobody
has, and a key nobody can change cannot drift from the field it names — so there
is nothing to fill in and nothing to keep in step.

```
name: email        →  field_email_label,  field_email_placeholder
rename to mail     →  field_mail_label,   field_mail_placeholder
```

A field with no placeholder contributes no placeholder string. That is a field
without one, not a string waiting to be translated.

### The form's own messages

The confirmation, the notification and the auto reply are the form *speaking*
rather than asking, so they are keyed to the form rather than shared across the
site — two forms both saying "Thank you" may want to say it differently, whereas
two fields both labelled `Email` rarely do.

The prefix is the form's **Text ID**, on the Fields tab:

```
contact-form__form_title
contact-form__success_message
contact-form__notification_subject     contact-form__notification_message
contact-form__auto_reply_subject       contact-form__auto_reply_message
```

The title is in that list because `{form_title}` puts it in front of whoever
the mail is addressed to. It is the post's title rather than one of the form's
settings, which is how it stayed English while everything around it was
translated — a Latvian subject line with the word *Contacts* in the middle of
it.

Lower case, dashes for spaces, accents folded — anything else typed into the box
is converted to that on save, so the field agrees with the keys rather than
showing `My Form ID` while every key says `my-form-id`.

It is filled in from the title when the form is first saved and then left alone.
Unlike a field key, it does **not** follow a rename: a field is renamed lightly,
a form is retitled for presentation, and having every message translation vanish
because somebody tidied a title would be a poor trade.

Each is translated *before* its placeholders are filled in, so a translation can
put `{name}` where that language wants it rather than where English had it.

The submit button is keyed `form_submit` — one key for every form on the site,
because "Send" is "Send" everywhere and a key per form would mean translating
the same word once for each. Its wording stays editable per form; only the
translation is shared.

Two forms with a field named alike likewise share a translation, which is
usually right. Give a field a distinct name where it should read differently.

### Two ways to run a form in several languages

Both are supported and nothing links a form to a language, so the choice is per
form — a shared contact form translated once, and a campaign form written
separately per language, can coexist.

| One form, translated | A separate form per language |
| --- | --- |
| Labels, placeholders and button written in English, translated under Translations. One form on every language version of the page. | One form per language, each written in that language, each on its own page. |
| Entries land together with the language recorded on each — one list, filterable. | Entries separated by form, convenient when different people handle each language. |
| Add a field once, then translate it. | Add the field to every form; forgetting one is easy. |
| Notification recipients and wording shared. | Each language can notify different people, with its own subject and message. |
| Fields named alike share a translation, so `Email` is translated once site-wide. | No translation step at all. |

**Taking the translated route, write the originals in English.** They are what a
translator is shown and what a visitor sees when no translation exists for their
language. Nothing enforces it, and a form written in Latvian works — but
changing your mind later means retyping every label, and any translation
pointing at the old wording is orphaned.

**Taking the separate-forms route, ignore Translations entirely.** A form whose
labels are already in the right language needs no keys.

On a multilingual site the form editor says this once, dismissibly, because the
decision is cheap now and expensive later.

### The plugin's own wording

The messages a visitor reads that were never typed into a form — *This field is
required.*, *Please enter a valid email address.*, *Please check the highlighted
fields.*, the Yes and No a ticked box becomes in an email — are listed on the
Translations screen too, under **General texts**, and the form picker has an
entry for them on their own.

They used to be plain `__()` calls against the plugin's text domain, which put
them out of reach of the screen where everything else is translated: a site
could translate every label and still tell people "This field is required." in
English, because the only route was a `.po` inside a folder WordPress replaces
on every update. They now go through the same layer a form's own strings do —
WPML first, then the form-content MO — keyed `general__error_required` and the
like, with the English as the msgid so an untranslated one still reads as a
sentence.

Only what a visitor can see. Admin wording is translated the ordinary way;
whoever reads it can also read a `.po`.

### Delivering the translations

Two routes, tried in that order.

**WPML String Translation.** Strings are registered when a form is saved rather
than when one is rendered — a translator should see a string before anyone has
visited the page it appears on.

**Gettext files**, for everyone else. `wp i18n make-pot` scans source and these
strings are rows in a table, so nothing can discover them — **Lonsda Forms →
Translations** works from the stored forms instead.

Translate in the browser: pick a language and a form, fill in the boxes, save.
The list is grouped — form fields, submit button, confirmation message,
notification email, auto reply email, general texts — because a flat list of a
dozen keys gives no sense of which of them a visitor actually reads.

The placeholders are listed beside the boxes, copyable, for the same reason they
are listed beside the form editor: a translation of a subject line has
`{site_name}` in it, and a token retyped as `{site-name}` passes every check
there is and turns up in an email as a brace. Both panels are drawn from
`Notifications::placeholderReference()` — one list, so a screen cannot name a
token that does not exist, and a self test checks every listed token against
what the substitution actually produces.
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

The language list comes from WPML or Polylang where one is running, because the
translation plugin decides which locale a page is served as — and that is the
only name a file can usefully have. WordPress's own list of installed
translations is a fallback for sites without one, used *instead of* rather than
as well as. Offering both listed Latvian twice on a WPML site: `lv_LV`, which is
what pages are served as, and a bare `lv` from a stray language pack. The second
looked right and would never have been loaded. The locale actually serving the
page is marked in the list.

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

## Styles

The plugin ships one small stylesheet, `assets/css/form.css`, switched on under
**Settings → Appearance** and enqueued only on a page that actually renders a
form.

**It covers three things and stops:** the confirmation notice, the failure
notice, and a field that came back rejected — border, ring and message. The form
itself is left alone. A theme owns how inputs and buttons look, and a plugin
that restyles those is a plugin somebody has to fight. What no theme has an
opinion about is markup it has never seen, which is exactly what appears after a
submission: on an unstyled site a rejected field looked identical to an accepted
one, at the one moment the form had something to say.

Every rule is a single class scoped under `.llf-form`, with no `!important`
anywhere, so a theme rule of the same weight written later simply wins. The
colours are custom properties, so recolouring the lot is one rule:

```css
.llf-form {
    --llf-error-border: #b32d2e;
    --llf-error-text: #7a1d1e;
    --llf-error-background: #fdf3f3;
    --llf-success-border: #1e8c3a;
    --llf-success-text: #0d4a1c;
    --llf-success-background: #f0faf2;
}
```

Unticking the box stops the file being enqueued at all, rather than loading it
and overriding it. The handle is `llf-form`, registered on `wp_enqueue_scripts`,
so a theme can also `wp_dequeue_style()` it or register its own file under the
same handle.

The Appearance tab shows a live sample of all three states — drawn by the same
renderer, with the same wording, that a real form uses, so it cannot quietly
stop matching what a visitor sees. It is shown whether the styles are on or off:
knowing what ticking the box would do is the point of looking.

**On update, existing sites get these styles switched on.** A site that has
already styled its forms may see them change; unticking the box restores exactly
what was there before.

## Styling a rejected submission

A submission that fails validation comes back with the answers intact and the
offending fields marked, so a stylesheet can colour them without any JavaScript.
These are the classes the built-in styles use, and the ones to target when they
are switched off.

| Class | On |
| --- | --- |
| `llf-form--has-errors` | The form, when anything was rejected. |
| `llf-field--error` | The wrapper of each rejected field. |
| `llf-input--error` | The input itself. |
| `llf-input`, `llf-input--text` / `--textarea` / `--checkbox` | Every input, error or not. |
| `llf-error` | The message under a rejected field. |
| `llf-notice--error` | The message above the form. |

```css
.llf-input--error { border-color: #d63638; }
.llf-field--error .llf-error { color: #d63638; }
```

The class is on the input as well as the wrapper because colouring the border of
the thing that is wrong is the usual way to show it, and a stylesheet should not
have to reach in from a parent to do it.

Only rejected fields are marked — a class everything wears is no more use than
one nothing wears.

Rejected inputs also carry `aria-invalid="true"` and an `aria-describedby`
pointing at their message, so the reason is announced and not merely coloured.

## Testing a form's emails

The form editor's **Testing** tab sends the notification or the auto reply to an
address of your choosing — your own by default — with made-up answers filled in.

It goes through the real senders rather than reproducing them, so what arrives
is what a submission produces: the same templates, placeholders, translations
and `Reply-To`, and whatever a filter does to it on the way out. A test that
built its own message would only prove the test works.

- The recipient is replaced at the last moment, through the same filter a site
  would use, so everything before that point is untouched.
- The subject is prefixed `[TEST]`, so it cannot be mistaken for a real enquiry.
- Nothing is stored as an entry, and the submitted hook is not fired — a
  listener a theme has added should not run for a message nobody sent.
- IP and user agent are blanked. Recording your own address against a made-up
  submission would mislead in a message that may well be forwarded.
- A button whose message is not configured is disabled, and says what would
  switch it on. When a send produces nothing, the reason is reported rather
  than "nothing happened".

Both read what was **last saved**, so save before testing a change.

## Auto reply

A form can email whoever submitted it, confirming the message arrived. On the
**Auto reply** tab: a switch, a subject and a message, with wording that ships
ready to send.

Off unless switched on. It mails an address a stranger typed into a public form
— which is how a form becomes a way of sending mail to somebody who never asked
for it — so it should be a decision rather than a discovery.

The address comes **only** from a field with email validation. A field merely
named something like one has never been checked, and guessing would mean mailing
whatever was typed into it. Without such a field nothing is sent and the reason
is logged.

Same placeholders as a notification, passed through `wp_kses_post()` on the way
out, and sent as **both** an HTML part and a plain-text one.

### Which language it is sent in

**The one the form was submitted in.** That language is recorded with the
submission — the page's, under WPML or Polylang; the site's where neither is
running — and the reply switches to it before a word of the message is built:
the wording and its translation, the shipped defaults behind an empty box, the
`Yes` or `No` a ticked checkbox becomes, and whatever `lonsda_form_auto_reply`
adds. The language is put back afterwards, in a `finally`, so a filter that
throws cannot leave the rest of the request speaking Latvian.

On the page itself this changes nothing: the request is already in that
language. It matters the moment the reply is sent from anywhere else — a queue,
a retry, WP-Cron, an admin screen resending one — where the request has no
language worth having and the recorded one is all that still knows who the
message is being written to. It is also why the language is read back from the
submission rather than from `determine_locale()`, which answers for whoever is
running the code rather than for whoever is being written to.

A site that never installed the WordPress translation for that language still
gets the form's own wording translated. Core will not switch to a locale it has
no files for; the form strings are in a directory of their own and are loaded
for the language regardless, so the part a visitor actually reads arrives
translated either way.

The **notification** is not switched — it goes out in whatever language the
request is in. It is read by whoever runs the site rather than by the visitor,
so the submission's language is not obviously the right answer for it.

The text part matters: `wp_mail()` sends a single body, so declaring it HTML and
stopping there means a client showing plain text — or a mail setup that strips
the markup — gets the whole message flattened into one unbroken paragraph.
The text version is built from the HTML with the shape kept: a blank line
between paragraphs, single breaks where `<br>` was, and links carrying their
address, since a text reader has nothing to click. Notifications are sent the
same way. `lonsda_form_auto_reply` filters it; returning
an empty array sends nothing.

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
| Appearance | Whether to use the built-in styles, and a sample of what they do. |
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
| Auto reply | Off by default, the address it picks, placeholders, the text part, stripped script, and that the submission's own language is the one it is answered in — and is put back afterwards. |
| Placeholders and translation | The two together: every token replaced in the site's own language, a full example translation of one form, a translation that moves the tokens somewhere else, the words a token stands for translated with it, and a number filled into a translated message rather than before it. |
| Testing tab | The recipient swap, the `TEST` mark, filled-in answers, a refused address, and that a test is not stored as an entry. |
| Translations | Collection, the POT, saving, merging, clearing, and that no language is offered twice. |
| Clean up leftovers | Removes anything an interrupted run left behind. |

Because a new form is prefilled with the site's own address, a test form is one
that would email the administrator — so every send is cancelled for the duration
of a run, at a priority nothing else uses. A full run makes zero `wp_mail()`
calls, which is asserted rather than assumed.

The scenarios that need a translation write to the locale `zz_ZZ`, which no site
serves.
Using a real one would put test strings in front of visitors, and cleaning up
afterwards would delete a translation somebody had actually made.

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
