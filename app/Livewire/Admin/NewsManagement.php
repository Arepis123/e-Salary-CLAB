<?php

namespace App\Livewire\Admin;

use App\Models\News;
use Flux\Flux;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class NewsManagement extends Component
{
    use WithFileUploads;

    public $news;

    public $newsId;

    public $showModal = false;

    public $modalMode = 'create'; // create or edit

    #[Validate('required|string|max:255')]
    public $title = '';

    #[Validate('required|string')]
    public $description = '';

    #[Validate('required|in:welcome,alert,announcement,image')]
    public $type = 'announcement';

    #[Validate('nullable|image|max:5120')]
    public $image;

    public $existing_image_path = '';

    #[Validate('nullable|string|max:255')]
    public $button_text = '';

    #[Validate('nullable|string|max:255')]
    public $button_url = '';

    #[Validate('required|integer|min:0')]
    public $order = 0;

    public $is_active = true;

    #[Validate('nullable|date')]
    public $published_at = '';

    #[Validate('nullable|date')]
    public $expires_at = '';

    public function mount()
    {
        $this->loadNews();
    }

    public function loadNews()
    {
        $this->news = News::orderBy('order')->orderBy('created_at', 'desc')->get();
    }

    public function openCreateModal()
    {
        $this->reset(['title', 'description', 'type', 'image', 'existing_image_path', 'button_text', 'button_url', 'order', 'is_active', 'published_at', 'expires_at']);
        $this->modalMode = 'create';
        $this->showModal = true;
        $this->is_active = true;
        $this->type = 'announcement';
    }

    public function openEditModal($id)
    {
        $news = News::findOrFail($id);
        $this->newsId = $news->id;
        $this->title = $news->title;
        $this->description = $news->description;
        $this->type = $news->type;
        $this->existing_image_path = $news->image_path;
        $this->button_text = $news->button_text ?? '';
        $this->button_url = $news->button_url ?? '';
        $this->order = $news->order;
        $this->is_active = $news->is_active;
        $this->published_at = $news->published_at ? $news->published_at->format('Y-m-d') : '';
        $this->expires_at = $news->expires_at ? $news->expires_at->format('Y-m-d') : '';
        $this->modalMode = 'edit';
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['title', 'description', 'type', 'image', 'existing_image_path', 'button_text', 'button_url', 'order', 'is_active', 'published_at', 'expires_at', 'newsId']);
    }

    public function save()
    {
        $this->validate();

        $data = [
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'button_text' => $this->button_text,
            'button_url' => $this->button_url,
            'order' => $this->order,
            'is_active' => $this->is_active,
            'published_at' => $this->published_at ?: null,
            'expires_at' => $this->expires_at ?: null,
        ];

        // Handle image upload
        if ($this->image) {
            $imagePath = $this->image->store('news', 'public_uploads');
            $data['image_path'] = $imagePath;
        }

        if ($this->modalMode === 'create') {
            News::create($data);
            Flux::toast(variant: 'success', text: 'News created successfully.');
        } else {
            $news = News::findOrFail($this->newsId);

            // If no new image, keep existing
            if (! $this->image && $this->existing_image_path) {
                $data['image_path'] = $this->existing_image_path;
            }

            $news->update($data);
            Flux::toast(variant: 'success', text: 'News updated successfully.');
        }

        $this->closeModal();
        $this->loadNews();
    }

    public function delete($id)
    {
        News::findOrFail($id)->delete();
        Flux::toast(variant: 'success', text: 'News deleted successfully.');
        $this->loadNews();
    }

    public function toggleActive($id)
    {
        $news = News::findOrFail($id);
        $news->update(['is_active' => ! $news->is_active]);
        $this->loadNews();
    }

    public function render()
    {
        return view('livewire.admin.news-management')
            ->layout('components.layouts.app', ['title' => 'News Management']);
    }
}
