@php
    use App\Models\Setting;
    use App\Support\Breadcrumbs;
    $crumbs = Breadcrumbs::make([['label' => __('pages.contact.title'), 'url' => lroute('contact')]]);
    $intro = Setting::text('contact_intro') ?: __('pages.contact.lede');
    $location = Setting::text('location') ?: __('pages.contact.location');
    $req = '<span class="req" aria-hidden="true">*</span>';
@endphp
<x-layout :title="__('pages.contact.title')" :description="__('pages.contact.description')" :jsonld="[Breadcrumbs::jsonld($crumbs)]">
    <x-page-head :title="__('pages.contact.title')" :lede="$intro" :crumbs="$crumbs" />

    <div class="section section--tight">
        <div class="wrap grid">
            <section class="span-7" aria-labelledby="form-title">
                <h2 id="form-title" class="h3">{{ __('pages.contact.form_title') }}</h2>

                @if ($errors->any())
                    <div class="notice notice--warn" role="alert" tabindex="-1" id="form-errors">
                        <p><strong>{{ __('pages.contact.error') }}</strong></p>
                        <ul>@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="post" action="{{ lroute('contact.store') }}" novalidate>
                    @csrf
                    <input type="hidden" name="_t" value="{{ $formToken }}">
                    {{-- Campo isco: escondido de pessoas e de leitores de ecrã --}}
                    <div class="hp-field" aria-hidden="true">
                        <label for="website">Website</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="field">
                        <label for="name">{{ __('pages.contact.name') }} {!! $req !!}<span class="visually-hidden">({{ __('pages.contact.required') }})</span></label>
                        <input id="name" name="name" type="text" required maxlength="120" autocomplete="name" value="{{ old('name') }}"
                               @error('name') aria-invalid="true" aria-describedby="name-err" @enderror>
                        @error('name')<p class="error" id="name-err">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="email">{{ __('pages.contact.email') }} {!! $req !!}<span class="visually-hidden">({{ __('pages.contact.required') }})</span></label>
                        <input id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="{{ old('email') }}"
                               @error('email') aria-invalid="true" aria-describedby="email-err" @enderror>
                        @error('email')<p class="error" id="email-err">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="organisation">{{ __('pages.contact.organisation') }}</label>
                        <input id="organisation" name="organisation" type="text" maxlength="190" autocomplete="organization" value="{{ old('organisation') }}">
                    </div>
                    <div class="field">
                        <label for="subject">{{ __('pages.contact.subject') }} {!! $req !!}<span class="visually-hidden">({{ __('pages.contact.required') }})</span></label>
                        <select id="subject" name="subject" required @error('subject') aria-invalid="true" @enderror>
                            @foreach (\App\Http\Controllers\ContactController::SUBJECTS as $s)
                                <option value="{{ $s }}" @selected(old('subject', $subject ?? 'proposal') === $s)>{{ __('pages.contact.subjects.'.$s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="message">{{ __('pages.contact.message') }} {!! $req !!}<span class="visually-hidden">({{ __('pages.contact.required') }})</span></label>
                        <textarea id="message" name="message" required minlength="10" maxlength="5000"
                                  @error('message') aria-invalid="true" aria-describedby="message-err" @enderror>{{ old('message') }}</textarea>
                        @error('message')<p class="error" id="message-err">{{ $message }}</p>@enderror
                    </div>
                    <p class="small muted">{!! __('pages.contact.privacy', ['link' => '<a href="'.e(lroute('privacy')).'">'.e(__('pages.contact.privacy_link')).'</a>']) !!}</p>
                    <button type="submit" class="btn btn--primary">{{ __('pages.contact.send') }}</button>
                </form>
            </section>

            <aside class="span-4 start-9" aria-labelledby="details">
                <div class="specimen">
                    <h2 class="specimen__title" id="details">{{ __('pages.contact.details') }}</h2>
                    <dl>
                        <dt>{{ __('pages.contact.email_label') }}</dt>
                        <dd><a href="mailto:{{ $email }}">{{ $email }}</a></dd>
                        <dt>{{ __('pages.contact.location_label') }}</dt>
                        <dd>{{ $location }}</dd>
                        <dt>{{ __('pages.contact.hours_label') }}</dt>
                        <dd>{{ __('pages.contact.hours') }}</dd>
                    </dl>
                    <p class="coord specimen__foot">19°50′S 34°51′E · Beira</p>
                </div>
            </aside>
        </div>
    </div>
</x-layout>
