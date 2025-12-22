<?php

namespace App\Livewire;

use Livewire\Component;

class UserManual extends Component
{
    public function render()
    {
        return view('livewire.user-manual')
            ->layout('components.layouts.guest');
    }
}
