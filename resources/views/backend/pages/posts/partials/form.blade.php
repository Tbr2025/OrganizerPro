{!! ld_apply_filters('inside_post_form_start', '') !!}

<input type="hidden" name="post_id" value="{{ $post->id ?? '' }}" data-post-id="{{ $post->id ?? '' }}">
<input type="hidden" name="post_type" value="{{ $postType }}" data-post-type="{{ $postType }}">

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Main Content Area -->
    <div class="lg:col-span-3 space-y-6">
        <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="p-5 space-y-4 sm:p-6">
                <!-- Title and Slug with Alpine.js -->
                <div x-data="slugGenerator('{{ old('title', $post->title ?? '') }}', '{{ old('slug', $post->slug ?? '') }}')">
                    <!-- Title -->
                    <div class="space-y-1">
                        <label for="title"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Title') }}</label>
                        <input type="text" name="title" id="title" required x-model="title" maxlength="255"
                            class="form-control">
                    </div>
                    {!! ld_apply_filters('post_form_after_title', '') !!}

                    <!-- Compact Slug UI -->
                    <div class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-300">
                        <span class="mr-1">{{ __('Permalink') }}:</span>
                        <span class="flex-1 truncate" x-show="!showSlugEdit">
                            {{-- The real public prefix, not url('/'): a blog post is served from
                                 /blog/{slug}, and showing the bare root sent every "view" link
                                 to a 404. A type with no public route says so instead. --}}
                            @php $publicPrefix = \App\Models\Post::publicUrlPrefix((string) $postType); @endphp
                            <span class="text-gray-400">{{ $publicPrefix ?? url('/') . '/' }}</span><span
                                class="font-medium text-primary" x-text="slug || '{{ __('auto-generated') }}'"></span>
                        </span>
                        <div class="flex-1" x-show="showSlugEdit">
                            <input type="text" name="slug" id="slug" x-model="slug" maxlength="200"
                                class="h-7 w-full rounded border border-gray-300 bg-transparent px-2 py-1 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                placeholder="{{ __('Leave empty to auto-generate') }}">
                        </div>
                        <div class="ml-2 flex space-x-1">
                            <!-- Edit/Save Button -->
                            <button type="button" @click="toggleSlugEdit()"
                                class="text-xs text-primary hover:underline">
                                <span x-show="!showSlugEdit">{{ __('Edit') }}</span>
                                <span x-show="showSlugEdit">{{ __('OK') }}</span>
                            </button>
                            <!-- Generate Button -->
                            <button type="button" @click="generateSlug()"
                                class="text-xs text-primary hover:underline ml-2">
                                {{ __('Generate') }}
                            </button>
                        </div>
                    </div>
                    {!! ld_apply_filters('post_form_after_slug', '') !!}
                </div>

                @if ($postTypeModel->supports_editor)
                    <div class="space-y-1">
                        <label for="content"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Content') }}</label>
<div>
                            <textarea name="content" id="content" rows="10">{!! old('content', $post->content ?? '') !!}</textarea>
</div>
                    </div>
                @endif
                {!! ld_apply_filters('post_form_after_content', '') !!}

                @if ($postTypeModel->supports_excerpt)
                    <div class="space-y-1">
                        <label for="excerpt"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Excerpt') }}</label>
                        <textarea name="excerpt" id="excerpt" rows="3"
                            class="w-full rounded-md border border-gray-300 bg-transparent p-4 text-sm text-gray-700 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">{{ old('excerpt', $post->excerpt ?? '') }}</textarea>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-300">
                            {{ __('A short summary of the content') }}.
                            {{ __('Leave empty to auto-generate from content') }}</p>
                    </div>
                @endif
                {!! ld_apply_filters('post_form_after_excerpt', '') !!}

                @if ($postTypeModel->supports_thumbnail)
                    <x-inputs.file-input
                        name="featured_image"
                        id="featured_image"
                        accept="image/*"
                        label="{{ __('Featured Image') }}"
                        :existingAttachment="isset($post) && $post->featured_image ? $post->featured_image : null"
                        :existingAltText="isset($post) ? $post->title : ''"
                        :removeCheckboxLabel="__('Remove featured image')"
                        class="mt-1"
                    >
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-300">
                            {{ __('Select an image to represent this post') }}
                        </p>
                    </x-inputs.file-input>
                @endif
                {!! ld_apply_filters('post_form_after_featured_image', '') !!}
            </div>
        </div>

        <x-advanced-fields :post-meta="isset($post) ? $post->getAllMeta() : []" />
    </div>

    <!-- Sidebar Area -->
    <div class="lg:col-span-1 space-y-6">
        <!-- Status and Visibility -->
        <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="px-4 py-3 sm:px-6 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-2">
                <h3 class="text-base font-medium text-gray-700 dark:text-white">{{ __('Status & Visibility') }}</h3>

                {{-- Straight to the page a reader sees. Only offered once the post exists and its
                     type has a public route, and it says when the page is not live yet rather
                     than handing over a link that 404s. --}}
                @if(isset($post) && $post->publicUrl())
                    @php $isLive = \App\Models\Post::query()->whereKey($post->getKey())->publiclyVisible()->exists(); @endphp
                    <a href="{{ $post->publicUrl() }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-lg
                              {{ $isLive ? 'bg-green-50 text-green-700 hover:bg-green-100 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400' }}">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        {{ $isLive ? __('View public page') : __('Preview — not live yet') }}
                    </a>
                @endif
            </div>
            <div class="p-3 space-y-2 sm:p-4">
                <!-- Status with Combobox -->
                @php
                    $statusOptions = ld_apply_filters('post_status_options', [
                        ['value' => 'draft', 'label' => __('Draft')],
                        ['value' => 'publish', 'label' => __('Published')],
                        ['value' => 'pending', 'label' => __('Pending Review')],
                        ['value' => 'future', 'label' => __('Scheduled')],
                        ['value' => 'private', 'label' => __('Private')],
                    ]);
                    $currentStatus = old('status', $post->status ?? 'draft');
                @endphp

                <x-inputs.combobox name="status" label="{{ __('Status') }}" :options="$statusOptions" :selected="$currentStatus"
                    :multiple="false" :searchable="false" x-model="status" />

                {!! ld_apply_filters('post_form_after_status', '') !!}

                <!-- Publish Date (for scheduled posts) -->
                <div x-data="{
                    showSchedule: {{ isset($post) && (old('status', $post->status) === 'future' || $post->published_at) ? 'true' : 'false' }},
                    status: '{{ old('status', $post->status ?? 'draft') }}',
                    init() {
                        this.$watch('status', value => {
                            if (value === 'future') {
                                this.showSchedule = true;
                            }
                        });
                    }
                }">
                    <div class="mb-2">
                        <input type="checkbox" id="schedule_post" name="schedule_post" x-model="showSchedule"
                            x-on:change="if(showSchedule && status !== 'future') status = 'future'; $dispatch('input', status)"
                            class="form-checkbox mr-2">
                        <label for="schedule_post"
                            class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Schedule this post') }}</label>
                    </div>
                    <div x-show="showSchedule" class="mt-2">
                        <x-inputs.datetime-picker id="published_at" name="published_at" :label="__('Publish Date')"
                            :value="old(
                                'published_at',
                                isset($post) && $post->published_at
                                    ? $post->published_at->format('Y-m-d H:i')
                                    : now()->addDay()->format('Y-m-d H:i'),
                            )" :min-date="now()->format('Y-m-d')" :help-text="__('Schedule when this post should be published')" />
                    </div>
                </div>
                {!! ld_apply_filters('post_form_after_publish_date', '') !!}
                <div class="mt-4">
                    <x-buttons.submit-buttons cancelUrl="{{ route('admin.posts.index', $postType) }}" />
                </div>
                {!! ld_apply_filters('post_form_after_submit_buttons', '') !!}
            </div>
        </div>

        @if ($postTypeModel->hierarchical)
            <!-- Parent -->
            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="px-4 py-3 sm:px-6 sm:py-3 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-base font-medium text-gray-700 dark:text-white">{{ __('Parent') }}</h3>
                </div>
                <div class="p-3 space-y-2 sm:p-4">
                    @php
                        $parentOptions = [['value' => '', 'label' => __('None')]];
                        foreach ($parentPosts as $id => $title) {
                            $parentOptions[] = [
                                'value' => $id,
                                'label' => $title,
                            ];
                        }
                    @endphp

                    <x-inputs.combobox name="parent_id"
                    :label="__('Parent ' . $postTypeModel->label_singular)"
                    :placeholder="__('Select Parent')" :options="$parentOptions"
                    :selected="old('parent_id', $post->parent_id ?? '')"
                    :searchable="false" />
                </div>
            </div>
        @endif
        {!! ld_apply_filters('post_form_after_content_parent', '') !!}

        <!-- Taxonomies -->
        @if (!empty($taxonomies))
            @foreach ($taxonomies as $taxonomy)
                @include('backend.pages.posts.partials.post-taxonomy-chooser', [
                    'taxonomy' => $taxonomy,
                    'post_type' => $postType,
                ])
            @endforeach
        @endif
    </div>
</div>
