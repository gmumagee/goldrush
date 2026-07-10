# GoldRush

GoldRush is a multi-account vending machine management application for operators who need to organize field inventory, machine placement, and service-related stock movement from a single admin interface.

## Application Intent

The application is built to help a vending business answer a few practical questions:

- Which accounts does a user manage?
- Which routes and customer locations belong to that account?
- Which machines are installed at those locations?
- How are machine bins arranged by row, and what product is assigned to each bin?
- Which products, vendors, and warehouses support machine stocking?
- Which service and transaction records describe inventory movement over time?

In its current form, GoldRush already provides the account-aware structure for those workflows and implements the core management screens for the operational entities that sit underneath them.

## Current Functional Scope

The codebase currently supports these flows:

- User registration, login, logout, and account selection
- Account-scoped data isolation through session-based account context
- Dashboard metrics for machines, locations, products, warehouses, bins, services, transactions, vendors, and routes
- CRUD-style creation and listing for routes, locations, machines, products, warehouses, and vendors
- Machine detail pages with summary data and row-based bin layout inspection
- Bin row creation for machines using row prefixes such as `A1`, `A2`, `B1`, and `B2`

## Domain Model

GoldRush is centered around the following business objects:

- `Account`: The tenant boundary for all operational data
- `User`: Authenticated operators linked to one or more accounts
- `VendingRoute`: A route used to group service locations
- `Location`: A customer site on a route where machines are installed
- `Machine`: A vending machine assigned to a location
- `Bin`: A slot within a machine, identified by a row-and-position code such as `A1`
- `Product`: A saleable item that can be assigned to bins
- `Vendor`: The supplier associated with products
- `Warehouse`: A storage location used in inventory movement records
- `Service`: A service event performed against a machine
- `Transaction`: Inventory or financial movement tied to services, bins, products, and optionally warehouses

## Current Product Shape

GoldRush is not just a generic inventory tracker. The current schema and UI are specifically shaped around vending operations:

- Machines belong to locations, and locations belong to routes
- Bins belong to machines and are organized by row letters
- Inventory on the machine detail page is derived from transactions per bin
- Products, vendors, and warehouses support downstream stocking workflows
- Services and transactions are modeled in the database even where the UI is still lighter than the rest of the platform

That makes the present application best described as a vending operations admin system with a strong machine-and-bin management foundation.

## Tech Stack

- PHP 8.3
- Laravel 13
- Blade templates
- Tailwind CSS 4
- Vite
- Alpine.js
- Linux-friendly deployment target with relational database backing

## Running The Application

1. Install PHP, Composer, Node.js, and a supported database for your environment.
2. Copy `.env.example` to `.env` if needed.
3. Run the project setup script:

```bash
composer run setup
```

4. Start the local development stack:

```bash
composer run dev
```

This starts the Laravel application server, queue listener, log tailing, and Vite development server together.

If you only need to run the application without the full development stack, you can use:

```bash
php artisan serve
```

## Database Notes

The project includes the vending management schema in `database/migrations/2026_07_09_225448_create_vending_mt_schema.php`.

The schema is multi-account from the start. Most tables carry `account_id`, and the web layer enforces an active selected account before a user can access the operational screens.

## Major Screens

- `/dashboard`
- `/machines`
- `/machines/{machine}`
- `/machines/{machine}/bins/create`
- `/products`
- `/locations`
- `/warehouses`
- `/vendors`
- `/routes`

## Notes On Current Scope

- The machine and bin experience is one of the most complete operational flows in the current UI.
- Services and transactions are already represented in models and dashboard metrics, but the CRUD surface for those areas is not yet as complete as machines, locations, and products.
- The application uses custom authentication and account membership logic rather than Laravel Breeze or Jetstream scaffolding.
