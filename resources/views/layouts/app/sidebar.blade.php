<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        {{-- Primary application navigation with mobile-collapsible behavior. --}}
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="document-text" :href="route('documents.index')" :current="request()->routeIs('documents.*')" wire:navigate>
                        {{ __('Documents') }}
                    </flux:sidebar.item>
                    @php($primaryOrganization = auth()->user()->organizations()->first())
                    @if ($primaryOrganization)
                        <flux:sidebar.item
                            icon="users"
                            :href="route('organizations.members.index', $primaryOrganization)"
                            :current="request()->routeIs('organizations.members.*')"
                            wire:navigate
                        >
                            {{ __('Members') }}
                        </flux:sidebar.item>
                        @can('manageWorkflowTemplates', $primaryOrganization)
                            <flux:sidebar.item
                                icon="list-bullet"
                                :href="route('organizations.workflow-templates.index', $primaryOrganization)"
                                :current="request()->routeIs('organizations.workflow-templates.*')"
                                wire:navigate
                            >
                                {{ __('Workflow Templates') }}
                            </flux:sidebar.item>
                        @endcan
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        {{-- Mobile user menu and quick account actions. --}}
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        <div class="sticky top-0 z-20 border-b border-zinc-200 bg-white/95 px-4 py-2 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
            <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center gap-2">
                <a href="{{ route('project-docs.show', 'documentation') }}">
                    <flux:button size="sm" variant="ghost">{{ __('Documentation') }}</flux:button>
                </a>
                <a href="{{ route('project-docs.show', 'changelog') }}">
                    <flux:button size="sm" variant="ghost">{{ __('Changelog') }}</flux:button>
                </a>
                <a href="{{ route('project-docs.show', 'what-i-learn') }}">
                    <flux:button size="sm" variant="ghost">{{ __('What I Learned') }}</flux:button>
                </a>
                <a href="https://github.com/sztokula" target="_blank" rel="noopener noreferrer">
                    <flux:button size="sm" variant="ghost">{{ __('GitHub') }}</flux:button>
                </a>
            </div>
        </div>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
