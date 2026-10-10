<?php

namespace App\Models;

use App\Models\Concerns\UsesRandomUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'whatsapp', 'is_general', 'active'])]
class ContactPerson extends Model
{
    use UsesRandomUuid;

    protected $table = 'contact_persons';

    protected function casts(): array
    {
        return [
            'is_general' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /**
     * The public shape: the WhatsApp number is turned into a wa.me link and
     * never carries an identifier, so the endpoint stays public-safe.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'name' => (string) $this->name,
            'whatsapp' => (string) $this->whatsapp,
            'waLink' => 'https://wa.me/'.preg_replace('/\D+/', '', (string) $this->whatsapp),
        ];
    }
}
