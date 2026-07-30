@php
    $signupEnabled = config('security.allow_self_registration', false) && \App\Support\Tenancy::isMulti();
    $ctaHref = $signupEnabled ? route('register') : '#contact';
    $ctaLabel = $signupEnabled ? 'Start your workspace' : 'Request access';
    $storyBlocks = [
        [
            'title' => 'Why this product exists',
            'copy' => 'GoldRush is positioned for operators who have outgrown shared spreadsheets and disconnected notes. The product already models the moving parts that matter: routes, locations, machines, products, warehouse inventory, service work, and audit history.',
        ],
        [
            'title' => 'What to customize before launch',
            'copy' => 'Replace this copy with your real company story, implementation process, customer proof, and support commitment. The structure is ready for launch-quality messaging, but the narrative should be your own.',
        ],
    ];
@endphp

<x-marketing-layout
    title="About & Contact"
    description="Learn what GoldRush is for and how prospects can request access or contact your team."
>
    <section class="marketing-section pb-18 pt-14 sm:pb-24 sm:pt-18">
        <div class="marketing-shell grid gap-10 lg:grid-cols-[0.95fr_1.05fr] lg:items-start">
            <div>
                <span class="marketing-pill">About GoldRush</span>
                <h1 class="mt-8 font-['Space_Grotesk'] text-5xl font-semibold tracking-tight text-gray-950 sm:text-6xl dark:text-white">Position the product like a serious operations system, not a side project.</h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-gray-600 dark:text-gray-300">
                    These sections are structured for a real SaaS launch: product rationale, implementation framing, and a clean contact path for prospects who need a conversation before they buy.
                </p>
                <a href="{{ $ctaHref }}" class="mt-10 inline-flex items-center justify-center rounded-full bg-gray-950 px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-gray-800 dark:bg-[linear-gradient(135deg,#d6b15a_0%,#a87918_100%)] dark:text-gray-950">
                    {{ $ctaLabel }}
                </a>
            </div>

            <div class="space-y-6">
                @foreach ($storyBlocks as $block)
                    <article class="marketing-panel p-8">
                        <h2 class="font-['Space_Grotesk'] text-2xl font-semibold text-gray-950 dark:text-white">{{ $block['title'] }}</h2>
                        <p class="mt-4 text-base leading-8 text-gray-600 dark:text-gray-300">{{ $block['copy'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="contact" class="marketing-section pb-24">
        <div class="marketing-shell marketing-panel overflow-hidden">
            <div class="grid gap-0 lg:grid-cols-[0.92fr_1.08fr]">
                <div class="border-b border-gray-200/80 bg-[linear-gradient(180deg,#fff8ea_0%,#ffffff_100%)] p-8 sm:p-10 lg:border-b-0 lg:border-r dark:border-gray-700/70 dark:bg-[linear-gradient(180deg,rgba(214,177,90,0.14)_0%,rgba(17,24,39,0.88)_100%)]">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400">Contact</p>
                    <h2 class="mt-5 font-['Space_Grotesk'] text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">Use a simple request-access path first.</h2>
                    <p class="mt-5 text-base leading-8 text-gray-600 dark:text-gray-300">
                        This v1 keeps contact intentionally light: a direct email call to action and editable company contact details. If you later want a full inbound form delivered through your production mailer, that can be added as a separate pass.
                    </p>
                </div>
                <div class="p-8 sm:p-10">
                    <div class="grid gap-5">
                        <div class="rounded-3xl border border-gray-200/80 bg-white/80 p-6 dark:border-gray-700/70 dark:bg-gray-900/60">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Primary contact</p>
                            <a href="mailto:hello@goldrush.example?subject=GoldRush%20Inquiry" class="mt-3 block font-['Space_Grotesk'] text-2xl font-semibold text-gray-950 underline decoration-gray-300 underline-offset-4 dark:text-white">
                                hello@goldrush.example
                            </a>
                            <p class="mt-3 text-sm leading-7 text-gray-600 dark:text-gray-300">Placeholder mailbox for launch. Replace with your production sales or support address.</p>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="rounded-3xl border border-gray-200/80 bg-gray-50/80 p-6 dark:border-gray-700/70 dark:bg-gray-800/70">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Response model</p>
                                <p class="mt-3 text-base font-semibold text-gray-950 dark:text-white">Editable placeholder</p>
                                <p class="mt-2 text-sm leading-7 text-gray-600 dark:text-gray-300">Use this spot for your actual SLA, onboarding cadence, or demo process once those are finalized.</p>
                            </div>
                            <div class="rounded-3xl border border-gray-200/80 bg-gray-50/80 p-6 dark:border-gray-700/70 dark:bg-gray-800/70">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Commercial flow</p>
                                <p class="mt-3 text-base font-semibold text-gray-950 dark:text-white">{{ $signupEnabled ? 'Self-registration is currently open.' : 'Request-access path is currently the safer CTA.' }}</p>
                                <p class="mt-2 text-sm leading-7 text-gray-600 dark:text-gray-300">This message reflects the app’s actual registration gating so the site never promises a signup path that is disabled.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-marketing-layout>
