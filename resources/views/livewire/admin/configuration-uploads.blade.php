<!-- Uploads Tab -->
<div class="space-y-6">

    <!-- Upload Form -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Upload New Document</h3>

        <form wire:submit="uploadDocument" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <flux:select wire:model="uploadKey" label="Document Type" variant="listbox">
                    <flux:select.option value="faq">FAQ (Client Sidebar)</flux:select.option>
                    <flux:select.option value="general">General</flux:select.option>
                </flux:select>

                <flux:input wire:model="uploadTitle" label="Title" placeholder="e.g. Frequently Asked Questions" />
            </div>

            <flux:textarea wire:model="uploadDescription" label="Description (Optional)" rows="2" placeholder="Short description shown to admins only..." />

            <div
                x-data
                @document-uploaded.window="$el.querySelectorAll('input[type=file]').forEach(i => i.value = '')"
            >
                <flux:file-upload wire:model="uploadFile" label="Document File (PDF, max 10MB)" accept="application/pdf">
                    <flux:file-upload.dropzone
                        heading="Drop file or click to browse"
                        text="PDF up to 10MB"
                        inline
                    />
                </flux:file-upload>

                <div wire:loading wire:target="uploadFile" class="mt-2 text-sm text-zinc-500">
                    Uploading...
                </div>

                @if($uploadFile)
                    <div class="mt-3">
                        <flux:file-item heading="{{ $uploadFile->getClientOriginalName() }}" :size="$uploadFile->getSize()">
                            <x-slot name="actions">
                                <flux:file-item.remove wire:click="removeUploadFile" />
                            </x-slot>
                        </flux:file-item>
                    </div>
                @endif

                @error('uploadFile') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="uploadDocument,uploadFile">
                    <span wire:loading.remove wire:target="uploadDocument">Upload Document</span>
                    <span wire:loading wire:target="uploadDocument">Uploading...</span>
                </flux:button>
            </div>
        </form>
    </flux:card>

    <!-- Documents Table -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Uploaded Documents</h3>

        @if($uploadedDocumentsPaginated->total() === 0)
            <div class="px-4 py-8 text-center">
                <flux:icon.document class="size-10 mx-auto text-zinc-300 dark:text-zinc-600 mb-2" />
                <p class="text-sm text-zinc-600 dark:text-zinc-400">No documents uploaded yet</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>
                            <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">#</span>
                        </flux:table.column>
                        <flux:table.column>
                            <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Title</span>
                        </flux:table.column>
                        <flux:table.column>
                            <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Type</span>
                        </flux:table.column>
                        <flux:table.column>
                            <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Size</span>
                        </flux:table.column>
                        <flux:table.column>
                            <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Uploaded By</span>
                        </flux:table.column>
                        <flux:table.column>
                            <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Date</span>
                        </flux:table.column>
                        <flux:table.column>
                            <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</span>
                        </flux:table.column>
                        <flux:table.column>
                            <span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span>
                        </flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($uploadedDocumentsPaginated as $document)
                            <flux:table.row :key="$document->id">
                                <flux:table.cell variant="strong">
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $uploadedDocumentsPaginated->firstItem() + $loop->index }}</span>
                                </flux:table.cell>

                                <flux:table.cell variant="strong">
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $document->title }}</p>
                                        @if($document->description)
                                            <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $document->description }}</p>
                                        @endif
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell variant="strong">
                                    <flux:badge size="sm" color="{{ $document->key === 'faq' ? 'blue' : 'zinc' }}">
                                        {{ $document->key === 'faq' ? 'FAQ' : 'General' }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell variant="strong">
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $document->readable_size }}</span>
                                </flux:table.cell>

                                <flux:table.cell variant="strong">
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $document->uploadedBy->name ?? 'Unknown' }}</span>
                                </flux:table.cell>

                                <flux:table.cell variant="strong">
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $document->created_at->format('d M Y H:i') }}</span>
                                </flux:table.cell>

                                <flux:table.cell variant="strong">
                                    <flux:badge size="sm" color="{{ $document->is_active ? 'green' : 'zinc' }}">
                                        {{ $document->is_active ? 'Active' : 'Inactive' }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <flux:dropdown align="center">
                                        <flux:button size="sm" variant="filled" icon:trailing="chevron-down">Edit</flux:button>

                                        <flux:menu>
                                            <flux:menu.item
                                                icon="viewfinder-circle"
                                                href="{{ route('documents.view', ['document' => $document, 'filename' => $document->public_filename]) }}"
                                                target="_blank"
                                            >
                                                Preview
                                            </flux:menu.item>

                                            <flux:menu.item
                                                icon="{{ $document->is_active ? 'eye-slash' : 'eye' }}"
                                                wire:click="toggleDocument({{ $document->id }})"
                                            >
                                                {{ $document->is_active ? 'Deactivate' : 'Activate' }}
                                            </flux:menu.item>

                                            <flux:menu.separator />

                                            <flux:menu.item
                                                variant="danger"
                                                icon="trash"
                                                wire:click="deleteDocument({{ $document->id }})"
                                                wire:confirm="Are you sure you want to delete &quot;{{ $document->title }}&quot;? This cannot be undone."
                                            >
                                                Delete
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>                                    
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            <flux:pagination :paginator="$uploadedDocumentsPaginated" class="mt-4" />
        @endif
    </flux:card>
</div>
