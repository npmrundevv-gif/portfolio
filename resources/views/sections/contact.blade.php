@php
    $rawEmail = $section->data['email'] ?? 'hello@creative.dev';
    $contactEmail = filter_var($rawEmail, FILTER_VALIDATE_EMAIL) ? $rawEmail : 'hello@creative.dev';
@endphp
<section id="{{ $section->getAnchorId() }}" class="py-60 bg-black text-center section-contact relative">
    <div class="container mx-auto px-6">
        <h2 class="text-2xl md:text-3xl font-light tracking-[0.5em] uppercase mb-10 text-green-400 opacity-80">
            {{ $section->title }}
        </h2>
        <a href="mailto:{{ $contactEmail }}"
            class="glitch-text relative text-[clamp(2.5rem,10vw,8rem)] font-black tracking-tighter leading-none hover:text-green-400/80 transition-colors uppercase split-text inline-block"
            id="contact-mail" data-text="LET'S TALK"
            aria-label="Send email to {{ $contactEmail }}">
            LET'S TALK
        </a>

        <div class="mt-40 flex flex-col items-center gap-10">
            <div class="w-px h-32 bg-gradient-to-b from-green-400 to-transparent"></div>
            <div class="text-xs tracking-[0.6em] uppercase opacity-50 text-green-400/50">Malaysia Based • Available
                Worldwide</div>
        </div>
    </div>
</section>