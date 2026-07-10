# Product Finder – Session Handover Notes

Last updated: 10 July 2026 (v1.6.0)

This file carries context between Claude Code sessions. It can be deleted once no longer useful.

## What this plugin is

WordPress/WooCommerce quiz plugin ("Product Finder") with an Elementor widget. Users answer
multiple-choice questions and get product recommendations rendered via a CrocoBlock/JetEngine
listing template (with a built-in fallback grid). Includes email capture that sends a styled
results email with a shareable results URL (30-day transient token, `?pf_results=TOKEN`).

## Key concepts

- **Finder types:** `cosmeceuticals` (simple products, per-category result limits) and
  `beauty` (variation/shade-aware, scoring keys `pid:variation_id`, lip/cheek intelligence).
- **Categories:** Beauty: base, concealer, lip, cheek, lip_cheek, eye (one product per category).
  Cosmeceuticals: cleanser (1), exfoliator (1), moisturiser (1), essential (2), specialty (2) —
  numbers are per-category result limits in `class-pf-ajax.php` (`$cosm_cat_limits`).
- **Day/Night mode:** optional; each product row has a Set (both/day/night); results render in
  two tabs; limits apply per tab. Tab styling lives in a CPT metabox (`_pf_dn_styles`), NOT Elementor.
- **Follow-up questions:** any answer can have one conditional follow-up question with its own
  answers/products. Frontend uses a history-stack navigation. Scoring keys `"qi_ai"` in
  `followup_answers` POST field.
- **Scoring:** rank inversion (`max_rank + 1 - rank`) + coverage bonus
  (`raw × (1 + questions_matched/total_answered)`).
- **Email styling:** CPT metabox (`_pf_email_styles`): logo, header image, accent colour,
  heading, sub-heading (wp_editor), subject, footer. A "Send Test Email" button in the box
  emails a sample (3 placeholder WooCommerce products) to the logged-in admin's address.
- **Lead capture (v1.6.0):** every email submission is stored in `{$wpdb->prefix}pf_leads`
  (`includes/class-pf-leads.php`: email, consent flag, readable answers JSON, recommended
  products JSON, date). Admin screen: Product Finder → Submissions (filter by finder,
  delete rows, CSV export via admin-post `pf_export_leads`). Capability defaults to
  `manage_options`, filterable via `pf_leads_capability`. Table is created/updated from
  `pf_leads_db_version` option check on `admin_init` (dbDelta), so no reactivation needed.
- **Consent + notifications (v1.6.0):** per-finder options in `_pf_options`:
  `enable_consent` (default on), `consent_text`, `notify_email` (instant lead alert).
  `notify_email` is stripped from the public `data-options` markup and from the
  compute_results JSON response — never expose it to visitors.
- **Rate limiting (v1.6.0):** `pf_send_results_email` is capped at 5 sends/IP/hour
  (transient `pf_email_rl_<md5(ip)>`, `PF_Email::RATE_LIMIT`).
- **Duplicate finder (v1.6.0):** row action in the finders list clones the post (draft,
  "(Copy)" suffix) with all meta via `admin_action_pf_duplicate_finder`.
- **Debugging:** frontend console logging is gated behind `?pf_debug=1` in the page URL.

## State at handover

- v1.5.2 (pushed): cosmeceuticals category save-whitelist fix (⚠️ site owner must re-select
  categories on product rows and re-save each finder once), PHP 8.2 compat, Safari option
  fix, `?pf_debug=1` log gating, removed `pf_get_finder`, JSON/email validation.
- v1.6.0 (this commit): the full feature bundle — lead capture log + Submissions screen +
  CSV export, marketing consent checkbox, send-test-email button, per-IP rate limiting,
  duplicate-finder row action, owner notification email. Plugin source now lives in the
  `product-finder/` folder; a downloadable `product-finder-<version>.zip` sits at repo root
  (rebuild it on every version bump — `.gitignore` has an exception for it).

## Pending / discussed next steps

- **User will supply a JPEG design** for further email template refinement (template already
  built in `class-pf-email.php`; replicate the design when provided).

## Conventions

- Bump `PF_VERSION` (plugin header + constant in `product-finder.php`) on every change — it
  cache-busts all CSS/JS.
- All admin strings translatable, text domain `product-finder`.
- British English in UI labels ("moisturiser", "colour").
