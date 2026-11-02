# WareLog ERP – warehouse upgrade

## Apply
1. Copy these files over your project (the zip keeps the same folder layout).
2. `php artisan migrate`   (adds tracking fields, delivery_locations, tracking_events, user phone/vehicle/is_active)
3. `php artisan test`
4. Open **/team** as admin to approve driver sign-ups, then dispatch from **/deliveries**.

## Roles
- **Admin** uses the existing account to manage everything: inventory, Stock In / Stock Out (/inventory/stock-in, /inventory/stock-out), QR labels, dispatch + driver assignment, live tracking, reports, cash wallet, settings, and driver accounts.
- **Driver** accounts are created by signing up at /register and remain inactive until an admin approves them under /team. Drivers only deliver: My Deliveries -> trip -> QR scan + photo + GPS proof. No stock, reports or dispatch access.

## Flow
- Admin: Inventory (QR per item, Receive stock, print labels) -> Deliveries (dispatch + assign driver) -> Live Tracking (map, item trace, activity feed) -> Drivers.
- Driver: sign up -> wait for admin approval -> My Deliveries (assigned orders are listed with an alert) -> Start trip (live GPS) -> Scan item QR -> Photo -> GPS -> Confirm.
- COD is recorded on confirm; PayMongo deliveries stay "Awaiting payment" until PayMongo confirms.

## Phones need HTTPS
Camera QR scanning and GPS only work on HTTPS (or localhost). On a LAN `http://192.168.x.x` the browser blocks them.
Use a tunnel (e.g. cloudflared / ngrok) or real HTTPS for testing on phones.
Meanwhile "Scan from photo" and typing the code work over HTTP, and Settings -> "Require GPS location on proof" can be turned off for testing.

## Realtime
Drivers send GPS every ~6s; the admin map polls every 4s. Works on `php artisan serve` with no websocket server.
To go push-based later, add Laravel Reverb and broadcast TrackingEvent.

## Uploads
Proof photos are compressed in the browser (~1280px JPEG) and saved privately in storage/app/private/delivery-proofs,
served only to the admin and the assigned driver.
