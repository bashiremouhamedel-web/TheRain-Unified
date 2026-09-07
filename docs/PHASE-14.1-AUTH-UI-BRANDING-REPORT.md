# PHASE 14.1 AUTH UI, BRANDING, REGISTRATION, LANGUAGE, AND DATABASE REPORT

## Status

**PARTIAL**

The Unified authentication pages were rebuilt around a shared responsive auth design system. Browser screenshot validation remains blocked by the local PHP 8.0.28/XAMPP server intermittently terminating before accepting HTTP requests.

## Login Before/After

The public root `login.php` is a compatibility redirect to Unified auth. `auth/login.php` now uses the supplied TheRain Unified logo and city background, a two-panel layout, language selector, email/username field, password toggle, CSRF field, responsive form, and no fake social-login controls.

## Registration Before/After

The public root `register.php` redirects to Unified registration. `auth/register.php` uses the same design system and collects business, owner, currency, locale, timezone, module, terms consent, owner picture, and business logo fields through the existing registration service.

## Branding Changes

Removed duplicate legacy auth markup and old Pharmacy POS branding from Unified auth. Browser titles are `TheRain Unified | Sign In` and `TheRain Unified | Create Account`.

## Responsive Validation

Responsive CSS includes desktop two-panel, tablet, and mobile single-column breakpoints. Browser viewport screenshots were **NOT TESTED** because the local PHP server exited before serving requests.

## Language System

The reusable CORE auth translation service supports all eight catalog locale codes: English, French, Arabic, Portuguese, Swahili, Hausa, Spanish, and Chinese. Same-request switching was executed and confirmed to produce different localized labels. English and French have broad auth copy; other locales have translated core auth copy with English fallback for less-common registration labels.

## Translation Coverage

Login and registration headings, descriptions, labels, buttons, password hints, branding labels, upload hints, module hints, and accessibility password labels use the shared translation service. Validation error messages from the existing backend remain English and are not yet fully localized.

## Background Image Asset Path

The requested assets are available at `dist/image/BJIMAGE/BJ1.png` and `dist/image/logo.png`. They are copied from the verified existing artwork and used through one CSS background variable and shared page markup.

## Registration Fields

Existing secure backend fields remain in use: business name/type/description/email/phone/address/country/city, timezone, language, currency, enabled management system, owner full name/email/phone/password/confirmation, profile picture, business logo, and terms consent. Upload validation remains content/MIME/size based with randomized private storage names.

## Database Name Standardization

`.env.example` already defaults the Unified platform to `therain_unified`. Legacy `database/db.sql` and Pharmacy module SQL continue to refer to `pharmacy` because they are legacy Pharmacy schema sources; they were not blindly renamed or modified.

## Database Connection Changes

No additional connection change was necessary. `core/config/database.php` remains environment-driven.

## SQL/Installer Changes

No migration or schema change was required for the auth UI pass. Existing migration and dbumi architecture remains authoritative.

## Legacy Pharmacy Safety

The real `pharmacy` database was not used for destructive operations. Existing Pharmacy compatibility and legacy action routes remain separate from the Unified auth wrappers.

## Security Tests

Focused PHP lint passes for auth pages, i18n, registration service, and wrappers. Buffered rendered-template checks passed for titles, requested asset paths, absence of raw PHP markers, and removal of old Pharmacy branding. Locale switching was executed across all eight codes. Full form submission, upload, CSRF, and registration rollback tests remain **NOT COMPLETED** in this pass.

## HTTP Tests

**BLOCKED.** Detached PHP server attempts exited before accepting connections, consistent with the previously documented local Windows/XAMPP instability.

## Browser Tests

**NOT TESTED.** No valid screenshots were captured in this environment.

## Production Database Safety

The real `pharmacy` database was not modified. Its existence/table count was previously checked; this pass did not run destructive operations against it.

## Remaining Issues

- Browser-level desktop/mobile screenshots remain blocked by the local PHP server instability.
- Backend validation messages are not fully localized.
- Forgot-password functionality does not exist, so the UI does not expose a dead link.
- No commit or push was performed; existing uncommitted Phase 13/14 work and the untracked `query` artifact were preserved.
