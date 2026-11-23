<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Ecosystem\NotificationHubService;
use Illuminate\Foundation\Http\FormRequest;

/** Interruptor de avisos por sistema (solo los sistemas que emiten avisos). Opera sobre el propio usuario. */
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
        $rules = ['active' => ['nullable', 'array']];

        foreach (NotificationHubService::SYSTEMS as $system) {
            $rules['active.'.$system] = ['nullable', 'boolean'];
        }

        return $rules;
    }
}
