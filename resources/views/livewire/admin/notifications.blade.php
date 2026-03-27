<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Notifications & Reminders</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Manage notification templates and send messages to clients</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid gap-4 md:grid-cols-4">
        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Templates</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['total_templates'] }}</p>
                </div>
                <div class="rounded-full bg-blue-100 dark:bg-blue-900/30 p-3">
                    <flux:icon.document-text class="size-6 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Active Templates</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['active_templates'] }}</p>
                </div>
                <div class="rounded-full bg-green-100 dark:bg-green-900/30 p-3">
                    <flux:icon.check-circle class="size-6 text-green-600 dark:text-green-400" />
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Sent Today</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['sent_today'] }}</p>
                </div>
                <div class="rounded-full bg-purple-100 dark:bg-purple-900/30 p-3">
                    <flux:icon.paper-airplane class="size-6 text-purple-600 dark:text-purple-400" />
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Failed Today</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $stats['failed_today'] }}</p>
                </div>
                <div class="rounded-full bg-red-100 dark:bg-red-900/30 p-3">
                    <flux:icon.x-circle class="size-6 text-red-600 dark:text-red-400" />
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Tabs -->
    <div class="border-b border-zinc-200 dark:border-zinc-700">
        <nav class="-mb-px flex space-x-8">
            <button wire:click="$set('activeTab', 'logs')"
                class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'logs' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                History
            </button>
            <button wire:click="$set('activeTab', 'templates')"
                class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'templates' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                Templates
            </button>
            <button wire:click="$set('activeTab', 'send')"
                class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'send' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                Send Notification
            </button>
        </nav>
    </div>

    <!-- Content based on active tab -->
    @if($activeTab === 'templates')
        <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Notification Templates</h2>
                <flux:modal.trigger name="template-modal">
                    <flux:button wire:click="openTemplateModal" variant="primary">
                        <flux:icon.plus class="size-4 inline" />
                        Create Template
                    </flux:button>
                </flux:modal.trigger>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Name</th>
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Type</th>
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Trigger</th>
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</th>
                            <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($templates as $template)
                            <tr>
                                <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                    <div class="font-medium">{{ $template->name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $template->slug }}</div>
                                </td>
                                <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">
                                    <flux:badge color="zinc" size="sm">{{ ucfirst($template->type) }}</flux:badge>
                                </td>
                                <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">
                                    @if($template->trigger_type === 'auto_payment_deadline')
                                        <flux:badge color="purple" size="sm">
                                            <flux:icon.clock variant="outline" class="size-4 me-1"/>
                                            Auto ({{ $template->trigger_days_before }} days)
                                        </flux:badge>
                                    @elseif($template->trigger_type === 'auto_overdue')
                                        <flux:badge color="red" size="sm">
                                            <flux:icon.clock variant="outline"  class="size-4 me-1"/>
                                            Auto (Overdue)
                                        </flux:badge>
                                    @elseif($template->trigger_type === 'auto_submission_deadline')
                                        <flux:badge color="orange" size="sm">
                                            <flux:icon.clock variant="outline"  class="size-4 me-1"/>
                                            Auto (Submission)
                                        </flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm">
                                            <flux:icon.hand-raised variant="outline"  class="size-4 me-1"/>
                                            Manual
                                        </flux:badge>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <button wire:click="toggleTemplateStatus({{ $template->id }})">
                                        @if($template->is_active)
                                            <flux:badge color="green" size="sm">Active</flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm">Inactive</flux:badge>
                                        @endif
                                    </button>
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <flux:modal.trigger name="template-modal">
                                            <flux:button wire:click="openTemplateModal({{ $template->id }})" variant="ghost" size="sm" icon="pencil" icon-variant="outline">
                                                Edit                                        
                                            </flux:button>
                                        </flux:modal.trigger>
                                        <flux:button wire:click="deleteTemplate({{ $template->id }})"
                                            wire:confirm="Are you sure you want to delete this template?"
                                            variant="ghost" size="sm" icon="trash" icon-variant="outline">
                                            Delete
                                        </flux:button>
                                    </div>                                   
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-sm text-zinc-500">
                                    No templates found. Create your first template to get started.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif

    @if($activeTab === 'send')
        <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Send Notification</h2>
            <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">
                <flux:icon.information-circle variant="micro" class="inline" />
                Only manual templates are shown here. Automatic templates are sent by the system scheduler.
            </p>
            <div class="space-y-4">
                <div>
                    <flux:select wire:model="selectedTemplateId" variant="listbox" label="Select Template" size="sm">
                        <flux:select.option value="">Choose a template...</flux:select.option>
                        @forelse($manualTemplates->where('is_active', true) as $template)
                            <flux:select.option value="{{ $template->id }}">{{ $template->name }}</flux:select.option>
                        @empty
                            <flux:select.option value="" disabled>No manual templates available</flux:select.option>
                        @endforelse
                    </flux:select>
                </div>

                <div>
                    <div class="mb-2"><span class="text-sm font-medium">Recipient</span></div>
                    <flux:field variant="inline">
                        <flux:checkbox wire:model.live="sendToAll" />
                        <flux:label>Send to all clients</flux:label>
                        <flux:error name="terms" />
                    </flux:field>                    
                    @if(!$sendToAll)
                        <div class="max-h-48 overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg p-3 space-y-2 mt-2">
                            @foreach($clients as $client)
                                <flux:checkbox wire:model="selectedRecipients" value="{{ $client->id }}" label="{{ $client->name }}" />
                            @endforeach
                        </div>
                    @endif
                </div>

                <div>
                    <div class="mb-2">
                        <span class="text-sm font-medium">Custom Message</span>
                        <span class="text-sm font-medium dark:text-neutral-400">(Optional)</span>
                    </div>
                    <flux:textarea wire:model="customBody" rows="3" placeholder="Add a custom message to the template..." />
                </div>

                <div>
                    <flux:file-upload wire:model="attachments" label="Attachment (Optional)">
                        <flux:file-upload.dropzone
                            heading="Drop file or click to browse"
                            text="Single file up to 1MB"
                            inline
                        />
                    </flux:file-upload>

                    @if(!empty($attachments))
                        <div class="mt-3">
                            @php
                                $file = is_array($attachments) ? $attachments[0] : $attachments;
                                $fileName = $file->getClientOriginalName();
                                $fileSize = $file->getSize();
                            @endphp
                            <flux:file-item heading="{{ $fileName }}" size="{{ $fileSize }}">
                                <x-slot name="actions">
                                    <flux:file-item.remove wire:click="removeAttachment" />
                                </x-slot>
                            </flux:file-item>
                        </div>
                    @endif

                    @error('attachments')
                        <span class="text-red-600 text-xs mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <flux:button wire:click="sendNotification" variant="primary" class="mt-2" icon="paper-airplane" icon-variant="outline">
                    Send Notification
                </flux:button>
            </div>
        </flux:card>
    @endif

    @if($activeTab === 'logs')
        <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Notification History</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Date</th>
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Recipient</th>
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Subject</th>
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Attachment</th>
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($logs as $logGroup)
                            @php
                                $primaryLog = $logGroup['primary'];
                                $recipients = $logGroup['recipients'];
                                $recipientCount = count($recipients);
                            @endphp
                            <tr>
                                <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $primaryLog->created_at->format('M d, Y H:i') }}</td>
                                <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                    @if($recipientCount === 1)
                                        {{ $recipients[0]->recipient ? $recipients[0]->recipient->name : $recipients[0]->recipient_email }}
                                    @else
                                        <div class="space-y-1">
                                            @foreach(array_slice($recipients, 0, 3) as $log)
                                                <div class="text-xs">{{ $log->recipient ? $log->recipient->name : $log->recipient_email }}</div>
                                            @endforeach
                                            @if($recipientCount > 3)
                                                <flux:button size="xs" variant="ghost" x-data @click="$dispatch('open-modal', 'recipients-{{ $primaryLog->id }}')">
                                                    +{{ $recipientCount - 3 }} more
                                                </flux:button>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">{{ $primaryLog->subject }}</td>
                                <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400">
                                    @if(!empty($primaryLog->attachments) && is_array($primaryLog->attachments) && count($primaryLog->attachments) > 0)
                                        <div class="flex items-center gap-1">
                                            <flux:icon.paper-clip variant="micro" class="text-zinc-500" />
                                            <span class="text-xs">{{ count($primaryLog->attachments) }} file(s)</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-zinc-400">-</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    @if($primaryLog->status === 'sent')
                                        <flux:badge color="green" size="sm">Sent</flux:badge>
                                    @elseif($primaryLog->status === 'failed')
                                        <flux:badge color="red" size="sm">Failed</flux:badge>
                                    @else
                                        <flux:badge color="yellow" size="sm">Pending</flux:badge>
                                    @endif
                                </td>
                            </tr>

                            @if($recipientCount > 3)
                                <flux:modal name="recipients-{{ $primaryLog->id }}" class="space-y-4">
                                    <div>
                                        <flux:heading size="lg">All Recipients ({{ $recipientCount }})</flux:heading>
                                        <flux:subheading>{{ $primaryLog->subject }}</flux:subheading>
                                    </div>
                                    <div class="max-h-96 overflow-y-auto space-y-2">
                                        @foreach($recipients as $log)
                                            <div class="flex items-center justify-between p-2 rounded border border-zinc-200 dark:border-zinc-700">
                                                <span class="text-sm">{{ $log->recipient ? $log->recipient->name : $log->recipient_email }}</span>
                                                @if($log->status === 'sent')
                                                    <flux:badge color="green" size="sm">Sent</flux:badge>
                                                @elseif($log->status === 'failed')
                                                    <flux:badge color="red" size="sm">Failed</flux:badge>
                                                @else
                                                    <flux:badge color="yellow" size="sm">Pending</flux:badge>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                    <flux:button variant="ghost" x-on:click="$dispatch('close-modal', 'recipients-{{ $primaryLog->id }}')">Close</flux:button>
                                </flux:modal>
                            @endif
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-sm text-zinc-500">No notification history found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif

    <!-- Template Modal -->
    <flux:modal name="template-modal" variant="default" class="space-y-6 w-full">
        <div>
            <flux:heading size="lg">{{ $editingTemplateId ? 'Edit' : 'Create' }} Template</flux:heading>
        </div>

        <flux:input wire:model="templateForm.name" label="Template Name" placeholder="e.g., Payment Reminder" />

        <flux:input wire:model="templateForm.slug" label="Slug" placeholder="payment_reminder" />

        <div>
            <flux:label>Type</flux:label>
            <flux:select wire:model="templateForm.type" variant="listbox">
                <flux:select.option value="email">Email</flux:select.option>
                <flux:select.option value="sms" disabled>SMS</flux:select.option>
            </flux:select>
        </div>

        <flux:input wire:model="templateForm.subject" label="Subject" placeholder="Email subject" />

        <div x-data>
            <flux:label>Body</flux:label>
            <flux:textarea wire:model="templateForm.body" rows="10" placeholder="Use @{{client_name}} for variables" x-ref="bodyTextarea" />
            <div class="mt-2">
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-1.5">Click to insert variable:</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach([
                        'client_name'  => 'Client Name',
                        'client_email' => 'Client Email',
                        'client_phone' => 'Client Phone',
                        'date'         => "Today's Date",
                        'month'        => 'Current Month',
                        'message'      => 'Custom Message',
                    ] as $varKey => $label)
                        @php $tag = '{{' . $varKey . '}}'; @endphp
                        <button type="button"
                            class="inline-flex items-center gap-1 rounded-md border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 px-2 py-0.5 text-xs font-mono text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-colors"
                            x-on:click="
                                const tag = '{{ $tag }}';
                                const el = $refs.bodyTextarea.querySelector('textarea') ?? $refs.bodyTextarea;
                                const start = el.selectionStart;
                                const end = el.selectionEnd;
                                el.value = el.value.slice(0, start) + tag + el.value.slice(end);
                                el.selectionStart = el.selectionEnd = start + tag.length;
                                el.dispatchEvent(new Event('input'));
                                el.focus();
                            ">
                            {{ $tag }}
                            <span class="text-blue-400 dark:text-blue-500 font-sans not-italic font-normal">{{ $label }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <flux:separator />

        <div class="flex gap-2 justify-end">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button wire:click="saveTemplate" variant="primary">Save Template</flux:button>
        </div>
    </flux:modal>
</div>
