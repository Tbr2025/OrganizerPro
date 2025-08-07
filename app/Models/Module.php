<?php

namespace App\Models;

use Illuminate\Contracts\Support\Arrayable;

class Module implements Arrayable
{
    public string $id;
    public string $name;

    public bool $status = false;

    public function __construct(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->{$key} = $value;
        }

        if (! isset($this->id)) {
            $this->id = $this->name ?? '';
        }

        $this->status = $attributes['status'] ?? false;
    }

    public function toArray()
    {
        return get_object_vars($this);
    }
}
