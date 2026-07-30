@php
    $tiers = \App\Models\Plan::query()
        ->ordered()
        ->get()
        ->map(function (\App\Models\Plan $plan): array {
            $copy = match ($plan->slug) {
                \App\Models\Plan::FREE_SLUG => [
                    'description' => 'Placeholder entry plan for smaller operators validating the workflow before paid expansion.',
                    'featured' => false,
                    'features' => [
                        'Up to 10 total machines, including inventory machines',
                        'Routes, locations, machines, and warehouse visibility',
                        'Manual upgrade request flow only in this phase',
                    ],
                ],
                \App\Models\Plan::STARTER_SLUG => [
                    'description' => 'Placeholder mid-tier plan for operators growing past the first route footprint.',
                    'featured' => false,
                    'features' => [
                        'Up to 25 total machines, including inventory machines',
                        'Import/export workflows and operational audit trail',
                        'Manual upgrade request flow only in this phase',
                    ],
                ],
                default => [
                    'description' => 'Placeholder growth plan for teams that need unlimited machine count without payment wiring yet.',
                    'featured' => true,
                    'features' => [
                        'Unlimited total machines, including inventory machines',
                        'Multi-user operations with audit-ready accountability',
                        'Manual upgrade request flow only in this phase',
                    ],
                ],
            };

            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => $plan->display_price,
                'period' => '',
                'limit' => $plan->isUnlimited() ? 'Unlimited machines' : sprintf('Up to %d machines', $plan->machine_limit),
                ...$copy,
            ];
        });
    $comparisonRows = [
        ['label' => 'Locations, routes, and machines', 'free' => true, 'starter' => true, 'pro' => true],
        ['label' => 'Warehouse, purchases, and product inventory', 'free' => true, 'starter' => true, 'pro' => true],
        ['label' => 'Machine-count ceiling', 'free' => '10', 'starter' => '25', 'pro' => 'Unlimited'],
        ['label' => 'Upgrade handling in this phase', 'free' => 'Manual', 'starter' => 'Manual', 'pro' => 'Manual'],
    ];
@endphp

<x-marketing-layout
    title="Pricing"
    description="Review GoldRush pricing tiers for a paid vending-management SaaS. Billing and checkout are a separate future integration."
>
    <section class="marketing-section pb-18 pt-14 sm:pb-24 sm:pt-18">
        <div class="marketing-shell text-center">
            <span class="marketing-pill">Pricing</span>
            <h1 class="mx-auto mt-8 max-w-4xl font-['Space_Grotesk'] text-5xl font-semibold tracking-tight text-gray-950 sm:text-6xl dark:text-white">Machine-count plans without billing in phase one.</h1>
            <p class="mx-auto mt-6 max-w-3xl text-lg leading-8 text-gray-600 dark:text-gray-300">
                Plans and placeholder pricing now live in the database so you can edit them without a deploy. Billing and checkout are still out of scope here; every CTA records plan intent only.
            </p>
        </div>
    </section>

    <section class="marketing-section pb-16">
        <div class="marketing-shell grid gap-6 lg:grid-cols-3">
            @foreach ($tiers as $tier)
                <article class="marketing-panel relative p-8 {{ $tier['featured'] ? 'overflow-hidden border-gray-900/90 bg-gray-950 text-white shadow-[0_40px_90px_-45px_rgba(17,24,39,1)] dark:border-[#d6b15a]/70 dark:bg-[linear-gradient(180deg,#1a2234_0%,#101826_65%,#0d1421_100%)]' : '' }}">
                    @if ($tier['featured'])
                        <div class="absolute right-6 top-6 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white/85">Recommended</div>
                    @endif
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] {{ $tier['featured'] ? 'text-white/75' : 'text-gray-500 dark:text-gray-400' }}">{{ $tier['name'] }}</p>
                    <div class="mt-6 flex items-end gap-2">
                        <span class="font-['Space_Grotesk'] text-5xl font-semibold tracking-tight">{{ $tier['price'] }}</span>
                        <span class="pb-2 text-sm {{ $tier['featured'] ? 'text-white/70' : 'text-gray-500 dark:text-gray-400' }}">{{ $tier['period'] }}</span>
                    </div>
                    <p class="mt-3 text-sm font-medium {{ $tier['featured'] ? 'text-white/75' : 'text-gray-500 dark:text-gray-400' }}">{{ $tier['limit'] }}</p>
                    <p class="mt-4 text-sm leading-7 {{ $tier['featured'] ? 'text-white/75' : 'text-gray-600 dark:text-gray-300' }}">{{ $tier['description'] }}</p>
                    <form method="POST" action="{{ route('plan-upgrade-intents.store') }}" class="mt-8">
                        @csrf
                        <input type="hidden" name="plan_id" value="{{ $tier['id'] }}">
                        <input type="hidden" name="source" value="marketing_pricing">
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-full px-5 py-3.5 text-sm font-semibold transition {{ $tier['featured'] ? 'bg-[linear-gradient(135deg,#d6b15a_0%,#a87918_100%)] text-gray-950 hover:brightness-105' : 'bg-gray-950 text-white hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-950' }}">
                            Request {{ $tier['name'] }}
                        </button>
                    </form>

                    <ul class="mt-8 space-y-4 text-sm">
                        @foreach ($tier['features'] as $feature)
                            <li class="flex items-start gap-3 {{ $tier['featured'] ? 'text-white/85' : 'text-gray-700 dark:text-gray-200' }}">
                                <span class="mt-1 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full {{ $tier['featured'] ? 'bg-white/12 text-white' : 'bg-[linear-gradient(135deg,#d6b15a_0%,#a87918_100%)] text-gray-950' }}">✓</span>
                                <span>{{ $feature }}</span>
                            </li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    </section>

    <section class="marketing-section pb-24">
        <div class="marketing-shell marketing-panel overflow-hidden">
            <div class="border-b border-gray-200/80 px-8 py-8 dark:border-gray-700/70 sm:px-10">
                <h2 class="font-['Space_Grotesk'] text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">Feature comparison</h2>
                <p class="mt-3 text-base leading-7 text-gray-600 dark:text-gray-300">This phase blocks machine additions at the assigned plan limit instead of charging overages.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-gray-50/80 dark:bg-gray-900/60">
                        <tr>
                            <th class="px-8 py-4 font-semibold text-gray-500 dark:text-gray-400">Capability</th>
                            <th class="px-6 py-4 font-semibold text-gray-500 dark:text-gray-400">Free</th>
                            <th class="px-6 py-4 font-semibold text-gray-500 dark:text-gray-400">Starter</th>
                            <th class="px-6 py-4 font-semibold text-gray-500 dark:text-gray-400">Pro</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($comparisonRows as $row)
                            <tr class="border-t border-gray-200/80 dark:border-gray-700/70">
                                <td class="px-8 py-5 text-gray-700 dark:text-gray-200">{{ $row['label'] }}</td>
                                @foreach (['free', 'starter', 'pro'] as $column)
                                    <td class="px-6 py-5">
                                        @if (is_bool($row[$column]))
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full {{ $row[$column] ? 'bg-[linear-gradient(135deg,#d6b15a_0%,#a87918_100%)] text-gray-950' : 'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-300' }}">
                                                {{ $row[$column] ? '✓' : '—' }}
                                            </span>
                                        @else
                                            <span class="text-gray-700 dark:text-gray-200">{{ $row[$column] }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</x-marketing-layout>
