=== Buy Now Pay Later for WooCommerce ===
Contributors: devdiggers
Plugin URI: https://devdiggers.com/product/woocommerce-buy-now-pay-later/
Author: DevDiggers
Author URI: https://devdiggers.com/
Tags: buy now pay later, pay in 4, installments, net 30, store credit
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 9.0
WC tested up to: 11.2
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Run your own buy now, pay later program. Offer Pay in 4 and Pay in 30 days on store credit you control. No lender, no fees per order.

== Description ==

Klarna and Afterpay are great until you look at the fees, or a loyal customer gets turned down by someone else's credit check. Plenty of stores already let trusted customers pay later. They just do it with spreadsheets, invoices and reminder emails written by hand.

**[Buy Now Pay Later for WooCommerce](https://devdiggers.com/product/woocommerce-buy-now-pay-later/)** turns that into a proper checkout option. You approve the customers you trust and give each one a credit limit. They pick **Pay in 4** or **Pay in 30 days** at checkout, and the plugin tracks every installment, sends the reminders and lets them pay from My Account with any payment method you already accept.

The credit is yours. There is no third party lender, no API key, no account to sign up for and no cut taken from your orders. Everything runs inside WooCommerce on your own server.

= Quick links =

* [Pro Demo](https://demo.devdiggers.com/woocommerce-postpaid/)
* [Documentation](https://docs.devdiggers.com/woocommerce-postpaid/)
* [Support](https://wordpress.org/support/plugin/buy-now-pay-later-for-woocommerce/)
* [Upgrade to Pro](https://devdiggers.com/product/woocommerce-buy-now-pay-later/)

= How it works =

1. A customer applies from the Pay Later section in My Account, or you let every logged in customer use it straight away.
2. You approve them on the Customers screen and they get your store credit limit.
3. At checkout they choose the Pay Later payment method and a plan: Pay in 4 or Pay in 30 days.
4. The order total comes off their available credit and the installments are scheduled.
5. Before each due date they get a reminder email with a link to pay. They pay the installment in My Account with card, bank transfer or any other gateway you run.
6. Each payment goes back onto their credit, so they can shop again.

= What the free plugin does =

=== Pay in 4 and Net 30 payment plans ===

Two ready made plans cover what most stores need. Customers choose one at checkout and see exactly what they will pay and when.

* **Pay in 4**: four equal payments, one every two weeks. The first is due at purchase and the order stays on hold until it is paid, so your risk on a new customer is one quarter of the order.
* **Pay in 30 days**: the full amount in one payment, 30 days after the order. Classic Net 30 terms for trade and repeat customers.
* Switch either plan on or off under Configuration > General. Customers only see the plans their credit covers.
* No fees and no interest on either plan.

=== A store credit line for each customer ===

* One credit limit for every approved customer, saved when their credit line starts so a later change never moves what they owe
* Add or remove dues by hand, with a note and an optional email to the customer
* Available credit shown under the payment method at checkout
* Hide Pay Later at checkout for orders below or above an amount you set

=== Customer approval ===

* Manual review: customers apply in one click and you approve or reject them
* Or no review: every logged in customer can activate credit
* Optionally start the credit line the moment you approve someone
* Email to the store when a new application comes in, and to the customer when it is approved or rejected

=== Installment pricing on product pages ===

Showing "or 4 payments of $25.00 every 2 weeks" under the price is one of the simplest ways to lift order value, and it is on by default.

* Show it on product pages, in product lists and under the cart total
* Updates live when a shopper picks a variation
* Edit the text with placeholders such as {count}, {amount} and {interval}
* A Learn more link opens a short explainer with your active plans
* [ddwcpp_installments_message] shortcode for page builders and custom templates

=== Reminders and missed payments ===

* A reminder email a set number of days before every installment is due
* An overdue notice the day after a missed payment, with a link to pay
* Optionally pause credit for overdue customers until they catch up. Checkout tells them why Pay Later is not available.
* Payments settle the oldest installment first
* Nine customer and store emails using your WooCommerce email design. Edit the subject, heading and message of each one, with placeholders such as {amount} and {due_date}, or switch it off

=== My Account Pay Later section ===

Customers get their own page under My Account, with tabs that switch without reloading.

* Available credit, what they owe and a usage meter
* Next payment with a one click Pay now button
* Every order on a plan, with its full schedule
* A complete history of purchases, payments, refunds and adjustments
* Rename the menu item and change its address, for example /my-account/pay-later/

=== Orders, refunds and the admin dashboard ===

* Refund a Pay Later order from WooCommerce and the amount goes back onto the customer's credit
* Cancelled and failed orders return the credit too
* The payment schedule shows on the order screen, the thank you page, the customer's order view and the order emails
* Record a payment you received outside the store, such as a bank transfer, from the Installments screen
* Dashboard with outstanding dues, credit used, repayments, repayment rate, overdue amounts, upcoming payments and charts
* Customers, Installments and Transactions screens with search and filters
* Works with the classic checkout and the WooCommerce Cart and Checkout blocks
* Compatible with High-Performance Order Storage (HPOS)
* Setup wizard that gets you running in a couple of minutes

=== Make more from credit with Pro ===

[Buy Now Pay Later for WooCommerce Pro](https://devdiggers.com/product/woocommerce-buy-now-pay-later/) adds the controls stores ask for once credit sales take off. You can see every Pro setting in the free plugin, marked with a PRO tag.

* **Plan builder**: create and rename plans with their own number of payments, schedule, fee and order amount limits
* **Unlimited payment plans**: 3, 6 or 12 monthly payments, weekly schedules, up to 60 installments
* **Your own payment terms**: Net 15, Net 45, Net 60 or any number of days
* **Credit limits per customer**: give wholesale and trusted buyers a bigger line, edited inline on the Customers screen
* **Plan fees**: a fixed or percentage fee per plan, shown before the customer chooses
* **Late fees and a grace period**: charge once per missed payment, fixed or a percentage
* **Interest on repayments** and **partial payments** with a minimum amount
* **Document verification**: customers upload an ID or business licence when they apply, stored in a private folder, with re-upload after a rejection
* **Allowed user roles**: offer credit to wholesale or B2B customers only
* **Excluded products and categories**: keep gift cards or low margin items cash only
* **Message controls**: show the installment price to approved customers only, pick which plan it advertises, and rewrite the Learn more explainer with your logo
* **Appearance**: credit card style balance, tab styles, corners, eleven brand colors and your own labels
* **Bulk actions** for customers and installments, **CSV export** of installments and transactions, and one click payment reminders

== Installation ==

1. In your WordPress admin go to Plugins > Add New and search for "Buy Now Pay Later for WooCommerce".
2. Click Install Now, then Activate. WooCommerce must be active.
3. The setup wizard opens. Turn the program on, set the default credit limit, pick your plans and set the reminder.
4. Go to WooCommerce > Settings > Payments and make sure the Pay Later method is enabled. You can change its title and description there.
5. Approve your first customers under DevDiggers Plugins > Pay Later > Customers.

To install manually, upload the `buy-now-pay-later-for-woocommerce` folder to `/wp-content/plugins/` and activate it from the Plugins screen.

== Frequently Asked Questions ==

= Is this a lender like Klarna, Afterpay or Affirm? =

No. You are the one giving credit, so you decide who gets it and how much. The plugin never sends customer data anywhere and takes no fee from your orders.

= Do customers need a card to use Pay Later? =

Not at checkout. They pay each installment later from My Account with whatever payment methods your store accepts, such as card, PayPal or bank transfer. Cash on delivery and cheque are hidden for installment payments.

= What happens to the order with Pay in 4? =

The first payment is due on the day of the order. The order stays on hold until that first payment comes in, then WooCommerce processes it as usual. With Pay in 30 days the order is processed straight away.

= Can I offer Net 30, Net 60 or other payment terms? =

Net 30 is included as the Pay in 30 days plan. Net 15, Net 45, Net 60 or any other number of days is part of Pro.

= Can I give one customer a bigger credit limit? =

Every customer gets the store credit limit you set under Configuration > Credit & Approval. Setting a different limit per customer is part of Pro.

= What happens when a customer misses a payment? =

They get an overdue email with a link to pay. If you turn on Pause Credit they cannot use Pay Later again until they catch up. Late fees and a grace period are part of Pro.

= Can customers pay off everything at once? =

Customers pay their installments one at a time, oldest first, from the Pay Later section in My Account. Paying any amount at once, up to the full balance, is a Pro feature.

= What happens when I refund a Pay Later order? =

Refund it from the WooCommerce order screen as usual. The refunded amount goes back onto the customer's credit and comes off their open installments.

= Does it work with the block checkout? =

Yes. Pay Later and the plan choice work in both the classic checkout and the Checkout block.

= Where do customers see what they owe? =

In the Pay Later section of My Account. You can rename it and change its address under Configuration > General.

= I used the Pro plugin before. Will my data carry over? =

Yes. The free plugin reads the same customers, balances, installments and transactions. Settings that only exist in Pro stay saved and come back when you activate Pro again.

== Screenshots ==

1. Dashboard with outstanding dues, credit used, repayment rate and upcoming payments.
2. Pay in 4 and Pay in 30 days plan choice at checkout, with available credit.
3. Installment price under the product price, with the Learn more link.
4. The Pay Later section in My Account with the next payment and Pay now button.
5. Customers screen with approval status, dues and the Adjust Balance dialog.
6. Installments screen with upcoming, due soon and overdue views and Mark paid.
7. Credit and approval settings.
8. Payment plan switches under Configuration > General.

== External services ==

The plugin itself sends no data to any outside service. Applications, balances, installments and transactions are stored in your WordPress database, and emails go out through the WooCommerce mailer. It contains no licence checks, no update checks and no telemetry.

**1. Gravatar (avatar images)**

* What it is: Gravatar, the avatar service operated by Automattic, used through WordPress core's `get_avatar_url()`.
* What it is used for: Showing the administrator's and customers' avatars on the plugin dashboard and list screens. This is standard WordPress behaviour.
* When data is sent: When those admin screens are viewed and avatars are enabled under **Settings > Discussion**. Turning avatars off there stops the requests.
* What data is sent: A hash of each shown user's email address inside the image URL, plus the usual request data of the browser such as IP address and user agent.
* Terms of Service: https://automattic.com/terms/
* Privacy Policy: https://automattic.com/privacy/

The bundled DevDiggers framework, which draws the admin screens, connects to the two services below. Both run only inside the WordPress admin.

**2. DevDiggers extensions directory**

* What it is: A read only API on devdiggers.com that returns the public list of DevDiggers extensions.
* What it is used for: Showing available DevDiggers extensions on the Extensions admin page.
* When data is sent: Only when a logged in administrator opens the Extensions admin page. The response is cached for 24 hours.
* What data is sent: A standard outbound HTTP request only, meaning your server's IP address and a plugin user agent string. No personal data and no store data are sent. The page also loads each listed extension's image from devdiggers.com in the administrator's browser.
* Endpoint: https://devdiggers.com/wp-json/ddwcs/v1/plugins

**3. Newsletter subscription (optional)**

* What it is: A contact and newsletter endpoint on devdiggers.com.
* What it is used for: Adding your email address to the DevDiggers newsletter, only if you choose to subscribe.
* When data is sent: Only when an administrator submits the optional newsletter form in the plugin dashboard. Nothing is sent automatically.
* What data is sent: The email address you enter and your site URL.
* Endpoint: https://devdiggers.com/?fluentcrm=1&route=contact

The DevDiggers services are provided by DevDiggers. By using them you agree to the DevDiggers Terms and Conditions (https://devdiggers.com/terms-and-conditions/) and Privacy Policy (https://devdiggers.com/privacy-policy/).

== Source code and build tools ==

The admin, storefront and checkout block JavaScript and CSS are compiled from the `src/` folder with webpack and Babel. The complete, human readable source code, including all build configuration, is published in our public GitHub repository:

https://github.com/DevDiggers-Technologies/buy-now-pay-later-for-woocommerce

To build the assets from source:

1. Run `npm install` to install the build dependencies listed in `package.json`.
2. Run `npm run build` to compile the production assets into `assets/js` and `assets/css`.

The build configuration is in `webpack.config.js` and `babel.config.js`. Two third party libraries used by the admin screens ship unmodified under the MIT licence inside `devdiggers-framework/assets/js/`: Chart.js (https://www.chartjs.org) and Select2 (https://select2.org).

== Changelog ==

= 1.0.0 - 2026-10-21 =
* New: Pay Later payment method for the classic checkout and the Checkout block, with a plan choice and the customer's available credit.
* New: Pay in 4 (first payment at purchase, order held until it is paid) and Pay in 30 days plans, each with its own on/off switch.
* New: Store credit line with one credit limit, manual or automatic approval and manual balance adjustments with a note.
* New: Installment price on product pages, product lists and the cart, updated for variations, plus the [ddwcpp_installments_message] shortcode and a Learn more explainer.
* New: Pay Later section in My Account with available credit, the next payment, every plan's schedule and full history. Customers pay installments with any payment method you accept.
* New: Reminder before every due date, overdue notice the day after, and an option to pause credit for overdue customers.
* New: Refunds, cancellations and failed orders return the credit automatically.
* New: Dashboard with dues, credit used, repayments, repayment rate, due and overdue totals, charts and widgets.
* New: Customers, Installments, Plans and Transactions screens, a setup wizard, HPOS support and nine editable customer and store emails.

== Upgrade Notice ==

= 1.0.0 =
First release of Buy Now Pay Later for WooCommerce.
