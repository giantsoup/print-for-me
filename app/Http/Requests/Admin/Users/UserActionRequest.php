<?php

namespace App\Http\Requests\Admin\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

abstract class UserActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('user');

        return $subject instanceof User
            && $this->user() instanceof User
            && $this->user()->can($this->ability(), $subject);
    }

    abstract protected function ability(): string;
}
