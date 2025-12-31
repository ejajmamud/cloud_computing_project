@extends('frontend.layouts.app')

@section('content')
<div class="bg-page">
    <header class="page-banner-header gradient-bg position-relative">
        <div class="section-overlay">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="page-banner-content text-center">
                            <h3 class="page-banner-heading text-white pb-15">{{ __('Post a Skill or Request Help') }}</h3>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb justify-content-center">
                                    <li class="breadcrumb-item font-14"><a href="{{ url('/') }}">{{__('Home')}}</a></li>
                                    <li class="breadcrumb-item font-14"><a href="{{ route('skillswap.index') }}">{{__('SkillSwap')}}</a></li>
                                    <li class="breadcrumb-item font-14 active" aria-current="page">{{ __('Create Post') }}</li>
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
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="contact-form-area bg-white p-5 radius-4 shadow">
                        <h5 class="contact-form-title font-24 font-semi-bold mb-4">{{ __('Submit Your Exchange Post') }}</h5>
                        
                        <form method="POST" action="{{ route('skillswap.store') }}">
                            @csrf

                            {{-- Post Type Selector --}}
                            <div class="row">
                                <div class="col-md-12 mb-30">
                                    <label class="label-text-title color-heading">{{ __('Post Type *') }}</label>
                                    <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                                        <option value="">{{__('Select Post Type')}}</option>
                                        <option value="offer" {{ old('type') == 'offer' ? 'selected' : '' }}>{{ __('Offer Skill (I can teach)') }}</option>
                                        <option value="request" {{ old('type') == 'request' ? 'selected' : '' }}>{{ __('Request Help (I need to learn)') }}</option>
                                    </select>
                                    @error('type') <span class="text-danger font-13">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            {{-- Title Input --}}
                            <div class="row">
                                <div class="col-md-12 mb-30">
                                    <label class="label-text-title color-heading">{{ __('Title *') }}</label>
                                    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" placeholder="{{ __('Brief title for your post') }}" required>
                                    @error('title') <span class="text-danger font-13">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            
                            {{-- Skill Tag Input --}}
                            <div class="row">
                                <div class="col-md-12 mb-30">
                                    <label class="label-text-title color-heading">{{ __('Key Skill Tag (e.g., Python, UI/UX, Public Speaking) *') }}</label>
                                    <input type="text" name="skill_tag" class="form-control @error('skill_tag') is-invalid @enderror" value="{{ old('skill_tag') }}" placeholder="{{ __('The primary skill tag') }}" required>
                                    @error('skill_tag') <span class="text-danger font-13">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            {{-- Description Textarea --}}
                            <div class="row">
                                <div class="col-md-12 mb-30">
                                    <label class="label-text-title color-heading">{{ __('Detailed Description *') }}</label>
                                    <textarea name="description" class="form-control message @error('description') is-invalid @enderror" rows="5" required>{{ old('description') }}</textarea>
                                    @error('description') <span class="text-danger font-13">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="theme-btn theme-button1 theme-button3 w-100 font-15 fw-bold">{{__('Submit Post')}}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    </div>
@endsection