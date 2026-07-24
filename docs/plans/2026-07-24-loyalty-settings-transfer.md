# YS Checkout Loyalty And Settings Transfer Plan

## Goal

Unify YITH Points & Rewards and WPLoyalty under the existing "購物金整合"
admin tab, provide provider-correct checkout controls, make all checkout-facing
colors follow the backend design tokens, and add a validated full settings
export/import workflow.

## Invariants

1. YITH's native form remains outside `form.checkout` and is the only YITH
   submission source. The YS surface is a nameless, id-less visual proxy.
2. WPLoyalty keeps its native reward-picker workflow. YS may restyle the launcher
   but must not invent a direct-points API that WPLoyalty does not provide.
3. Failed or unavailable enhancement leaves each provider's native UI usable.
4. Import accepts only a versioned YS package with known keys and valid values.
   A failed write restores the complete pre-import snapshot.
5. Export includes every canonical YS setting plus the WPLoyalty integration
   labels currently stored in its dedicated option.
6. Frontend field, border, button, text, section, and radius values resolve from
   the backend checkout design tokens, with fallbacks only for fail-open use.

## Delivery

### 1. Contract Tests

- Lock YITH and WPLoyalty settings into separate cards under the loyalty tab.
- Lock the YITH proxy structure: numeric input, apply, use-all, max hint, and no
  form ownership attributes.
- Lock WPLoyalty to its real modal-launch behavior.
- Lock token-based loyalty and coupon styles.
- Lock the transfer schema, whitelist validation, rollback behavior, nonce, and
  capability checks.

### 2. Loyalty Admin And Checkout

- Move the YITH display label, enable switch, and diagnostics out of the checkout
  tab and into a YITH card under "購物金整合".
- Keep WPLoyalty in its own card and add a shared preview containing both real
  workflow shapes.
- Replace the clone-shaped YITH proxy with deterministic YS markup.
- Add a local "全部使用" control which fills the provider-derived maximum without
  submitting until the user presses apply.
- Preserve the native provider nodes until the replacement is visibly mounted.

### 3. Settings Transfer

- Add `YSSettingsTransfer` with schema version 1.
- Export effective canonical values and WPLoyalty labels.
- Validate format, schema, completeness, unknown keys, value types, colors,
  radius, CVS rate IDs, and bounded integration labels.
- Apply only after complete validation; snapshot and roll back on any failed
  write.
- Add AJAX download/upload controls to the database-management tab.

### 4. Verification

- Static: contract, transfer matrix, existing matrices, PHP lint, JS syntax,
  diff check, archive hygiene.
- Admin E2E: export, invalid file rejection, modified import, full round-trip,
  rollback injection, and restoration.
- YITH E2E: type, use-all, apply, cancel, over-limit/invalid input, no accidental
  checkout submit, and a real order after redemption.
- WPLoyalty E2E: native activity discovery, replacement launch, coexistence with
  YITH, fail-open, and no duplicate controls.
- Visual: desktop, tablet, and 390px mobile; token changes reflected for button,
  field, border, section background, text, and radius; no horizontal overflow.

## Release Boundary

This worktree may be deployed only to the owned development checkout for
verification. Commit, tag, push, release, and production deployment are outside
this implementation pass unless separately authorized.
