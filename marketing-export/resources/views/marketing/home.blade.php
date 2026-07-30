@php
    $signupEnabled = config('security.allow_self_registration', false) && \App\Support\Tenancy::isMulti();
    $primaryCtaHref = $signupEnabled ? route('register') : route('marketing.about').'#contact';
    $primaryCtaLabel = 'Get Started';
    $secondaryCtaHref = route('marketing.features');
    $secondaryCtaLabel = 'Explore Features';
    $featureHighlights = [
        [
            'title' => 'Route and location control',
            'copy' => 'Organize routes, attach locations in service order, and keep recurring work visible before route day becomes scramble day.',
        ],
        [
            'title' => 'Machine and bin visibility',
            'copy' => 'Track deployed machines, inventory-held machines, and product-by-bin assignments without relying on disconnected spreadsheets.',
        ],
        [
            'title' => 'Warehouse and product tracking',
            'copy' => 'Keep products, vendors, purchases, and warehouse-ledger movements tied together so replenishment decisions are grounded in actual stock.',
        ],
        [
            'title' => 'Service and accountability trails',
            'copy' => 'Run location services, maintenance work, transactions, and audit logging in one account-scoped system with role-aware access.',
        ],
    ];
    $proofPlaceholders = [
        'Customer logos placeholder',
        'Implementation timeline placeholder',
        'Support promise placeholder',
    ];
    $operationsSignals = [
        ['label' => 'Open-source', 'value' => 'Download the latest version and run it yourself, or let GoldRushVMS host it for you.'],
        ['label' => 'Built by operators', 'value' => 'Designed by former vending operators with over a decade of hands-on experience.'],
    ];
@endphp

<x-marketing-layout
    title="Community-driven vending management"
    description="GoldRush is a community-developed, open-source vending management system for routes, machines, inventory, and service."
>
    <section class="marketing-section pb-20 pt-12 sm:pb-24 sm:pt-16">
        <div class="marketing-shell grid gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
            <div>
                <span class="marketing-pill">COMMUNITY-DRIVEN VENDING MANAGEMENT</span>
                <h1 class="mt-8 max-w-3xl font-['Space_Grotesk'] text-5xl font-semibold tracking-tight text-gray-950 sm:text-6xl dark:text-white">
                    Keep more of what you earn.
                </h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-gray-600 dark:text-gray-300">
                    GoldRush is a community-developed, open-source vending management system built to help small and mid-sized operators run routes, machines, inventory, and service — all in one place. It's open-source and free for you to host yourself; GoldRushVMS only charges for hosting and maintenance.
                </p>

                <div class="mt-10 flex flex-col gap-4 sm:flex-row">
                    <a href="{{ $primaryCtaHref }}" class="inline-flex items-center justify-center rounded-full bg-gray-950 px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-gray-800 dark:bg-[linear-gradient(135deg,#d6b15a_0%,#a87918_100%)] dark:text-gray-950">
                        {{ $primaryCtaLabel }}
                    </a>
                    <a href="{{ $secondaryCtaHref }}" class="inline-flex items-center justify-center rounded-full border border-gray-300/80 px-6 py-3.5 text-sm font-semibold text-gray-700 transition hover:border-gray-400 hover:bg-white dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-800">
                        {{ $secondaryCtaLabel }}
                    </a>
                </div>

                <div class="mt-12 grid gap-4 sm:grid-cols-2">
                    @foreach ($operationsSignals as $signal)
                        <div class="rounded-3xl border border-gray-200/70 bg-white/75 p-5 shadow-[0_20px_60px_-42px_rgba(17,24,39,0.35)] backdrop-blur dark:border-gray-700/70 dark:bg-gray-800/75">
                            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ $signal['label'] }}</p>
                            <p class="mt-3 text-sm leading-7 text-gray-700 dark:text-gray-200">{{ $signal['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="marketing-grid marketing-panel overflow-hidden p-6 sm:p-8">
                <div class="rounded-[1.5rem] border border-gray-200/80 bg-white/90 p-6 dark:border-gray-700/70 dark:bg-gray-900/80">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Operations board</p>
                            <h2 class="mt-2 font-['Space_Grotesk'] text-2xl font-semibold text-gray-950 dark:text-white">What teams actually need at 6 a.m.</h2>
                        </div>
                        <div class="rounded-2xl bg-[linear-gradient(135deg,#d6b15a_0%,#a87918_100%)] px-3 py-2 text-xs font-semibold text-gray-950">
                            Live app workflow
                        </div>
                    </div>

                    <div class="mt-8 grid gap-4">
                        <div class="rounded-3xl bg-gray-950 p-5 text-white shadow-[0_30px_70px_-45px_rgba(17,24,39,1)]">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.18em] text-gray-300">Daily route view</p>
                                    <p class="mt-3 text-lg font-semibold">See which locations are due, who is assigned, and which machines need attention.</p>
                                </div>
                                <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-white/80">Routes + services</span>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-3xl border border-gray-200/80 bg-[linear-gradient(180deg,#fff8ea_0%,#ffffff_100%)] p-5 dark:border-gray-700/70 dark:bg-[linear-gradient(180deg,rgba(214,177,90,0.12)_0%,rgba(17,24,39,0.92)_100%)]">
                                <p class="text-xs uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Inventory flow</p>
                                <p class="mt-3 text-base font-semibold text-gray-950 dark:text-white">Purchases, warehouse ledger, and machine stock stay connected.</p>
                            </div>
                            <div class="rounded-3xl border border-gray-200/80 bg-gray-50 p-5 dark:border-gray-700/70 dark:bg-gray-800/90">
                                <p class="text-xs uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Governance</p>
                                <p class="mt-3 text-base font-semibold text-gray-950 dark:text-white">Audit history and role-based controls keep operational changes traceable.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="marketing-section py-20">
        <div class="marketing-shell">
            <div class="max-w-2xl">
                <span class="marketing-pill">Why operators buy systems like this</span>
                <h2 class="mt-6 font-['Space_Grotesk'] text-4xl font-semibold tracking-tight text-gray-950 dark:text-white">Built around the real work: routes, machines, stock, and service history.</h2>
            </div>

            <div class="mt-12 grid gap-6 lg:grid-cols-2">
                @foreach ($featureHighlights as $feature)
                    <article class="marketing-panel p-8">
                        <h3 class="font-['Space_Grotesk'] text-2xl font-semibold text-gray-950 dark:text-white">{{ $feature['title'] }}</h3>
                        <p class="mt-4 text-base leading-8 text-gray-600 dark:text-gray-300">{{ $feature['copy'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="marketing-section py-20">
        <div class="marketing-shell marketing-panel overflow-hidden p-8 sm:p-10">
            <div class="grid gap-10 lg:grid-cols-[1fr_1.1fr] lg:items-center">
                <div>
                    <span class="marketing-pill">Trust section placeholder</span>
                    <h2 class="mt-6 font-['Space_Grotesk'] text-4xl font-semibold tracking-tight text-gray-950 dark:text-white">Add your real proof here when you have it.</h2>
                    <p class="mt-4 max-w-xl text-base leading-8 text-gray-600 dark:text-gray-300">
                        This area is intentionally structured for future customer logos, launch metrics, operator testimonials, or implementation snapshots. Nothing here pretends to be real social proof yet.
                    </p>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach ($proofPlaceholders as $placeholder)
                        <div class="rounded-3xl border border-dashed border-gray-300 bg-gray-50/80 p-6 text-center text-sm font-medium text-gray-500 dark:border-gray-600 dark:bg-gray-900/60 dark:text-gray-300">
                            {{ $placeholder }}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="marketing-section pb-24 pt-10">
        <div class="marketing-shell rounded-[2rem] bg-gray-950 px-8 py-12 text-white shadow-[0_40px_90px_-45px_rgba(17,24,39,1)] sm:px-12">
            <div class="grid gap-10 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
                <div>
                    <span class="inline-flex rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-white/80">Ready when you are</span>
                    <h2 class="mt-6 font-['Space_Grotesk'] text-4xl font-semibold tracking-tight">Run GoldRush yourself or let GoldRushVMS host it for you.</h2>
                    <p class="mt-4 max-w-2xl text-base leading-8 text-gray-300">
                        GoldRush is open-source and free to self-host. If you want managed hosting and maintenance, use the pricing and contact flows to start that conversation.
                    </p>
                </div>
                <div class="flex flex-col gap-4 sm:flex-row lg:flex-col">
                    <a href="{{ $primaryCtaHref }}" class="inline-flex items-center justify-center rounded-full bg-white px-6 py-3.5 text-sm font-semibold text-gray-950 transition hover:bg-gray-100">
                        {{ $primaryCtaLabel }}
                    </a>
                    <a href="{{ $secondaryCtaHref }}" class="inline-flex items-center justify-center rounded-full border border-white/20 px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-white/10">
                        {{ $secondaryCtaLabel }}
                    </a>
                </div>
            </div>
        </div>
    </section>
</x-marketing-layout>
