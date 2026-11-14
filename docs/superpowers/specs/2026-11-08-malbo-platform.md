# MALBO platform: requirements and phases

Source: the technical and financial proposal "تصميم وتطوير منصة MALBO للتجارة الإلكترونية" (Be Found
Online). MALBO is a men's fashion brand. One platform serves two channels from one inventory:

- **B2C**: a storefront for individual buyers.
- **B2B**: a portal for approved merchants with wholesale prices.

## Stack (decided)

The proposal names MySQL, Filament and Livewire. The project is already built on Laravel 13,
nwidart modules, Inertia 3 + Vue 3 and PostgreSQL (see `CLAUDE.md`), and keeps that stack. The
proposal's requirements are what count, not its tool list.

## Requirements, by area

1. **Languages**: Arabic (main), English, Hebrew. Each language has its own direction. Products,
   categories and content are translated in the admin. MALBO supplies the texts.
2. **Products**:
   - Categories, sections and collections.
   - Colors, sizes and variants, each variant with its own SKU and its own stock.
   - Several images per color.
   - Fabric, composition, fit and care details.
   - Model info: height, weight and size worn.
   - Flags for new, best seller and special offer.
3. **Sets and bundles**:
   - A set sold as its own product at a special price.
   - A group of separate products sold as one offer.
   - The stock of a set or bundle comes from its components.
   - "Complete the look" suggestions.
   - Collections managed in the admin.
   - *Added by MALBO (2026-11-12), not in the proposal:* a bundle offer can target buyers (B2C),
     merchants (B2B) or both, with its own price for each channel. Example: shirt + trousers + jacket
     at one offer price.
4. **Search and filters**:
   - Search by name and keywords, with suggestions while typing.
   - Filters: color, size, fit, price and category.
   - Sorting: newest, best selling, price.
   - Available sizes shown on product cards.
   - Related products.
5. **Product page**:
   - Zoom and video.
   - Size guide.
   - "Notify me when available" by phone or email, and the notification itself.
   - Badges: limited quantity, out of stock, new, best seller.
   - Reviews from verified buyers, with photos and moderation in the admin.
6. **Customer accounts (B2C)**:
   - Profile and addresses.
   - Orders with their details.
   - Wishlist and coupons.
   - Guest checkout.
   - Exchange and return requests.
7. **Order tracking** without login, by order number and phone.
8. **Cart and checkout**:
   - Coupons.
   - Delivery cost per zone, and free delivery above a minimum.
   - Cash on delivery as the main method. A cash order starts as "awaiting confirmation" and MALBO
     confirms it before it is prepared.
   - Ready for an online payment gateway.
9. **Abandoned carts**: record them with their value, and send reminders by email or SMS at times
   set in the admin.
10. **Campaign pages**: built in the admin from ready blocks (text, images, banners, product links),
    each with its own slug and publish state.
11. **Shipping**:
    - Zones, each with a cost and an expected delivery time.
    - A free-delivery rule.
    - Shipment records with their status.
    - Ready for courier integrations.
12. **Exchanges and returns**:
    - Requests from the site or the admin.
    - Items returned and items given in exchange.
    - Status, notes and stock update.
13. **B2B merchants**:
    - Join request: shop name, city, phone, commercial register.
    - Manual approval and suspension.
    - Wholesale prices hidden from everyone else.
    - A sales rep per merchant.
14. **Wholesale pricing**:
    - Tiers by merchant level and by order size. The method still has to be agreed with MALBO.
    - The suggested retail price shown next to the wholesale price.
15. **Wholesale cart**:
    - Order by size run, or by the piece where allowed.
    - A minimum order by value or by pieces, set in the admin.
    - Live available quantities.
16. **Pre-orders, reorders and catalog**:
    - Incoming goods with an expected date, which merchants can reserve.
    - Reorder from the order history.
    - A PDF catalog to share on WhatsApp.
17. **Merchant finance**:
    - Status and price tier.
    - Orders, invoices and statements.
    - Payments and bank transfers.
    - Balance due and credit limit.
18. **Admin**:
    - Shared inventory and low-stock alerts.
    - Prices, discounts and merchant tiers.
    - Customers, merchants and sales reps.
    - Orders: statuses, internal notes, invoice and waybill printing.
    - Returns, abandoned carts, reviews, campaign pages and coupons.
    - Roles and permissions.
    - Activity log.
19. **Order statuses**: new, awaiting confirmation, confirmed, preparing, with courier, delivered,
    exchange or return, cancelled.
20. **Reports**:
    - Daily and monthly sales.
    - B2C compared with B2B.
    - Sales by product, category, color and size.
    - Best and slow sellers.
    - Return rate.
    - Merchant performance.
    - Abandoned carts.
    - Conversion and average order value.
21. **Marketing**:
    - Meta Pixel and Conversions API: ViewContent, AddToCart, InitiateCheckout and Purchase, with
      duplicate events removed.
    - Product catalog feed for Facebook and Instagram.
    - WhatsApp button.
    - Instagram content.
22. **Shared inventory**:
    - Stock per product, color and size.
    - Stock goes down on a sale and back up on a cancellation or return.
    - B2C and B2B can never oversell each other.
    - Reservations for pre-orders.
    - Alerts when stock is low.

**Out of scope (phase 2 of the proposal):** "know your size" tool, loyalty points, extra languages,
advanced accounting integration. External services (payment gateways, couriers, SMS, accounting) are
built as internal features plus integration points. The actual connections are priced separately.

## Build phases

Each phase ships working, tested software.

1. **Foundation**:
   - Hebrew.
   - The order record: channel, customer, item snapshots, the statuses above, internal notes.
   - The shared inventory ledger, used by every order path, with low-stock alerts.
2. **Catalog**: product details, images per color, flags, collections, badges, size guide, video.
3. **Shipping zones**, then the **storefront cart and checkout** (guest, cash on delivery with
   confirmation, coupons) and **order tracking**.
4. **Customer accounts**: login, addresses, orders, wishlist, return requests. Then the admin
   returns workflow.
5. **Admin operations**: invoice and waybill PDFs, shipments, a global activity log.
6. **B2B**: merchants and their approval, price tiers, the wholesale cart, merchant finance,
   pre-orders, reorder, PDF catalog.
7. **Search and filters**.
8. **Marketing**:
   - Meta events and the catalog feed.
   - Abandoned carts and reminders.
   - Reviews.
   - Back-in-stock notifications.
   - Bundles (for buyers and for merchants) and "complete the look".
   - Campaign pages.
9. **Reports**.
