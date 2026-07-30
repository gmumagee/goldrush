@php
    $featureSections = [
        [
            'eyebrow' => 'Route operations',
            'title' => 'Keep location work tied to actual route structure.',
            'copy' => 'GoldRush already models routes, route-location ordering, recurring service creation, and calendar visibility. The marketing site should reflect that this is operational software, not a brochure feature list.',
            'items' => [
                'Route lists with ordered stops and schedule-aware service generation',
                'Location views that bring machines, contacts, documents, and service history together',
                'Calendar and reminder surfaces for planning and follow-up',
            ],
        ],
        [
            'eyebrow' => 'Machine and stock visibility',
            'title' => 'See what is deployed, what is in inventory, and what each machine is carrying.',
            'copy' => 'The current app supports machine records, bin assignments, inventory-location workflows, warehouse stock, and transaction history.',
            'items' => [
                'Machine records tied to locations or held in inventory',
                'Per-bin product assignments for machine-level merchandising',
                'Warehouse, purchase, and inventory-ledger history for stock movement accountability',
            ],
        ],
        [
            'eyebrow' => 'Service execution',
            'title' => 'Capture the operational work, not just the schedule.',
            'copy' => 'Service flows, maintenance tracking, machine counting, filling, and transaction capture are already core to the product.',
            'items' => [
                'Location and maintenance service workflows',
                'Machine count and fill screens tied to downstream transactions',
                'Installation events and service-linked operational history',
            ],
        ],
        [
            'eyebrow' => 'Controls and governance',
            'title' => 'Give teams access without giving up control.',
            'copy' => 'The product already has multi-account boundaries, user roles, audit logging, import/export, and super-admin controls for platform operations.',
            'items' => [
                'Role-based access for owners, admins, managers, technicians, and viewers',
                'Audit-log history for sensitive operational changes',
                'Account-level import/export and account backup workflows',
            ],
        ],
    ];
@endphp

<x-marketing-layout
    title="Features"
    description="Explore GoldRush features for route management, machine tracking, inventory control, service workflows, and operational governance."
>
    <section class="marketing-section pb-18 pt-14 sm:pb-24 sm:pt-18">
        <div class="marketing-shell">
            <span class="marketing-pill">Feature overview</span>
            <div class="mt-8 max-w-3xl">
                <h1 class="font-['Space_Grotesk'] text-5xl font-semibold tracking-tight text-gray-950 sm:text-6xl dark:text-white">A vending-operations stack built around the system you already run every day.</h1>
                <p class="mt-6 text-lg leading-8 text-gray-600 dark:text-gray-300">
                    GoldRush is strongest when it is honest about what it does well: route planning, location management, machine visibility, inventory movement, service execution, and auditability for multi-user teams.
                </p>
            </div>
        </div>
    </section>

    <section class="marketing-section pb-24">
        <div class="marketing-shell space-y-8">
            @foreach ($featureSections as $index => $section)
                <article class="marketing-panel overflow-hidden">
                    <div class="grid gap-0 lg:grid-cols-[0.92fr_1.08fr]">
                        <div class="border-b border-gray-200/80 p-8 sm:p-10 lg:border-b-0 lg:border-r dark:border-gray-700/70 {{ $index % 2 === 0 ? 'bg-[linear-gradient(180deg,#fff9ee_0%,#ffffff_100%)] dark:bg-[linear-gradient(180deg,rgba(214,177,90,0.14)_0%,rgba(17,24,39,0.88)_100%)]' : 'bg-[linear-gradient(180deg,#f5f7fb_0%,#ffffff_100%)] dark:bg-[linear-gradient(180deg,rgba(55,65,81,0.65)_0%,rgba(17,24,39,0.88)_100%)]' }}">
                            <p class="text-sm font-semibold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400">{{ $section['eyebrow'] }}</p>
                            <h2 class="mt-5 font-['Space_Grotesk'] text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $section['title'] }}</h2>
                            <p class="mt-5 text-base leading-8 text-gray-600 dark:text-gray-300">{{ $section['copy'] }}</p>
                        </div>
                        <div class="p-8 sm:p-10">
                            <div class="grid gap-4">
                                @foreach ($section['items'] as $item)
                                    <div class="rounded-3xl border border-gray-200/80 bg-white/82 p-5 dark:border-gray-700/70 dark:bg-gray-900/60">
                                        <div class="flex items-start gap-4">
                                            <span class="mt-1 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-950 text-xs font-semibold text-white dark:bg-[linear-gradient(135deg,#d6b15a_0%,#a87918_100%)] dark:text-gray-950">{{ str_pad((string) ($loop->iteration), 2, '0', STR_PAD_LEFT) }}</span>
                                            <p class="text-base leading-7 text-gray-700 dark:text-gray-200">{{ $item }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</x-marketing-layout>
