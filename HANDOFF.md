# Product Finder – Session Handover Notes

Last updated: 10 July 2026 (v1.5.2, commit `d13b87f`)

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
  heading, sub-heading (wp_editor), subject, footer.
- **Debugging:** frontend console logging is gated behind `?pf_debug=1` in the page URL.

## State at handover

Version 1.5.2 committed locally but **never pushed** (session had no GitHub credentials).
The v1.5.2 commit (included in these files) contained:

1. **Critical fix:** cosmeceuticals categories were missing from the save whitelist in
   `PF_Admin::sanitize_questions()` — they were silently wiped on save. Now fixed.
   ⚠️ The site owner must re-select categories on product rows and re-save each finder once.
2. Fixed potential PHP 8 fatal (`$valid_categories` scope in follow-up product sanitising).
3. Declared `_debug` / `_new_styles` / `_new_scripts` as PF_Ajax properties (PHP 8.2 dynamic
   property deprecation could corrupt AJAX JSON).
4. Safari fix: category `<option>` filtering now also toggles `disabled`.
5. Console logging gated behind `?pf_debug=1` (frontend + add-to-cart JS); admin debug log removed.
6. Removed unused `pf_get_finder` AJAX endpoint.
7. Results-session transient now validates JSON before storing; email format regex added.

## Pending / discussed next steps

- **User will supply a JPEG design** for further email template refinement (template already
  built in `class-pf-email.php`; replicate the design when provided).
- Suggested feature bundle (user has NOT yet chosen): lead capture log (store email + answers +
  recommended products, admin list + CSV export), marketing consent checkbox, rate-limiting the
  `pf_send_results_email` endpoint, "send test email" admin button, duplicate-finder action,
  owner notification email.

## Conventions

- Bump `PF_VERSION` (plugin header + constant in `product-finder.php`) on every change — it
  cache-busts all CSS/JS.
- All admin strings translatable, text domain `product-finder`.
- British English in UI labels ("moisturiser", "colour").
