<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-neutral-950 antialiased">
        <!-- Full-screen Background Image -->
        <div
            class="fixed inset-0 bg-cover bg-left bg-no-repeat"
            style="background-image: url('{{ asset('images/payroll-6.jpg') }}');"
        >
        </div>

        <!-- Content Container -->
        <div class="relative z-10 flex min-h-dvh items-center justify-center px-4 py-12 lg:justify-end lg:px-24">
            <!-- Login Card -->
            <div class="w-full max-w-md">
                <!-- Glass-effect Card -->
                <div class="rounded-2xl bg-white/10 p-8 shadow-2xl ring-1 ring-white/20 backdrop-blur-xl dark:bg-neutral-900/80">
                    <!-- Logo and Header -->
                    <div class="mb-8 text-center">
                        <a href="{{ route('home') }}" class="inline-block" wire:navigate>
                            <img src="{{ asset('images/logo-clab.png') }}" alt="CLAB Logo" class="mx-auto w-16 mb-4">
                        </a>
                        <h1 class="text-3xl font-bold text-white">e-SALARY CLAB</h1>
                        <p class="mt-2 text-sm text-gray-300">Subcontract Labor Management</p>
                    </div>

                    <!-- Form Content -->
                    {{ $slot }}
                </div>

                <!-- Footer Quote -->
                <!-- <div class="mt-8 text-center">
                    <p class="text-sm text-gray-400" style="font-family: 'Albert Sans', sans-serif;">
                        "Empowering Construction Workers with Fair Compensation"
                    </p>
                    <p class="mt-1 text-xs text-gray-500">e-Salary System</p>
                </div> -->
            </div>
        </div>

        @fluxScripts
    </body>
</html>
