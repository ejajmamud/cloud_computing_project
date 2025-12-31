@extends('frontend.layouts.app')

@section('content')
<div class="bg-page">
    <header class="page-banner-header gradient-bg position-relative">
        <div class="section-overlay">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="page-banner-content text-center">
                            <h3 class="page-banner-heading text-white pb-15">{{ __('UniKL SkillSwap Hub') }}</h3>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb justify-content-center">
                                    <li class="breadcrumb-item font-14"><a href="{{ url('/') }}">{{__('Home')}}</a></li>
                                    <li class="breadcrumb-item font-14 active" aria-current="page">{{ __('SkillSwap') }}</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <section class="section-t-space">
        <div class="container">

            {{-- Display Success/Error Messages (using LMSzai's standard include) --}}
            @include('frontend.layouts.flash_message') 

            <div class="row mb-4">
                <div class="col-12 d-flex justify-content-between align-items-center">
                    <h5 class="font-24 font-semi-bold">{{ __('Open Exchange Posts') }}</h5>
                    <a href="{{ route('skillswap.create') }}" class="theme-btn theme-button1 default-hover-btn">
                        {{ __('Post New Skill/Request') }}
                    </a>
                </div>
            </div>

            <div class="row">
                @forelse($posts as $post)
                    {{-- Card adapted from instructor-item/instructor-support-item in base script --}}
                    <div class="col-md-6 col-lg-4 mb-30">
                        <div class="card instructor-support-item bg-white radius-3 p-4 shadow">
                            <h5 class="font-semi-bold mb-2">{{ __($post->title) }}</h5>
                            
                            {{-- Badge for Type and Status --}}
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <span class="badge {{ $post->type == 'offer' ? 'bg-success' : 'bg-warning' }} me-2">
                                        {{ $post->type == 'offer' ? __('Skill Offer') : __('Help Request') }}
                                    </span>
                                    <span class="badge bg-primary">{{ __('Status') }}: {{ ucfirst($post->status) }}</span>
                                </div>
                            </div>
                            
                            <p class="font-14 color-gray pt-2 mb-3">{{ Str::limit($post->description, 100) }}</p>

                            <ul class="list-unstyled font-15 mb-3">
                                <li>
                                    <span class="iconify me-2" data-icon="akar-icons:tag"></span> 
                                    <strong>{{ __('Skill') }}:</strong> {{ __($post->skill_tag) }}
                                </li>
                                <li>
                                    <span class="iconify me-2" data-icon="solar:user-circle-bold-duotone"></span> 
                                    <strong>{{ __('Posted By') }}:</strong> {{ __($post->creator->name) }}
                                </li>
                                <li>
                                    <span class="iconify me-2" data-icon="solar:calendar-date-bold-duotone"></span> 
                                    <strong>{{ __('Date') }}:</strong> {{ $post->created_at->format('M d, Y') }}
                                </li>
                            </ul>

                            {{-- Placeholder action button (will link to a Match/Chat page later) --}}
                            <a href="#" class="theme-btn theme-button3 mt-2 w-100">{{ __('View Details / Match Peer') }} <i data-feather="arrow-right"></i></a>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-center py-5">
                        <div class="bg-white p-5 radius-4">
                            <p class="font-24 color-gray mb-3">{{ __('No open skill offers or requests found yet.') }}</p>
                            <p class="font-16 color-gray">{{ __('Be the first to post a skill or request help!') }}</p>
                            <a href="{{ route('skillswap.create') }}" class="theme-btn theme-button1 default-hover-btn mt-4">
                                {{ __('Post New Exchange') }}
                            </a>
                        </div>
                    </div>
                @endforelse
            </div>
            
            {{-- Pagination (LMSZAI standard) --}}
            <div class="row">
                <div class="col-12 d-flex justify-content-center mt-3">
                    {{ $posts->links() }}
                </div>
            </div>

        </div>
    </section>
    </div>
@endsection