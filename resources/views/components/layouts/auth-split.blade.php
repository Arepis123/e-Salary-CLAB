<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-[65%_35%] lg:px-0">
            <!-- Left Side - Background Image Carousel -->
            <div
                class="bg-muted relative hidden h-full flex-col p-10 text-white lg:flex dark:border-e dark:border-neutral-800"
                x-data="{
                    currentSlide: 0,
                    slides: [
                        {
                            image: '{{ asset('images/payroll.jpg') }}',
                            quote: 'Simplifying Payroll Management',
                            author: 'e-Salary System'
                        },
                        {
                            image: '{{ asset('images/payroll-2.jpg') }}',
                            quote: 'Efficient and Accurate Salary Processing',
                            author: 'e-Salary System'
                        },
                        {
                            image: '{{ asset('images/payroll-3.jpg') }}',
                            quote: 'Empowering Construction Workers with Fair Compensation',
                            author: 'e-Salary System'
                        }
                    ],
                    autoplay: null,
                    init() {
                        this.startAutoplay();
                    },
                    startAutoplay() {
                        this.autoplay = setInterval(() => {
                            this.next();
                        }, 5000);
                    },
                    stopAutoplay() {
                        clearInterval(this.autoplay);
                    },
                    next() {
                        this.currentSlide = (this.currentSlide + 1) % this.slides.length;
                    },
                    prev() {
                        this.currentSlide = (this.currentSlide - 1 + this.slides.length) % this.slides.length;
                    }
                }"
            >
                <!-- Background Images -->
                <template x-for="(slide, index) in slides" :key="index">
                    <div
                        class="absolute inset-0 bg-cover bg-center bg-no-repeat transition-opacity duration-2000"
                        :style="`background-image: url('${slide.image}'); opacity: ${currentSlide === index ? 1 : 0};`"
                    >
                        <!-- Dark overlay for better text readability -->
                        <div class="absolute inset-0 bg-black/50"></div>
                    </div>
                </template>

                <!-- Logo and App Name -->
                <a href="{{ route('home') }}" class="relative z-20 inline-flex items-center text-lg font-medium" wire:navigate>
                    <div class="bg-white/30 backdrop-blur-sm rounded-lg px-2 py-2 shadow-lg">
                        <img src="{{ asset('images/company-logo.png') }}" alt="{{ config('app.name') }}" class="h-12 w-auto">
                    </div>
                </a>

                <!-- Welcome Message or Quote -->
                <div class="relative z-20 mt-auto">
                    <template x-for="(slide, index) in slides" :key="index">
                        <blockquote
                            class="space-y-2 transition-opacity duration-2000 absolute inset-x-0 bottom-0"
                            style="font-family: 'Albert Sans', sans-serif;"
                            :style="`opacity: ${currentSlide === index ? 1 : 0};`"
                            x-show="currentSlide === index"
                        >
                            <flux:heading size="xl" class="tracking-normal" x-text="`&ldquo;${slide.quote}&rdquo;`"></flux:heading>
                            <footer><flux:heading x-text="slide.author"></flux:heading></footer>
                        </blockquote>
                    </template>
                </div>

                <!-- Carousel Navigation Dots -->
                <div class="relative z-20 flex justify-center gap-2 mt-4">
                    <template x-for="(slide, index) in slides" :key="index">
                        <button
                            @click="currentSlide = index; stopAutoplay(); startAutoplay();"
                            class="h-2 rounded-full transition-all"
                            :class="currentSlide === index ? 'w-8 bg-white' : 'w-2 bg-white/50'"
                            :aria-label="`Go to slide ${index + 1}`"
                        ></button>
                    </template>
                </div>
            </div>

            <!-- Right Side - Login Form -->
            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <div class="text-center mb-8">
                        <img src="{{ asset('images/logo-clab.png') }}" alt="CLAB Logo" class="mx-auto w-12 mb-3">
                        <h1 class="text-3xl font-bold">e-SALARY CLAB</h1>
                        <p class="text-gray-600 dark:text-gray-300 text-sm">Subcontract Labor Management</p>
                    </div>
                    <!-- Mobile Logo (shown on small screens) -->
                    <a href="{{ route('home') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>                        
                        <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                    </a>
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
