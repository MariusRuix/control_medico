<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Envío de plantillas por WhatsApp Business Cloud API (Meta).
 */
final class WhatsAppClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    /**
     * @param  list<string>  $parameters  Valores de la plantilla en orden ({{1}}, {{2}}, …).
     */
    public function sendTemplate(string $to, array $parameters): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('WhatsApp no está configurado (WHATSAPP_TOKEN / WHATSAPP_PHONE_NUMBER_ID).');
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            config('services.whatsapp.api_version'),
            config('services.whatsapp.phone_number_id'),
        );

        try {
            Http::withToken((string) config('services.whatsapp.token'))
                ->timeout(15)
                ->retry(2, 500, throw: false)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'template',
                    'template' => [
                        'name' => config('services.whatsapp.template'),
                        'language' => ['code' => config('services.whatsapp.language')],
                        'components' => [[
                            'type' => 'body',
                            'parameters' => array_map(
                                static fn (string $value): array => ['type' => 'text', 'text' => $value],
                                $parameters,
                            ),
                        ]],
                    ],
                ])
                ->throw();
        } catch (Throwable $e) {
            Log::error('Falló el envío por WhatsApp.', ['to' => substr($to, 0, 4).'…', 'error' => $e->getMessage()]);

            throw new RuntimeException('No se pudo enviar el mensaje de WhatsApp.', previous: $e);
        }
    }
}
