<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Ecosystem\NotificationHubService;
use Illuminate\Foundation\Http\FormRequest;

/** Silenciado del push por sistema (solo los sistemas que emiten avisos). Opera sobre el propio usuario. */
class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = ['muted' => ['nullable', 'array']];

        foreach (NotificationHubService::SYSTEMS as $system) {
            $rules['muted.'.$system] = ['nullable', 'boolean'];
        }

        return $rules;
    }
}
