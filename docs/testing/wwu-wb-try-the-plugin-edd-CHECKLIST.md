# Try the plugin end-to-end — **Easy Digital Downloads (EDD)**

> Evaluator checklist. Take an EDD store from install to a full, verified withdrawal on a real
> (staging) order — reach the form → two-step flow → durable medium → evidence log → merchant
> processing → uninstall. ~30–45 min. Anyone can run it.
>
> For the *exemptions* corner (Art. 59 consent at checkout) see
> [`wwu-wb-edd-consent-CHECKLIST.md`](wwu-wb-edd-consent-CHECKLIST.md).

## Who/what this is for

A merchant or reviewer who wants to confirm the **whole compliance flow** works on EDD: reaching the
withdrawal form, the two-step statement→confirmation, the durable-medium receipt (e-mail + PDF +
verifiable link), the tamper-evident evidence log, and the admin side.

> **Read this first — EDD has no native withdrawal button (by design, for now).** Unlike WooCommerce
> (3 surfaces) and FluentCart (4 portal surfaces), the EDD integration is a **data-source + checkout
> consent** integration. The plugin does **not** inject a withdrawal button into the EDD purchase
> receipt or purchase-history pages. An EDD customer reaches the withdrawal form via the **standalone
> public page**, a **direct link with the EDD payment key**, or the **guest lookup**. The whole legal
> flow (two-step, durable medium, evidence log, admin) works fully — only the *EDD-page button* is
> absent. Don't hunt for a button on the EDD receipt; use §3 below.

## 0. Environment

- [ ] WordPress 5.8+, PHP 7.4+, **Easy Digital Downloads 3.0+ active** (the custom-tables order API).
- [ ] Install the plugin from the release ZIP (Plugins → Add New → Upload) and **Activate**.
- [ ] (For the PDF copy) the ZIP bundles **Dompdf** — PDF works out of the box; otherwise e-mail-only
  (still compliant), with an admin notice.

## 1. One-time setup (WP-Admin → **Withdrawal Button**)

1. [ ] **Settings → General:** turn the function **on** (`enabled`).
2. [ ] **Settings → Where the button applies:** **Applicability = "Always"** for testing.
3. [ ] **Settings → Receipt & evidence:** timestamp provider, **Attach PDF** on, **notification e-mail**.
4. [ ] **Public withdrawal page (required for EDD):** create a WP page (e.g. *"Right of withdrawal"*) with
   the **`[wwu_wb_form]`** shortcode (or the **"Withdrawal — self-service"** block), publish it, and set it
   as the plugin's public form page (`public_form_page_id`). **This is the EDD customer's entry point** —
   link to it from your account/receipt area or e-mails.

## 2. Create a test order

- [ ] Complete a normal EDD purchase for a **non-exempt** download (status **complete**), as a **test
  customer** (note the customer e-mail and, if you can, the **payment key**).

## 3. Reach the withdrawal form (EDD — three ways, pick one)

As the **customer**:
- [ ] **(a) Guest lookup (most realistic):** open the **public page** with no order context → the **guest
  lookup form** shows (order number + e-mail). Enter the EDD **order number** + the **purchase e-mail** →
  it verifies and grants a short-lived access token → the form opens for that order.
- [ ] **(b) Direct link with payment key:** open
  `…/<public-page>/?wwu_wb_order=<edd_order_id>&key=<edd_payment_key>` → authenticates directly.
- [ ] **(c) Logged-in customer:** if the buyer has a WP account, just open the public page while logged
  in → the chooser lists their eligible EDD orders.

> If the order isn't found/eligible: confirm it's **complete** + non-exempt + Applicability = Always, and
> that the e-mail matches the purchase e-mail. As admin, `?wwu_wb_diag=1` prints the applicability
> decision. (Lookup is rate-limited to 10 attempts / 5 min per IP.)

## 4. The two-step withdrawal (the legal core)

1. [ ] On the form → **Step 1 — submit the statement** (name, order, e-mail, optional reason) → Step 2
   is revealed.
2. [ ] **Step 2 — confirm** with the **statutory-words-only** button → **success** screen.
3. [ ] **No-JS check (optional):** disable JS and repeat — the standalone page posts to `admin-post.php`
   and renders Step-1 → Step-2 → success without scripts.

## 5. Durable-medium receipt

- [ ] The **customer receives the acknowledgement e-mail** (plain `wp_mail()` path — EDD has no WC
  mailer). It contains name, order, items, reason, timestamp, the **evidence row hash**, the trader's
  details, the **PDF link** and the **verify link**.
- [ ] **PDF copy** opens (if `send_pdf` on + Dompdf present).
- [ ] **Verify link** shows `order number`, `submitted at`, `row hash`, **record intact = true**, and
  **within window** (`?format=json` for the machine version).

## 6. Evidence-log integrity (tamper-evidence)

- [ ] **Withdrawal Button → Requests:** the confirmed request is listed; **"chain intact"** badge green
  (append-only hash-chained `{prefix}wwu_wb_log`).
- [ ] OpenTimestamps proof **pending → confirmed** by the hourly cron (no need to wait).

## 7. Merchant processing

1. [ ] In **Requests**, click **"Open order (refund)"** → the EDD order/payment screen opens
   (`edit.php?post_type=download&page=edd-payment-history…`). Issue the refund in EDD.
2. [ ] Click **"Mark processed"** → status **"Processed"**; a `request_processed` event is appended to
   the log.
3. [ ] Click **"Resend e-mail"** → re-sends the acknowledgement (20-second throttle).

## 8. Exemptions (Art. 59) — usually relevant for EDD

- [ ] EDD sells **digital downloads**, so the digital-immediate exemption (`59_o`) is common. Run
  [`wwu-wb-edd-consent-CHECKLIST.md`](wwu-wb-edd-consent-CHECKLIST.md) to verify the **checkout consent
  capture** (download- and **category-aware** via `download_category`) — the button is hidden for an
  exempt download **only** after consent is captured; otherwise it stays (fail-safe).

## 9. Compliance helpers (admin)

- [ ] **Withdrawal Button → Compliance:** go-live countdown, **Annex I-B model form**, **pre-contractual**
  info, ready-to-paste **legal clauses** — copy into your policies (review by counsel).

## 10. Smoke test (optional, fast)

- [ ] **Withdrawal Button → Debug Inspector** → enable debug → **Run ALL** → 0 fail. (Or REST
  `POST /wp-json/wwu-wb/v1/debug/run-tests` with the `wp_rest` nonce.)

## 11. Uninstall / data hygiene

- [ ] By default (`erase_on_uninstall` **off** = legal-hold) the **evidence tables are kept**; plugin
  **options** are removed (EDD keeps its own `wwu_wb_*` order meta in EDD order-meta — covered by the
  options/meta cleanup).
- [ ] Only with **`erase_on_uninstall` = on** does uninstall **drop** the evidence tables + secret
  (irreversible). Multisite: handled per site.

## Pass criteria

- [ ] The customer can reach the form via guest lookup **or** payment-key link **or** logged-in chooser.
- [ ] Two-step flow completes with the statutory confirmation label (and works with JS disabled).
- [ ] Acknowledgement e-mail + (optional) PDF + verify link delivered; verify shows **record intact**.
- [ ] Requests shows the request, **chain intact**, and reflects refund + processed status.
- [ ] Smoke tests 0 fail; uninstall respects the legal-hold default.

## Known limitation (worth noting in your evaluation)

- **No EDD-page button yet.** Withdrawal entry on EDD relies on the standalone public page / payment-key
  link / guest lookup. Injecting a button into the EDD purchase receipt + purchase-history is a sensible
  future enhancement (the data layer + consent already work); until then, **link the public page from your
  EDD receipt/account area and order e-mails** so customers can find it.

## Troubleshooting

- **Order not found in lookup** → wrong e-mail / order not complete / exempt / rate-limited (10 / 5 min).
  `?wwu_wb_diag=1` (admin) prints the decision.
- **No e-mail** → check your SMTP/mail plugin (EDD uses the plain `wp_mail()` fallback).
- **No PDF** → `send_pdf` off or Dompdf vendor missing (admin notice). E-mail-only still satisfies the duty.
