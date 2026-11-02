<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Email delivery

Password reset links are sent through the configured SMTP mailer. Set `MAIL_MAILER=smtp`, the SMTP host and port, any required username/password and scheme, and a valid `MAIL_FROM_ADDRESS` in `.env`. For Gmail, use a Google App Password for `MAIL_PASSWORD` and use the authenticated mailbox as the sender address. Set `APP_URL` to the public HTTPS address of the deployed application so links in email point to a reachable site; `localhost` links only work on the machine running the app. After changing environment settings on a server with cached configuration, run `php artisan config:clear`.

## Login and signup

Run `php artisan db:seed` to provision the default administrator account (`admin@warelog.com` / `admin123`). Change the default password before deploying publicly. Drivers sign up at `/register`; each account is assigned the driver role and must be activated by an administrator under **Drivers** before signing in. Admins do not sign up; they use their existing account.

The homepage redirects to the single `/login` page. Admins sign in with their existing account and are sent to the admin dashboard; drivers sign in with an approved account and are sent to their delivery page. Drivers without an account can use the sign-up link on that same page. The driver page lists assigned deliveries, checks for new assignments every 20 seconds, and alerts the driver to refresh the list. Admin and driver sessions use separate cookies (`SESSION_ADMIN_COOKIE` and `SESSION_USER_COOKIE`, with distinct defaults), allowing both roles to stay signed in simultaneously in different tabs in the same browser. Admin pages and Payments are restricted to admins; driver routes redirect admins to the shared login page and admin routes redirect drivers to the same login page. Dashboard metrics update when the page is manually refreshed, while Total Stock Value refreshes automatically as inventory changes.

## Inventory settings and movement history

Apply migrations with `php artisan migrate --force` when deploying. Administrators can edit system, warehouse, currency, date/timezone, low-stock, item-code, decimal-display, theme, and session settings from **Settings**; preferences are stored in the database. Stock-in and stock-out screens show the complete movement history with newest transactions first and older records available through pagination. Movement timestamps are recorded when the transaction is saved. Item-code entry warns about similar codes and rejects duplicates; automatic `ITM-` codes are used when enabled and the code is left blank. Enabled daily SQLite backups run at 02:00 using `php artisan schedule:run`; configure that command to run every minute in the host's scheduler.

Delivery completion is confirmed by scanning the item's existing Inventory QR code in Logistics. The scan matches the inventory item code to its pending dispatch; if multiple pending deliveries exist for that item, select the correct destination before confirming. Logistics does not display a separate delivery QR code. Run `php artisan migrate --force` to apply delivery schema updates, including removal of the obsolete QR token and delivery-photo fields.

## Delivery payments

Logistics dispatches can collect payment by Cash on Delivery (COD) or PayMongo hosted checkout. The payable amount is the item's saved unit price multiplied by the dispatched quantity. Stock-out remains an inventory operation and is not charged. COD is recorded when the delivery QR is scanned; a PayMongo delivery is marked paid and delivered only after the server verifies the checkout session or receives a valid paid webhook. PayMongo online checkout requires the system currency to be PHP.

Configure `PAYMONGO_SECRET_KEY` and `PAYMONGO_WEBHOOK_SECRET` in `.env` using the credentials from your PayMongo account; use test-mode credentials before going live. Set `APP_URL` to the public HTTPS address of the app, and configure the PayMongo webhook endpoint as `https://your-domain/webhooks/paymongo` for the `checkout_session.payment.paid` event. Then run `php artisan config:clear` and `php artisan migrate --force`. Do not commit secret keys. Without valid PayMongo credentials and a reachable HTTPS callback/webhook URL, COD continues to work, but online checkout cannot process payments.

The admin **Cash Wallet** page is an in-system cash record, not a bank account or e-wallet. Its wallet balance includes COD cash only after an admin confirms receiving it. COD collected by drivers is shown separately until remitted. Confirmed PayMongo payments are also shown separately because they settle to the configured PayMongo merchant account. Totals are grouped by the currency recorded for each delivery.
