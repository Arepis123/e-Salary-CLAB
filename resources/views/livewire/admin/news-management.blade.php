<div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">News Management</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Manage dashboard carousel news and announcements</p>
            </div>
            <flux:button wire:click="openCreateModal" variant="primary">
                <flux:icon.plus class="size-4 inline" />
                Add News
            </flux:button>
        </div>

        <!-- News Table -->
        <flux:card class="p-0 dark:bg-zinc-900 rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400 uppercase tracking-wider">Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400 uppercase tracking-wider">Published</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400 uppercase tracking-wider">Expires</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($news as $item)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $item->order }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        @if($item->image_path)
                                            <img src="{{ asset('images/uploads/' . $item->image_path) }}" alt="{{ $item->title }}" class="w-16 h-12 rounded-lg object-cover border border-zinc-200 dark:border-zinc-700">
                                        @else
                                            <div class="w-16 h-12 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center border border-zinc-200 dark:border-zinc-700">
                                                <flux:icon.photo class="size-6 text-zinc-400 dark:text-zinc-600" />
                                            </div>
                                        @endif
                                        <div>
                                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $item->title }}</p>
                                            <p class="text-xs text-zinc-600 dark:text-zinc-400 line-clamp-1">{{ Str::limit($item->description, 50) }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:badge
                                        :color="match($item->type) {
                                            'welcome' => 'blue',
                                            'alert' => 'orange',
                                            'announcement' => 'purple',
                                            'image' => 'green',
                                            default => 'zinc'
                                        }"
                                        size="sm">
                                        {{ ucfirst($item->type) }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button
                                        wire:click="toggleActive({{ $item->id }})"
                                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium {{ $item->is_active ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-300' }}">
                                        <span class="w-2 h-2 rounded-full {{ $item->is_active ? 'bg-green-600' : 'bg-zinc-400' }}"></span>
                                        {{ $item->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ $item->published_at ? $item->published_at->format('M d, Y') : 'Immediately' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ $item->expires_at ? $item->expires_at->format('M d, Y') : 'Never' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        <flux:button wire:click="openEditModal({{ $item->id }})" variant="ghost" size="sm">
                                            <flux:icon.pencil class="size-4" />
                                        </flux:button>
                                        <flux:button wire:click="delete({{ $item->id }})" wire:confirm="Are you sure you want to delete this news?" variant="ghost" size="sm" class="text-red-600 dark:text-red-400">
                                            <flux:icon.trash class="size-4" />
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <flux:icon.newspaper class="size-8 text-zinc-300 dark:text-zinc-600" />
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400">No news items yet. Create your first one!</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>

        <!-- Create/Edit Modal -->
        @if($showModal)
            <flux:modal wire:model="showModal" class="w-auto">
                <form wire:submit="save">
                    <div class="space-y-6">
                        <div>
                            <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $modalMode === 'create' ? 'Create News' : 'Edit News' }}
                            </h2>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                {{ $modalMode === 'create' ? 'Add a new news item to the dashboard carousel' : 'Update the news item details' }}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Title -->
                            <div class="md:col-span-2">
                                <flux:input wire:model="title" label="Title" placeholder="Enter news title" />
                                @error('title') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>

                            <!-- Description -->
                            <div class="md:col-span-2">
                                <flux:textarea wire:model="description" label="Description" placeholder="Enter news description" rows="3" />
                                @error('description') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>

                            <!-- Type -->
                            <div>
                                <flux:select variant="listbox" wire:model="type" label="Type" placeholder="Select type">
                                    <flux:select.option value="welcome">Welcome</flux:select.option>
                                    <flux:select.option value="alert">Alert</flux:select.option>
                                    <flux:select.option value="announcement">Announcement</flux:select.option>
                                    <flux:select.option value="image">Image</flux:select.option>
                                </flux:select>
                                @error('type') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>

                            <!-- Order -->
                            <div>
                                <flux:input wire:model="order" type="number" label="Order" placeholder="0" />
                                @error('order') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>

                            <!-- Image Upload -->
                            <div class="md:col-span-2">
                                <flux:file-upload wire:model="image" accept="image/*" label="Image">
                                    <flux:file-upload.dropzone
                                        heading="Drop image here or click to browse"
                                        text="JPG, PNG, GIF up to 5MB"
                                        with-progress
                                        inline
                                    />
                                </flux:file-upload>
                                @error('image')
                                    <div class="mt-1">
                                        <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span>
                                    </div>
                                @enderror

                                @if ($image)
                                    <div class="mt-4 space-y-3">
                                        <div class="flex items-start gap-3 p-3 bg-zinc-50 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                            <div class="flex-shrink-0">
                                                <img src="{{ $image->temporaryUrl() }}" alt="Preview" class="w-20 h-20 rounded object-cover">
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $image->getClientOriginalName() }}</p>
                                                <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">{{ number_format($image->getSize() / 1024, 2) }} KB</p>
                                                @php
                                                    try {
                                                        $imageInfo = getimagesize($image->getRealPath());
                                                        $width = $imageInfo[0] ?? 0;
                                                        $height = $imageInfo[1] ?? 0;
                                                    } catch (\Exception $e) {
                                                        $width = 0;
                                                        $height = 0;
                                                    }
                                                @endphp
                                                @if($width > 0 && $height > 0)
                                                    <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                                        Dimensions: {{ $width }} × {{ $height }}px
                                                    </p>
                                                @endif
                                            </div>
                                            <button type="button" wire:click="$set('image', null)" class="flex-shrink-0 p-1 hover:bg-zinc-200 dark:hover:bg-zinc-700 rounded transition-colors">
                                                <flux:icon.x-mark class="size-4 text-zinc-600 dark:text-zinc-400" />
                                            </button>
                                        </div>
                                    </div>
                                @elseif($existing_image_path && $modalMode === 'edit')
                                    <div class="mt-4 space-y-2">
                                        <flux:file-item
                                            heading="Current Image"
                                            image="{{ asset('images/uploads/' . $existing_image_path) }}"
                                        >
                                            <x-slot name="actions">
                                                <flux:file-item.remove wire:click="$set('existing_image_path', '')" />
                                            </x-slot>
                                        </flux:file-item>
                                        @php
                                            try {
                                                $imagePath = public_path('images/uploads/' . $existing_image_path);
                                                if (file_exists($imagePath)) {
                                                    $imageInfo = getimagesize($imagePath);
                                                    $width = $imageInfo[0] ?? 0;
                                                    $height = $imageInfo[1] ?? 0;
                                                } else {
                                                    $width = 0;
                                                    $height = 0;
                                                }
                                            } catch (\Exception $e) {
                                                $width = 0;
                                                $height = 0;
                                            }
                                        @endphp
                                        @if($width > 0 && $height > 0)
                                            <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                                Image dimensions: {{ $width }} × {{ $height }}px
                                            </p>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <!-- Button Text -->
                            <div>
                                <flux:input wire:model="button_text" label="Button Text (Optional)" placeholder="e.g., View Details" />
                                @error('button_text') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>

                            <!-- Button URL -->
                            <div>
                                <flux:input wire:model="button_url" label="Button URL (Optional)" placeholder="e.g., /admin/salary" />
                                @error('button_url') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>

                            <!-- Published At -->
                            <div>
                                <flux:input wire:model="published_at" type="date" label="Publish Date (Optional)" />
                                @error('published_at') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Leave empty to publish immediately</p>
                            </div>

                            <!-- Expires At -->
                            <div>
                                <flux:input wire:model="expires_at" type="date" label="Expiry Date (Optional)" />
                                @error('expires_at') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Leave empty to never expire</p>
                            </div>

                            <!-- Active Status -->
                            <div class="md:col-span-2">
                                <flux:checkbox wire:model="is_active" label="Active (Show in carousel)" />
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                            <flux:button type="button" wire:click="closeModal" variant="ghost">Cancel</flux:button>
                            <flux:button type="submit" variant="primary">
                                {{ $modalMode === 'create' ? 'Create News' : 'Update News' }}
                            </flux:button>
                        </div>
                    </div>
                </form>
            </flux:modal>
        @endif
</div>
