<x-app-layout title="Location Details">
    <style>
        /* Keep the forced summary grid stable on desktop while stacking cleanly on phones. */
        @media (max-width: 767.98px) {
            .forced-location-summary-grid {
                display: block !important;
            }

            .forced-location-summary-grid > div {
                margin-bottom: 1.25rem;
            }
        }

        /* Keep nested machine inventory accordions readable without introducing page-level scrolling. */
        .location-machine-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .machine-inventory-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            width: 100%;
            padding-right: 1rem;
        }

        .machine-inventory-heading-main {
            min-width: 0;
        }

        .machine-inventory-heading-summary {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
            color: rgb(107 114 128);
            font-size: 0.9rem;
        }

        @media (max-width: 767.98px) {
            .machine-inventory-heading {
                align-items: flex-start;
                flex-direction: column;
                gap: 0.4rem;
            }

            .machine-inventory-heading-summary {
                justify-content: flex-start;
            }
        }
    </style>

    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">{{ $location->location_name }}</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $location->routes->pluck('route_name')->join(' · ') ?: ($location->route?->route_name ?? 'No route') }}</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('locations.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Locations</a>
                    <a href="{{ route('calendar-events.create', ['source_type' => 'location', 'source_id' => $location->id]) }}" class="inline-flex items-center rounded-xl border border-violet-300 px-4 py-2 text-sm font-medium text-violet-700 transition hover:bg-violet-50 dark:border-violet-500/40 dark:text-violet-300 dark:hover:bg-violet-500/10">Schedule Event</a>
                    <a href="{{ route('locations.edit', $location) }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Edit Location</a>
                </div>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif

            <x-validation-errors />

            @php
                $serviceStatusClasses = static function (?string $status): string {
                    return match (strtolower(trim((string) $status))) {
                        strtolower(\App\Models\Service::STATUS_AWAITING_SERVICE) => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
                        strtolower(\App\Models\Service::STATUS_SERVICE_OPEN) => 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300',
                        strtolower(\App\Models\Service::STATUS_SERVICE_COMPLETED) => 'bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300',
                        strtolower(\App\Models\Service::STATUS_SERVICE_CLOSED) => 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300',
                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-700/60 dark:text-gray-200',
                    };
                };

                $displayServiceStatus = static function (?string $status) use ($serviceStatusLabels): string {
                    $normalizedStatus = strtolower(trim((string) $status));

                    return $serviceStatusLabels[$normalizedStatus] ?? ($status ?: 'Unknown');
                };
            @endphp

            <x-sales-chart
                chart-id="location-sales-chart"
                title="Location Sales"
                :chart-data="$locationSalesChart"
                empty-message="No calculated sales were recorded for this location during this period."
                accessible-label-prefix="Location sales"
            />

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Location Summary</h2>
                    </div>
                </div>
                <div class="panel-body">
                    {{-- Keep the summary fields locked into the requested two-row desktop grid. --}}
                    <div
                        class="forced-location-summary-grid"
                        style="
                            display: grid !important;
                            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                            grid-template-rows: auto auto !important;
                            column-gap: 2rem !important;
                            row-gap: 1.5rem !important;
                            width: 100% !important;
                            align-items: start !important;
                        "
                    >
                        <div style="grid-column: 1 / 2; min-width: 0;">
                            <div class="text-muted mb-1">Location Name</div>
                            <div class="font-semibold text-gray-800 dark:text-gray-100">
                                {{ $location->location_name ?: '—' }}
                            </div>
                        </div>

                        <div style="grid-column: 2 / 4; min-width: 0;">
                            <div class="text-muted mb-1">Address</div>
                            <div class="font-semibold text-gray-800 dark:text-gray-100">
                                {{ $addressLine ?: 'No address on file.' }}
                            </div>
                        </div>

                        <div style="grid-column: 1 / 2; min-width: 0;">
                            <div class="text-muted mb-1">Primary Contact</div>
                            <div class="font-semibold text-gray-800 dark:text-gray-100">
                                {{ $primaryContactName ?: 'No primary contact assigned.' }}
                            </div>
                        </div>

                        <div style="grid-column: 2 / 3; min-width: 0;">
                            <div class="text-muted mb-1">Primary Contact Phone</div>
                            <div class="font-semibold text-gray-800 dark:text-gray-100">
                                @if ($primaryContactPhone)
                                    <a
                                        href="tel:{{ $primaryContactPhone }}"
                                        class="text-reset no-underline hover:underline"
                                    >
                                        {{ $primaryContactPhone }}
                                    </a>
                                @else
                                    —
                                @endif
                            </div>
                        </div>

                        <div style="grid-column: 3 / 4; min-width: 0;">
                            <div class="text-muted mb-1">Primary Contact Email</div>
                            <div
                                class="font-semibold text-gray-800 dark:text-gray-100"
                                style="overflow-wrap: anywhere;"
                            >
                                @if ($primaryContactEmail)
                                    <a
                                        href="mailto:{{ $primaryContactEmail }}"
                                        class="text-reset no-underline hover:underline"
                                    >
                                        {{ $primaryContactEmail }}
                                    </a>
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div id="locationContactsAccordion" x-data="{ open: false }" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700/60">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left dark:bg-gray-800/80"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-controls="locationContactsCollapse"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-lg font-semibold text-gray-800 dark:text-gray-100">Contacts</span>
                            <span class="inline-flex rounded-full bg-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700/60 dark:text-gray-200">{{ $location->locationContacts->count() }}</span>
                        </div>
                    </div>
                    <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-sm leading-none text-gray-400 transition-transform duration-200" :class="open ? 'rotate-90' : ''" aria-hidden="true">›</span>
                </button>

                <div
                    id="locationContactsCollapse"
                    x-show="open"
                    x-transition.origin.top.duration.200ms
                    class="border-t border-gray-200 bg-white dark:border-gray-700/60 dark:bg-gray-900/30"
                >
                    <div class="space-y-4 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Attach reusable contacts to this location and manage location-specific roles.</p>
                            <div class="flex flex-wrap gap-3">
                                <a href="{{ route('locations.contacts.create', $location) }}#attach-existing" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Attach Existing Contact</a>
                                <a href="{{ route('locations.contacts.create', $location) }}#create-new" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Add Contact</a>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                <thead class="bg-gray-50 dark:bg-gray-800/80">
                                    <tr>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Name</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Role</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Organization</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Title</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Email</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Phone</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Mobile Phone</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Primary</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Notes</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                    @forelse ($location->locationContacts as $locationContact)
                                        @php
                                            $contact = $locationContact->contact;
                                            $roleKey = strtolower(trim((string) $locationContact->contact_role));
                                        @endphp
                                        <tr class="bg-white dark:bg-gray-800">
                                            <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">
                                                @if ($contact)
                                                    <a href="{{ route('contacts.show', $contact) }}" class="hover:text-violet-600 dark:hover:text-violet-300">{{ $contact->display_name }}</a>
                                                @else
                                                    Unknown Contact
                                                @endif
                                            </td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $locationContactRoleLabels[$roleKey] ?? ($locationContact->contact_role ?: '—') }}</td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $contact?->organization ?: '—' }}</td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $contact?->title ?: '—' }}</td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $contact?->email ?: '—' }}</td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $contact?->phone ?: '—' }}</td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $contact?->mobile_phone ?: '—' }}</td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                                @if ($locationContact->is_primary)
                                                    <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-800 dark:bg-green-500/15 dark:text-green-300">Primary</span>
                                                @else
                                                    No
                                                @endif
                                            </td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $locationContact->notes ?: '—' }}</td>
                                            <td class="px-5 py-4">
                                                <div class="flex flex-wrap gap-2">
                                                    <a href="{{ route('locations.contacts.edit', [$location, $locationContact]) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit Relationship</a>
                                                    <form method="POST" action="{{ route('locations.contacts.destroy', [$location, $locationContact]) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="inline-flex items-center rounded-xl border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">Remove From Location</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="bg-white dark:bg-gray-800">
                                            <td colspan="10" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No contacts are attached to this location yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div id="locationDocumentsAccordion" x-data="{ open: false }" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700/60">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left dark:bg-gray-800/80"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-controls="locationDocumentsCollapse"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-lg font-semibold text-gray-800 dark:text-gray-100">Documents</span>
                            <span class="inline-flex rounded-full bg-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700/60 dark:text-gray-200">{{ $location->documents->count() }}</span>
                        </div>
                    </div>
                    <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-sm leading-none text-gray-400 transition-transform duration-200" :class="open ? 'rotate-90' : ''" aria-hidden="true">›</span>
                </button>

                <div
                    id="locationDocumentsCollapse"
                    x-show="open"
                    x-transition.origin.top.duration.200ms
                    class="border-t border-gray-200 bg-white dark:border-gray-700/60 dark:bg-gray-900/30"
                >
                    <div class="space-y-4 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Store private location contracts, insurance records, photos, and other supporting files.</p>
                            @if ($canManageDocuments)
                                <a href="{{ route('locations.documents.create', $location) }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Upload Document</a>
                            @endif
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                <thead class="bg-gray-50 dark:bg-gray-800/80">
                                    <tr>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Title</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Document Type</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Original Filename</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">File Size</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Uploaded By</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Uploaded At</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                    @forelse ($location->documents as $document)
                                        @php
                                            // Normalize document type labels once per row before rendering actions.
                                            $documentTypeKey = strtolower(trim((string) $document->document_type));
                                        @endphp
                                        <tr class="bg-white dark:bg-gray-800">
                                            <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">
                                                <a href="{{ route('locations.documents.show', [$location, $document]) }}" class="hover:text-violet-600 dark:hover:text-violet-300">{{ $document->title ?: $document->original_filename }}</a>
                                            </td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $locationDocumentTypeLabels[$documentTypeKey] ?? ($document->document_type ?: '—') }}</td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $document->original_filename }}</td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $document->file_size ? \Illuminate\Support\Number::fileSize((int) $document->file_size) : '—' }}</td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $document->uploadedBy?->name ?: '—' }}</td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                                <div>{{ \App\Support\AppDateTime::displayDate($document->created_at) }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ \App\Support\AppDateTime::displayTime($document->created_at) }}</div>
                                            </td>
                                            <td class="px-5 py-4">
                                                <div class="flex flex-wrap gap-2">
                                                    <a href="{{ route('locations.documents.download', [$location, $document]) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Download</a>
                                                    @if ($canManageDocuments)
                                                        <form method="POST" action="{{ route('locations.documents.destroy', [$location, $document]) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="inline-flex items-center rounded-xl border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">Delete</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="bg-white dark:bg-gray-800">
                                            <td colspan="7" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No documents have been uploaded for this location yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div id="locationMachinesAccordion" x-data="{ open: false }" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700/60">
                <button
                    id="locationMachinesHeading"
                    type="button"
                    class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left dark:bg-gray-800/80"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-controls="locationMachinesCollapse"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-lg font-semibold text-gray-800 dark:text-gray-100">Machines</span>
                            <span class="inline-flex rounded-full bg-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700/60 dark:text-gray-200">{{ $location->machines->count() }}</span>
                        </div>
                    </div>
                    <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-sm leading-none text-gray-400 transition-transform duration-200" :class="open ? 'rotate-90' : ''" aria-hidden="true">›</span>
                </button>

                <div
                    id="locationMachinesCollapse"
                    aria-labelledby="locationMachinesHeading"
                    x-show="open"
                    x-transition.origin.top.duration.200ms
                    class="border-t border-gray-200 bg-white dark:border-gray-700/60 dark:bg-gray-900/30"
                >
                    <div class="space-y-4 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Review the machines currently assigned to this location and inspect the latest current inventory snapshot for each bin.</div>
                            <a href="{{ route('machines.create', ['location_id' => $location->id]) }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Add Machine</a>
                        </div>

                        @if ($machineInventoryGroups->isEmpty())
                            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700/60 dark:text-gray-400">
                                No machines are assigned to this location.
                            </div>
                        @else
                            <div id="locationMachineItemsAccordion" class="location-machine-list">
                                @foreach ($machineInventoryGroups as $index => $group)
                                    @php
                                        // Use stable numeric IDs so nested machine accordions never collide.
                                        $machine = $group['machine'];
                                        $machineHeadingId = 'location-machine-heading-'.$location->id.'-'.$machine->id;
                                        $machineCollapseId = 'location-machine-collapse-'.$location->id.'-'.$machine->id;
                                    @endphp

                                    <div x-data="{ open: false }" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700/60">
                                        <h3 id="{{ $machineHeadingId }}">
                                            <button
                                                type="button"
                                                class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left dark:bg-gray-800/80"
                                                @click="open = !open"
                                                :aria-expanded="open.toString()"
                                                aria-controls="{{ $machineCollapseId }}"
                                            >
                                                <div class="machine-inventory-heading">
                                                    <div class="machine-inventory-heading-main">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span class="font-semibold text-gray-800 dark:text-gray-100">
                                                                {{ $machine->type ?: 'Machine #'.$machine->id }}
                                                            </span>
                                                            @if ($machine->serial_number)
                                                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                                                    {{ $machine->serial_number }}
                                                                </span>
                                                            @endif
                                                            @if ($machine->status)
                                                                <span class="inline-flex rounded-full bg-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700/60 dark:text-gray-200">
                                                                    {{ ucfirst($machine->status) }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="machine-inventory-heading-summary">
                                                        <span>
                                                            {{ $group['bin_count'] }}
                                                            {{ \Illuminate\Support\Str::plural('bin', $group['bin_count']) }}
                                                        </span>

                                                        <span class="mx-2" aria-hidden="true">•</span>

                                                        @if ($group['snapshot_bin_count'] > 0)
                                                            <span>
                                                                {{ number_format($group['total_current_inventory']) }}
                                                                items
                                                            </span>
                                                        @else
                                                            <span class="text-gray-500 dark:text-gray-400">
                                                                Inventory unavailable
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>

                                                <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-sm leading-none text-gray-400 transition-transform duration-200" :class="open ? 'rotate-90' : ''" aria-hidden="true">›</span>
                                            </button>
                                        </h3>

                                        <div
                                            id="{{ $machineCollapseId }}"
                                            aria-labelledby="{{ $machineHeadingId }}"
                                            x-show="open"
                                            x-transition.origin.top.duration.200ms
                                            class="border-t border-gray-200 bg-white dark:border-gray-700/60 dark:bg-gray-900/30"
                                        >
                                            <div class="space-y-4 p-4">
                                                <div class="flex flex-wrap items-start justify-between gap-4">
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        Review the latest current inventory snapshot for each bin on this machine.
                                                    </div>
                                                    {{-- Keep the direct machine detail link while removing edit and bin-management actions from this page. --}}
                                                    <div class="flex flex-wrap gap-3">
                                                        <a href="{{ route('machines.show', $machine) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">View Machine</a>
                                                    </div>
                                                </div>

                                                @if ($group['bins']->isEmpty())
                                                    <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700/60 dark:text-gray-400">
                                                        No bins are configured for this machine.
                                                    </div>
                                                @else
                                                    <div class="overflow-x-auto">
                                                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                                            <thead class="bg-gray-50 dark:bg-gray-800/80">
                                                                <tr>
                                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Bin</th>
                                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Product</th>
                                                                    <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Capacity</th>
                                                                    <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Current Inventory</th>
                                                                    <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Selling Price</th>
                                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Inventory As Of</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                                                @foreach ($group['bins'] as $binRow)
                                                                    <tr class="bg-white dark:bg-gray-800">
                                                                        <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">
                                                                            {{ $binRow['bin']->bin_code ?: 'Bin #'.$binRow['bin']->id }}
                                                                        </td>
                                                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                                                            {{ $binRow['product']?->product_name ?? 'No product assigned' }}
                                                                        </td>
                                                                        <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                                                            {{ number_format($binRow['capacity']) }}
                                                                        </td>
                                                                        <td class="px-5 py-4 text-right tabular-nums font-semibold text-gray-800 dark:text-gray-100">
                                                                            @if ($binRow['has_inventory_snapshot'])
                                                                                {{ number_format($binRow['current_inventory']) }}
                                                                            @else
                                                                                —
                                                                            @endif
                                                                        </td>
                                                                        <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                                                            @if (! is_null($binRow['selling_price']))
                                                                                {{ \App\Support\Money::format($binRow['selling_price']) }}
                                                                            @else
                                                                                —
                                                                            @endif
                                                                        </td>
                                                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                                                            @if ($binRow['inventory_as_of_iso'])
                                                                                <time datetime="{{ $binRow['inventory_as_of_iso'] }}">
                                                                                    <div>{{ $binRow['inventory_as_of_date'] }}</div>
                                                                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $binRow['inventory_as_of_time'] }}</div>
                                                                                </time>
                                                                            @else
                                                                                —
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                            <tfoot class="bg-gray-50 dark:bg-gray-800/80">
                                                                <tr>
                                                                    <th colspan="3" class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Machine Total</th>
                                                                    <th class="px-5 py-3 text-right tabular-nums font-medium text-gray-800 dark:text-gray-100">
                                                                        @if ($group['snapshot_bin_count'] > 0)
                                                                            {{ number_format($group['total_current_inventory']) }}
                                                                        @else
                                                                            —
                                                                        @endif
                                                                    </th>
                                                                    <th class="px-5 py-3"></th>
                                                                    <th class="px-5 py-3"></th>
                                                                </tr>
                                                            </tfoot>
                                                        </table>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <section id="locationServicesAccordion" class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Services</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Review every service visit created for this location.</p>
                    </div>
                    <a href="{{ route('services.create', ['location_id' => $location->id]) }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Create Service</a>
                </div>
                <div class="panel-body">
                    @if ($location->services->isEmpty())
                        <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700/60 dark:text-gray-400">
                            No services have been created for this location.
                        </div>
                    @else
                        <div x-data="{ openServiceId: null }" class="space-y-3">
                            @foreach ($location->services as $service)
                                @php
                                    // Build the service header once so the accordion stays compact and consistent.
                                    $serviceHeaderDate = \App\Support\AppDateTime::displayDate($service->service_date) ?: 'No service date';
                                    $serviceStatusLabel = $displayServiceStatus($service->status);
                                    $serviceTypeLabel = $serviceTypeLabels[strtolower(trim((string) $service->service_type))] ?? ($service->service_type ?: '—');
                                @endphp

                                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700/60 {{ $service->accordion_color_class }}">
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left dark:bg-gray-800/80"
                                        @click="openServiceId = openServiceId === {{ $service->id }} ? null : {{ $service->id }}"
                                        :aria-expanded="(openServiceId === {{ $service->id }}).toString()"
                                        aria-controls="location-service-{{ $service->id }}"
                                    >
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-medium text-gray-800 dark:text-gray-100">{{ $serviceHeaderDate }}</span>
                                                <span class="text-gray-400" aria-hidden="true">—</span>
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $serviceStatusClasses($service->status) }}">{{ $serviceStatusLabel }}</span>
                                                @if ($service->user)
                                                    <span class="text-sm text-gray-500 dark:text-gray-400">— {{ $service->user->name }}</span>
                                                @endif
                                            </div>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Service #{{ $service->id }}
                                            </div>
                                        </div>
                                        <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-sm leading-none text-gray-400 transition-transform duration-200" :class="openServiceId === {{ $service->id }} ? 'rotate-90' : ''" aria-hidden="true">›</span>
                                    </button>

                                    <div
                                        id="location-service-{{ $service->id }}"
                                        x-show="openServiceId === {{ $service->id }}"
                                        x-transition.origin.top.duration.200ms
                                        class="border-t border-gray-200 bg-white dark:border-gray-700/60 dark:bg-gray-900/30"
                                    >
                                        <div class="overflow-x-auto p-4">
                                            <table class="service-detail-table min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                                <thead class="bg-gray-50 dark:bg-gray-800/80">
                                                    <tr>
                                                        <th class="px-4 py-3 text-left">Service Type</th>
                                                        <th class="px-4 py-3 text-left">Service Date</th>
                                                        <th class="px-4 py-3 text-left">Status</th>
                                                        <th class="px-4 py-3 text-left">Assigned Technician</th>
                                                        <th class="px-4 py-3 text-left">Opened At</th>
                                                        <th class="px-4 py-3 text-left">Completed At</th>
                                                        <th class="px-4 py-3 text-left">Closed At</th>
                                                        <th class="px-4 py-3 text-left">Closed By</th>
                                                        <th class="px-4 py-3 text-right">Sales</th>
                                                        <th class="px-4 py-3 text-right">Amount Collected</th>
                                                        <th class="px-4 py-3 text-right">Difference</th>
                                                        <th class="px-4 py-3 text-left">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                                    <tr class="bg-white dark:bg-gray-800">
                                                        @php
                                                            // Derive one reconciliation state so sales and differences match the stored facts.
                                                            $serviceSalesTotal = isset($service->sales_total) ? (string) $service->sales_total : null;
                                                            $calculatedSalesCount = (int) ($service->calculated_sales_count ?? 0);
                                                            $baselineSalesCount = (int) ($service->baseline_sales_count ?? 0);
                                                            $reconciliationStatus = match (true) {
                                                                $calculatedSalesCount > 0 && $baselineSalesCount === 0 => \App\Models\Service::RECONCILIATION_COMPLETE,
                                                                $calculatedSalesCount > 0 && $baselineSalesCount > 0 => \App\Models\Service::RECONCILIATION_PARTIAL,
                                                                $calculatedSalesCount === 0 && $baselineSalesCount > 0 => \App\Models\Service::RECONCILIATION_BASELINE_ONLY,
                                                                default => \App\Models\Service::RECONCILIATION_NONE,
                                                            };
                                                            $serviceDifference = null;

                                                            if ($reconciliationStatus === \App\Models\Service::RECONCILIATION_COMPLETE && $serviceSalesTotal !== null && $service->amount_collected !== null) {
                                                                $serviceDifference = \App\Support\Money::fromCents(
                                                                    \App\Support\Money::toCents($service->amount_collected)
                                                                    - \App\Support\Money::toCents($serviceSalesTotal)
                                                                );
                                                            }

                                                            $serviceSalesDisplay = match ($reconciliationStatus) {
                                                                \App\Models\Service::RECONCILIATION_COMPLETE => \App\Support\Money::format($serviceSalesTotal),
                                                                \App\Models\Service::RECONCILIATION_PARTIAL => trim(\App\Support\Money::format($serviceSalesTotal).' '.\App\Models\Service::reconciliationStatusLabel($reconciliationStatus)),
                                                                \App\Models\Service::RECONCILIATION_BASELINE_ONLY => \App\Models\Service::reconciliationStatusLabel($reconciliationStatus),
                                                                default => '—',
                                                            };
                                                        @endphp
                                                        <td class="px-4 py-3">{{ $serviceTypeLabel }}</td>
                                                        <td class="px-4 py-3 text-nowrap">{{ \App\Support\AppDateTime::displayDate($service->service_date) }}</td>
                                                        <td class="px-4 py-3 text-nowrap">
                                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $serviceStatusClasses($service->status) }}">{{ $serviceStatusLabel }}</span>
                                                        </td>
                                                        <td class="px-4 py-3">{{ $service->user?->name ?: 'Unassigned' }}</td>
                                                        <td class="px-4 py-3 text-nowrap">
                                                            <div>{{ \App\Support\AppDateTime::displayDate($service->opened_at) }}</div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ \App\Support\AppDateTime::displayTime($service->opened_at) }}</div>
                                                        </td>
                                                        <td class="px-4 py-3 text-nowrap">
                                                            <div>{{ \App\Support\AppDateTime::displayDate($service->completed_at) }}</div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ \App\Support\AppDateTime::displayTime($service->completed_at) }}</div>
                                                        </td>
                                                        <td class="px-4 py-3 text-nowrap">
                                                            <div>{{ \App\Support\AppDateTime::displayDate($service->closed_at) }}</div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ \App\Support\AppDateTime::displayTime($service->closed_at) }}</div>
                                                        </td>
                                                        <td class="px-4 py-3">{{ $service->closedBy?->name ?: '—' }}</td>
                                                        <td class="px-4 py-3 text-right text-nowrap">
                                                            @if ($service->isMaintenanceService())
                                                                N/A
                                                            @elseif ($service->isServiceCompleted() || $service->isServiceClosed())
                                                                {{ $serviceSalesDisplay }}
                                                            @else
                                                                —
                                                            @endif
                                                        </td>
                                                        <td class="px-4 py-3 text-right text-nowrap">
                                                            @if ($service->isMaintenanceService())
                                                                N/A
                                                            @elseif (! is_null($service->amount_collected))
                                                                {{ \App\Support\Money::format($service->amount_collected) }}
                                                            @elseif ($service->isServiceCompleted())
                                                                Pending
                                                            @else
                                                                —
                                                            @endif
                                                        </td>
                                                        <td class="px-4 py-3 text-right text-nowrap">
                                                            @if ($service->isMaintenanceService())
                                                                N/A
                                                            @elseif ($serviceDifference !== null)
                                                                {{ \App\Support\Money::format($serviceDifference) }}
                                                            @else
                                                                —
                                                            @endif
                                                        </td>
                                                        <td class="px-4 py-3 text-nowrap">
                                                            <a href="{{ route('services.show', $service) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">View Service</a>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            @if ($canDeleteLocation)
                <section class="panel border border-red-200 dark:border-red-500/40">
                    <div class="panel-header border-red-200 dark:border-red-500/40">
                        <div>
                            <h2 class="text-lg font-semibold text-red-700 dark:text-red-300">Delete Location</h2>
                        </div>
                    </div>
                    <div class="panel-body space-y-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Permanently delete this location. This action cannot be undone.
                        </p>

                        @if ($errors->has('location'))
                            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-300">
                                {{ $errors->first('location') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('locations.destroy', $location) }}" onsubmit="return confirm('Are you sure you want to delete this location? This action cannot be undone.');">
                            @csrf
                            @method('DELETE')

                            <button type="submit" class="inline-flex items-center rounded-xl border border-red-300 px-4 py-2.5 text-sm font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">
                                Delete Location
                            </button>
                        </form>
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
