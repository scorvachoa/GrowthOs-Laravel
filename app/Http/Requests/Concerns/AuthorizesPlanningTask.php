<?php

namespace App\Http\Requests\Concerns;

trait AuthorizesPlanningTask
{
    public function authorize(): bool
    {
        return $this->isMethod('POST')
            ? $this->user()->can('create planning')
            : $this->user()->can('edit planning');
    }
}
