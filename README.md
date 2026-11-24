# DevDiggers Buy Now Pay Later for WooCommerce

Run your own buy now, pay later program in WooCommerce. Approved customers get a credit limit, choose Pay in 4 or Pay in 30 days at checkout, and pay each installment later from My Account with any payment method the store accepts.

- **Author:** [DevDiggers](https://devdiggers.com/)
- **Version:** 1.1.0
- **License:** GPLv3 or later
- **Requires:** WordPress 6.5+, WooCommerce 9.0+, PHP 7.4+
- **Compatible with:** HPOS, WooCommerce Cart and Checkout blocks
- **Live Demo:** [Free](https://demo.devdiggers.com/buy-now-pay-later-for-woocommerce/) · [Pro](https://demo.devdiggers.com/woocommerce-postpaid/)
- **Pro:** [DevDiggers Buy Now Pay Later for WooCommerce Pro](https://devdiggers.com/product/woocommerce-buy-now-pay-later/)

The WordPress.org listing text lives in `readme.txt`.

## What it does

The store gives the credit, so there is no lender, API key or per order fee. A customer applies in My Account, the store approves them, and from then on they can buy on a plan up to their limit. The plugin schedules the installments, emails a reminder before each due date and an overdue notice after a miss, and puts every repayment back onto the customer's credit.

### AI assistants (MCP)

Ask Claude, ChatGPT, Cursor, VS Code or any MCP client about your Pay Later book in plain words: "How much am I owed and how much is overdue?", "What is due this week?", "Show me Sara's Pay Later orders."

- Tools for the credit overview (total owed, overdue, missed installments, due in the next 7 days, applications waiting, credit used and repaid), customers with their limits and balances, one customer's orders, installments and history, the installments list (overdue, due soon, upcoming, paid), the transactions ledger and a settings summary
- Collections review prompt
- Optional: let the assistant mark an installment paid (same as Mark paid on the Installments screen, with the payment received email), logged under WooCommerce > Status > Logs
- Same access as the Pay Later screens: store managers only, customers and visitors are refused
- Managed in DevDiggers Plugins > AI & MCP: one MCP server for every DevDiggers plugin, an on / off switch per plugin and per write tool (write tools start off), a Try panel on every tool and an activity log
- Needs WordPress 6.9+ (Abilities API) and the MCP Adapter, which WooCommerce 10.3+ bundles

## Setup

1. Activate the plugin and run the setup wizard.
2. Enable the Pay Later method under **WooCommerce > Settings > Payments**.
3. Approve customers under **DevDiggers Plugins > Pay Later > Customers**.
4. Switch Pay in 4 or Pay in 30 days on or off under **Configuration > General**.

## Free vs Pro

| Feature | Free | Pro |
| --- | --- | --- |
| Pay Later gateway, classic and block checkout | Yes | Yes |
| Pay in 4 and Pay in 30 days plans | Yes | Yes |
| Store credit limit, manual balance adjustments | Yes | Yes |
| Credit limits per customer, custom Net 15/45/60 terms | No | Yes |
| Manual or no approval, activate on approval | Yes | Yes |
| Installment price on product, list and cart pages, shortcode | Yes | Yes |
| Reminder and overdue emails, pause credit when overdue | Yes | Yes |
| My Account section with schedule, history and Pay now | Yes | Yes |
| Refund and cancel return credit, dashboard and reports | Yes | Yes |
| Plan builder: unlimited custom plans, per-plan order limits, plan fees | No | Yes |
| Late fees, grace period, interest, partial payments | No | Yes |
| Document verification, allowed roles, excluded products | No | Yes |
| Message audience and plan, custom Learn more dialog | No | Yes |
| Appearance settings | No | Yes |
| Bulk actions, CSV export, manual reminders | No | Yes |

## Development

```bash
npm install
npm run build          # webpack, then text domain replacement and i18n
php bin/test-free-boundary.php   # asserts no Pro code ships in this plugin
npm run build:release  # zip for WordPress.org
```

Sources live in `src/` (`admin`, `front`, `blocks`). Every file in `assets/js` and `assets/css` must map to an entry in `webpack.config.js`; the boundary test checks this.

Data is stored in two tables, `{prefix}ddwcpp_transactions` (the ledger) and `{prefix}ddwcpp_installments`, plus user meta for limits, balances and approval status. A daily cron event, `ddwcpp_daily_event`, sends reminders and overdue notices.

Useful hooks:

- `ddwcpp_can_use_postpaid` filter: final say on whether a customer may use Pay Later for an amount
- `ddwcpp_gateways_excluded_for_dues` filter: gateways hidden when paying an installment
- `ddwcpp_balance_recorded`, `ddwcpp_installment_paid`, `ddwcpp_installment_overdue`, `ddwcpp_plan_completed` actions
- `ddwcpp_mcp_abilities` filter: add, change or remove the AI assistant (MCP) tools, registered in `includes/integrations/mcp-integration.php` under the `bnpl/` namespace

## Support

- [Documentation](https://docs.devdiggers.com/woocommerce-postpaid/)
- [Support forum](https://wordpress.org/support/plugin/devdiggers-buy-now-pay-later-for-woocommerce/)

## Changelog

### 1.1.0 (2026-11-24)

- New: AI assistants (MCP). Ask Claude, ChatGPT, Cursor, VS Code or any MCP client how much you are owed, what is overdue or due this week, and about a customer's Pay Later orders, installments and history. An optional tool marks an installment paid, exactly like Mark paid on the Installments screen; it stays off until you allow changes.
- New: DevDiggers Plugins > AI & MCP page. Connect Claude, ChatGPT, Claude Code, Codex, Cursor, VS Code or any MCP client by signing in and clicking Allow in WordPress, with nothing to copy. Switch each plugin and each write tool on or off, test tools in the Try panel and review every call in the activity log.
- Improved: Updated the bundled DevDiggers framework to 1.3.0. The title bar with the Save button now stays visible on desktop while you scroll an edit screen, and admin charts and pickers no longer break when another theme or plugin replaces the shared select2 script.

### 1.0.0 (2026-10-21)

- Initial release: Pay in 4 and Pay in 30 days on store credit, installment pricing on product pages, My Account repayments, reminders and overdue notices, dashboard and admin screens. Full list in `readme.txt`.
