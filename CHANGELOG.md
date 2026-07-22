2026-07-22, Split service finalization from general service work, Added a dedicated Service finalize ability gated to the manage tier so technicians can still open services and record count and fill work while no longer being allowed to complete location services close maintenance services or enter amount collected, updated the service detail actions to hide those finalize-only controls for technicians, and added focused feature coverage for technician denial preserved work actions manage-tier finalize access and viewer behavior.











2026-07-22, Allow technicians to create maintenance services only, Broadened the generic service-create ability so technicians can reach the create flow while adding a type-aware policy check that still blocks technician creation of location services, filtered the create-service form and existing Create Service button so technicians only see maintenance-capable options, kept Admin Owner and Manager creation rights unchanged for both service types, and extended account-role coverage for technician maintenance creates tampered location-service rejection unchanged manager and owner behavior and viewer denial.








2026-07-22, Preserve closed maintenance services as finalized records, Tightened service deletion so any maintenance service in the closed state is now treated as an immutable finalized record regardless of creator or admin role, surfaced a specific closed-maintenance protection message on the service detail page and destroy flow, kept the existing no-transactions and creator-or-admin delete rules for all other services intact, and extended service-deletion coverage for blocked closed maintenance deletes allowed closed location deletes allowed non-closed maintenance deletes and the hidden delete UI state.








2026-07-22, Add safe service deletion with creator tracking and password confirmation, Added a nullable created_by_user_id foreign key to services with a backfill from the legacy assigned user, now persist the real creator during service creation without changing assignee behavior, exposed a creator-or-admin-only Delete Service action on the service detail page behind Laravel's password.confirm flow, kept the hard block on deleting services that already have transactions while still removing linked calendar events for valid deletions, and added feature coverage for creator tracking migration backfill password gating authorization button visibility and calendar cleanup.








2026-07-22, Seed new accounts from an editable default product catalog CSV, Extracted the existing CSV import and idempotent updateOrCreate product-loading logic into a reusable ProductCatalogImporter service, pointed the default catalog at a configurable storage-backed path, added queued after-commit default-catalog imports for newly created accounts plus CSV validation tooling, and extended coverage for job dispatch importer idempotency malformed-row handling and validation-only checks.



2026-07-22, Group the Products list by category with accordions, Reworked the account-scoped Products index so search behavior stays unchanged while results now load as a category-sorted collection grouped into Alpine accordion sections with Expand all and Collapse all controls, moved null and blank categories into an explicit Uncategorized bucket, removed the redundant Category column from the inner table, and extended feature coverage for grouping filtering empty states and pagination-free rendering.



2026-07-22, Add financial and inventory audit logging, Added a general tbl_audit_log table plus an AuditLog model and reusable Auditable trait, wired created updated and deleted accountability logging for Service Transaction Purchase PurchaseItem and append-only InventoryLedger records with compact per-field change payloads and nullable account and user support for future system-wide use, and added coverage for event creation null-user contexts no-op updates and changed-field-only payloads.



2026-07-22, Add an account-scoped Audit Log screen, Added a paginated /audit-log index with event and entity filters newest-first sorting compact rendered change summaries System fallback labeling for unauthenticated actions and admin-only navigation visibility, scoped account admins to the current account while leaving the super-admin all-accounts branch feature-gated, and extended feature coverage for account isolation role-based 403 handling filter persistence pagination and system-row rendering.



2026-07-22, Add runtime single-tenant mode without changing account-scoped data modeling, Added a centralized tenancy helper and config-driven multi-versus-single mode switch that keeps account_id on every table and query while auto-pinning current_account_id to one configured account in single mode, hid account-switching and account-creation friction from the shared auth and navigation flows, added an idempotent tenancy:init-single installer command, and covered single-mode auto-selection registration policy UI hiding and multi-tenant regression behavior.



2026-07-22, Add a global super-admin capability with platform-account access and audit logging, Added persisted is_super_admin support on users, console-only grant and revoke commands, a platform-admin route group with a minimal all-accounts index, policy and membership-middleware bypasses that allow cross-account access without AccountUser rows, lightweight tbl_super_admin_audit_log entries for cross-account bypass requests, and feature coverage for access denial bypass auditing platform-route gating and the new Artisan commands.




2026-07-21, Move Location Detail service-type styling onto the visible header button, Reworked the Location Detail services accordion so the stored tbl_services.service_type value now maps directly to button-level service-accordion-button classes and a rendered data-service-type hook, replaced the wrapper-only maintenance background override with button-specific light-blue and dark-mode rules that keep the chevron readable across collapsed expanded hover and focus states, and extended location-detail coverage to verify Service #9 style mapping button attributes non-service accordion isolation and the updated stylesheet contract.



2026-07-21, Color Location Detail service accordions by stored service type, Kept the Location Detail service accordion classifier tied to the persisted tbl_services.service_type value, removed the old location and maintenance color overrides so location_service and unknown service types fall back to the existing gray accordion styling, added a light blue maintenance_service header treatment with a dark-mode variant, and extended location-detail coverage for service-type class mapping accessibility markup non-service accordion isolation and the stylesheet color contract.



2026-07-21, Render Machine List statuses as pill badges, Updated the grouped Machines index so status values now render as rounded pill badges with the required blue Active and green Inactive mappings, normalize casing and surrounding whitespace only at display time, preserve unknown or blank statuses with neutral gray fallback pills, and extend machine-list coverage so the new badge styles do not affect grouping or stored database values.



2026-07-21, Group the Machine List by machine type with accordions, Reworked the account-scoped Machines index so the current paginated result set is ordered by the persisted tbl_machines.type value and grouped into collapsed Alpine accordion sections with accurate counts, kept every nonblank stored type as its own visible accordion label, moved only null or blank type values into a final Uncategorized group, removed the redundant Type column from the nested tables while preserving the existing machine actions and pagination links, and added feature coverage for grouping search filtering query-string pagination and the no-results state.



2026-07-20, Move the Location Sales graph above the Location Summary card, Reordered the Location Detail page so the shared Location Sales graph now appears before the Location Summary card while preserving the reused sales-chart component behavior and updating the location feature coverage to assert the new section order.



2026-07-20, Add a shared Location Sales line graph to the Location Detail page, Extracted the dashboard sales SVG into a reusable sales-chart component backed by the shared sales-chart Alpine state, reused the existing one month three month six month and one year revenue bucket builder for account and location charts, and added a Location Sales card after the summary section that filters finalized calculated service sales by the persisted service-sales location snapshot so historical machine moves do not erase the location's revenue history.



2026-07-20, Add selected-state styling to weekly calendar navigation, Updated the shared weekly calendar selector so Previous Week Current Week and Next Week now derive their selected state from the rendered Sunday-through-Saturday week instead of leaving Current Week visually hard-coded, reused the existing violet selected-button styling with separate keyboard focus treatment, and extended both calendar and dashboard coverage so exactly one week selector stays active after past current and future week navigations.



2026-07-20, Shorten the dashboard Sales yearly x-axis labels, Kept the Sales graph on the existing Alpine SVG renderer but switched its underlying chart labels to ISO date values so the one year view can render compact MM-YY ticks while the one month three month and six month views continue rendering MM-DD bucket-start ticks, preserved the existing tooltip detail and aggregation windows, and extended dashboard coverage so period switching now asserts the renderer-specific x-axis formatter behavior.



2026-07-20, Repair the dashboard Sales SVG axis tick rendering, Reworked the live Alpine SVG sales renderer so it now explicitly generates visible U.S.-dollar y-axis labels and horizontal grid lines from a dynamic five-interval scale, renders real date week and month x-axis tick values with period-specific selection limits, keeps exactly one date-range button visually active through the shared setSalesPeriod path, and updates dashboard coverage to assert the SVG-driven tick and button behavior instead of relying on a non-existent Chart.js implementation.



2026-07-20, Display the dashboard Sales axis in explicit U.S. dollars, Updated the existing Alpine-driven Sales line graph so the visible y-axis label now reads Sales (USD), y-axis ticks render as whole-number U.S. currency values with dollar signs and comma separators, hover tooltips keep two decimal places in U.S. dollars, and dashboard coverage now asserts the USD formatter contract without changing the chart data, layout, or sales aggregation.


2026-07-20, Move the dashboard Sales card ahead of Low Inventory and remove Low Inventory font overrides, Reordered the responsive dashboard grid so the Sales graph now renders first and occupies the left two thirds of the row while the Low Inventory card renders second on the right third and still stacks underneath Sales on smaller screens, removed the Low Inventory card's fixed 10 point typography overrides so it inherits the application's normal text sizing, and updated dashboard coverage to assert the new card order and typography behavior without changing the underlying sales or Main Warehouse inventory queries.


2026-07-20, Rework the dashboard cards into a responsive shared row, Replaced the custom fixed-width dashboard flex wrappers with the existing Tailwind grid so Low Inventory now occupies roughly one third of the dashboard row and Sales uses the remaining two thirds on large screens while both stack full width on smaller screens, preserved the compact 10 pixel low-inventory rows and the existing sales chart behavior, and updated dashboard coverage to reject the old fixed pixel layout rules.


2026-07-20, Add dynamic axis labels to the dashboard sales graph, Extended the existing Alpine-driven Sales line graph so the y-axis now shows Sales and the x-axis title switches between Date Week and Month for the 1 month 3 month 6 month and 1 year views, updated the chart accessibility label to match the active bucket type, and expanded dashboard coverage for the new axis-label metadata without changing the sales aggregation or the Main Warehouse low-inventory card.


2026-07-20, Narrow the Main Warehouse low-inventory dashboard card, Reworked the existing Low Inventory — Main Warehouse card into a compact 280 pixel desktop panel with full-width mobile behavior, reduced the body to 10 pixel product-and-quantity rows only, removed the prior SKU table and footer link without changing the Main Warehouse query or top-10 ordering, and extended dashboard coverage for the new width rules and compact row layout.


2026-07-20, Add an interactive dashboard sales graph, Added a dashboard Sales card that aggregates calculated service-sales revenue into daily weekly and monthly zero-filled periods for the last 1 month 3 months 6 months and 1 year, rendered the graph as an Alpine-driven responsive SVG line chart that switches periods without reloading, preserved the existing Main Warehouse low-inventory card, and extended dashboard coverage for current-account scoping bucket calculations and baseline-row exclusion.


2026-07-20, Add a Main Warehouse low-inventory dashboard card, Reused the canonical warehouse inventory ledger aggregation to show the 10 lowest on-hand products from the current account's exact Main Warehouse on the dashboard, added safe missing and duplicate warehouse states plus a drill-in link to the warehouse inventory page, and extended dashboard coverage so other warehouses other accounts and machine-bin inventory stay excluded from the card.


2026-07-20, Remove the Bins item from sidebar navigation, Removed the visible Bins link from the Route Management sidebar group while leaving the underlying bin routes and management screens intact, kept the remaining Route Management links alphabetized, preserved Transactions under Inventory, and extended sidebar coverage so the navigation no longer points to bins.index even though the page still loads directly.


2026-07-20, Rename sidebar group headings for inventory and settings, Updated the shared sidebar so the top-level Inventory Setup group now displays as Inventory and the top-level Account group now displays as Settings, while preserving the existing child links, top-level order, route behavior, and sidebar navigation coverage.


2026-07-20, Restore Route Management as a top-level sidebar group, Corrected the sidebar hierarchy so Route Management is once again a standalone top-level group positioned immediately after Operations instead of nesting inside Operations, kept Transactions under Inventory Setup, preserved alphabetized child links inside each group, and updated sidebar coverage so route pages expand Route Management without forcing Operations open.


2026-07-19, Add spoilage to the Location Service count workflow, Added a persisted spoilage field to count transactions and finalized service-sales rows, changed the Location Service count form to capture Count and Spoilage per bin with idempotent updates, updated sales reconciliation so units sold equals opening quantity minus final count minus spoilage while closing inventory still uses only final count plus post-count fill, replaced the Sales Breakdown Removals column with Spoilage, and extended transaction and service workflow coverage so count edits preserve spoilage and invalid spoilage blocks completion.


2026-07-19, Reorder the Location Service count inputs and replace repeated helper copy with header tooltips, Moved Spoilage ahead of Count in the machine count table, removed the repeated inline guidance under each field and the machine-level instruction sentence, added accessible header tooltip buttons while preserving the existing quantity and spoilage field names and saved values, and extended feature coverage for the revised layout.


2026-07-19, Add nested machine inventory accordions to the Location Detail page, Replaced the flat Machines table on the location detail screen with nested per-machine accordions, bulk-loaded the latest account-scoped Current Inventory snapshot by bin and product to avoid N+1 transaction queries, displayed bin capacity current inventory available capacity selling price inventory value and snapshot timestamps with AGENT-compliant date and time output, and preserved machine management links plus empty states inside the expanded machine panels.


2026-07-19, Simplify the nested machine inventory tables on the Location Detail page, Removed the Available Capacity and Inventory Value columns from each nested machine inventory table, stopped calculating those display-only values in the location detail controller payload, kept current inventory selling price and snapshot timestamps intact, and updated feature coverage so the machine accordions now assert the final six-column layout.


2026-07-19, Remove machine management buttons from the Location Detail inventory accordions, Removed the Edit Machine Manage Bins and Add Bins controls from the nested machine accordions on the location detail page, preserved the inventory tables and direct View Machine link, and updated feature coverage so the location screen now asserts those management actions are absent while the underlying routes remain available elsewhere.


2026-07-19, Remove inventory additions from persistent service sales, Removed the inventory_additions field from the service-sales schema and model contract, changed reconciliation tests and views so units sold no longer treats additions as part of the sales interval, kept post-count fill tied only to closing Current Inventory, removed the Additions column from the Service Detail machine sales tables, and added count-before-fill regression coverage.


2026-07-19, Standardize Service Detail dates to the AGENT display rules, Audited the Service Detail page against AGENT.md, extended the shared AppDateTime helper with ISO-safe output helpers and explicit display-timezone normalization, updated the service summary and transaction sections to render visible dates as DD-MM-YYYY and visible times as HH:MM:SS with separate date and time lines where both are shown, and added formatter and Service Detail coverage for the rendered output.


2026-07-19, Group service sales breakdown by machine on the service detail page, Updated the Service Detail Sales Breakdown to prepare account-scoped machine sales groups in the controller, render one collapsed machine accordion per machine with baseline and partial totals preserved, remove the redundant Machine column from the inner sales table, and extend feature coverage for grouped machine sales rendering.


2026-07-19, Align recent service-sales and location-detail updates with AGENT standards, Added short why-comments to the new service-sales calculation persistence and reporting code, updated the location detail services and document timestamps to use the shared DD-MM-YYYY and HH:MM:SS display helpers, and kept the baseline-sales reconciliation workflow unchanged.










2026-07-20, Move Route Management into Operations and alphabetize sidebar groups, Reworked the shared sidebar so Route Management now appears as a nested subgroup inside Operations instead of a separate top-level group, kept Transactions under Inventory Setup, alphabetized the child items inside each sidebar group and nested route-management section, preserved the top-level group order and existing route behavior, and added sidebar feature coverage for placement ordering and expanded active states.


2026-07-20, Move Transactions into the Inventory sidebar group, Updated the shared application sidebar so the existing Transactions navigation link now appears under Inventory Setup instead of Operations, expanded the Inventory group automatically on transaction routes, removed the old sidebar placement to keep only one visible Transactions link, and added feature coverage for placement uniqueness and active-state behavior.


2026-07-19, Rename baseline service-sales UI wording, Kept the stored service-sale calculation status value as baseline while updating the public-facing service and location detail interfaces to display Initial Installation, centralized the label mapping in the sales model and reconciliation helper, refreshed baseline-only summary messaging, and updated automated coverage to verify the clearer wording without changing reporting behavior.


2026-07-19, Correct public-facing date formatting, Updated the shared date formatter and remaining public-facing views to use MM-DD-YYYY dates with separate HH:MM:SS time displays per AGENT.md, corrected form placeholders and calendar and inventory detail displays, aligned console-facing scheduled-service output with the same convention, and refreshed automated test expectations to match the approved format.


2026-07-13, Add reusable location contact management, Added account-scoped tbl_contacts and tbl_location_contacts tables for reusable shared contacts, seeded location contact roles into the shared data dictionary, created contact CRUD plus location contact attach and relationship edit workflows, enforced duplicate-role and single-primary-contact rules per location, updated the location detail page with a Contacts card and relationship actions, added a top-level Contacts section in the Account navigation, and kept the legacy tbl_locations contact fields in place for backward compatibility.


2026-07-13, Add route scheduling and ordered route stops, Added scheduled_day on routes plus a new tbl_route_locations pivot table for account-scoped stop ordering, backfilled legacy location route assignments into ordered route stops, seeded route scheduled days into the shared data dictionary, updated route create edit index and detail screens to manage scheduled days and ordered stops, added add remove move-up and move-down route-stop actions with sequential renumbering, and kept legacy location route_id in sync as a nullable primary-route field for backward compatibility.


2026-07-13, Add account-user management for the current account, Added an Owner/Admin-only Account Users workflow for listing adding editing deactivating and removing account memberships, reused tbl_account_users for membership scoping instead of adding account_id to tbl_users, loaded account-user roles and statuses from the shared data dictionary, protected against duplicate memberships and deleting or deactivating the last active owner, added account-user screens and sidebar navigation, normalized legacy owner and viewer membership roles to canonical display values, and kept all membership reads and writes scoped to the selected account.


2026-07-13, Correct unit-cost handling for service count and fill transactions, Added a shared InventoryCostService for account-scoped warehouse average-cost and last-fill lookups, updated fill transactions to persist the current warehouse average unit cost on both tbl_transactions and matching negative service_fill ledger rows in one database transaction, updated count transactions to inherit unit cost from the latest prior fill for the same bin and product with warehouse-average and zero-cost fallbacks, and kept machine and product assignment derived from the persisted bin instead of request input.


2026-07-12, Add inbound purchasing and warehouse inventory ledger tracking, Added posted-only purchase entry with void-by-reversal workflow, created tbl_purchases tbl_purchase_items and append-only tbl_inventory_ledger, seeded purchase statuses and inventory movement types into the shared data dictionary, required services to select a source warehouse, made machine fill transactions consume warehouse inventory at stored average cost, added purchase screens plus warehouse inventory and recent-ledger reporting, and kept all purchase warehouse product service transaction and ledger lookups scoped to the current account.


2026-07-12, Centralize system statuses in the data dictionary, Expanded tbl_data_dictionary with account scope, labels, sort order, active flags, and timestamps, reseeded canonical service, purchase, machine, account, user, and account-user statuses with no Draft purchase state, added a shared DataDictionaryService plus dictionary-backed validation rules, switched machine and service status UI and display labels to load from the dictionary, replaced live auth/account checks with shared status constants, and redirected the active account-membership middleware alias to a writable replacement that uses the centralized status constant path.


2026-07-12, Track which user finalized service closure, Added a nullable closed_by_user_id foreign key on services, updated the Service model with a closedBy relationship and fillable support, stored the authenticated user when amount collected is entered and the service becomes Service Closed, surfaced Closed By on the service detail page and all-services listing, and applied the migration to the database.


2026-07-11, Split service completion from final closure, Expanded the service lifecycle to Awaiting Service, Service Open, Service Completed, and Service Closed, added completed_at tracking and guarded schema support for amount_collected, changed the technician close action to Complete Service, added a dedicated amount-collected entry screen to fully close completed services, updated service detail and services index screens to surface completed services awaiting money entry, and restricted transaction edits and writes to Service Open services only.


2026-07-11, Require the collected amount when closing a service, Added a nullable amount_collected field to the service schema and model, converted service closing into a form that requires a non-negative collected amount, stored that value directly on the service when status changes to Service Closed, and exposed Amount Collected on the service detail page and services index.


2026-07-11, Standardize application date and time formatting, Updated AGENT.md to require DD-MM-YYYY dates and HH:MM:SS times, added a shared AppDateTime formatter and parser, changed service, machine, and transaction views to display dates and times separately in the new format, converted service and machine date inputs to text fields with DD-MM-YYYY parsing, split transaction datetime entry into separate date and time inputs, and kept the database on native date and datetime storage while normalizing values at the controller boundary.


2026-07-11, Simplify the service summary table, Removed the Route, Address, City, and Contact columns from the service detail page Service Summary table so it shows only the core service status and timing fields plus the assigned user.


2026-07-11, Update the service detail subtitle line, Changed the service detail page subtitle to show the location name, city, and route name in the format "Location Name, City - Route Name" beneath the service title.


2026-07-11, Replace the service summary boxes with a table, Updated the service detail page so the Service Summary card uses a compact table with one data row instead of responsive summary boxes, making the layout stable and aligned regardless of viewport width.


2026-07-11, Force the service summary into five columns sooner, Updated the service detail summary card to use a five-column layout from the small breakpoint upward and reduced box padding again so the summary no longer falls back to a two-column layout on the service page width.


2026-07-11, Tighten the service summary breakpoint, Updated the service detail summary card to switch to a five-column layout at the medium breakpoint and slightly reduced spacing again so the summary stays in two rows at narrower desktop widths.


2026-07-11, Compress the service summary card layout, Updated the service detail page so the Service Summary card uses a tighter five-column grid on desktop to keep the summary boxes to two rows and reduced the box padding and corner size for a more compact layout.


2026-07-11, Close service transaction accordions by default, Updated the service detail transactions card so both the date accordion groups and nested transaction type groups start collapsed by default to match the system accordion behavior rule.


2026-07-11, Clarify accordion default behavior in agent instructions, Updated AGENT.md to require all accordions and collapsible sections across the system to default to the closed state unless a user explicitly requests otherwise.


2026-07-11, Reorganize service detail transactions by date and type, Updated the service detail page to load account-scoped transactions in descending transaction time order, grouped them by transaction date and transaction type with nested collapsible sections, added transaction action links within each grouped table, and preserved closed-service edit and delete restrictions.


2026-07-11, Rework the service detail page layout, Moved the Service Summary card into a full-width horizontal summary section across the top of the service detail page and positioned the Machines At This Location panel underneath it to improve scanability and match the rest of the admin layout.


2026-07-11, Redesign the Services index page, Replaced the flat filtered Services table with grouped Pending Services and All Services cards, removed the index filter UI, grouped service rows by location with collapsible sections, standardized pending-service display for legacy lowercase statuses, and preserved account-scoped service workflow actions.


2026-07-21, Fix SQLite-compatible test regressions after service accordion styling, Added a safe fallback for empty route scheduled-day ordering, made service lifecycle backfills and warehouse-column migration steps SQLite-safe, restored model factory support on users, and tightened stale feature-test assertions to match current dashboard and sidebar markup.


2026-07-10, Update the machine detail page summary and bins layout, Changed the machine detail page to use horizontal summary cards, renamed the bins card to Bins, grouped bins into collapsible row sections labeled by row letter, sized row bin cards to fit on a single line, and updated row labels to display as "Row: X".


2026-07-10, Update README to reflect the application's intent, Replaced the default Laravel README with project-specific documentation describing GoldRush as a multi-account vending machine management application, documented the current operational scope, summarized the domain model, and added practical local run instructions.


2026-07-10, Create a data dictionary for system terminology, Added DATA_DICTIONARY.md to define the main account, vending, inventory, service, transaction, and workflow terms used by the application and summarized the key data relationships between them.


2026-07-10, Replace the documentation-only data dictionary with a database lookup table, Added a global tbl_data_dictionary table with name and value pairs, created a DataDictionary model and seeder with initial machine and status terms, wired the seeder into DatabaseSeeder, and removed the markdown-only data dictionary file.


2026-07-10, Build the location-based service workflow, Converted services from machine-based handling to location-based handling, added service screens and routes for create/open/count/fill/close actions, enforced account and location ownership rules, recorded count and fill transactions at the bin level, added service workflow feature tests, and updated shared dictionary values for the new service statuses and type.


2026-07-10, Add service lifecycle and transaction timestamps, Added opened_at and closed_at to services plus transaction_at to transactions, updated service open and close actions to stamp lifecycle times, updated count and fill transactions to record transaction time, and expanded the service detail view to show those lifecycle and transaction timestamps.
