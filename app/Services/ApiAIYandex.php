<?php

namespace App\Services;

use App\DTO\MedBotCreateClientDTO;
use App\DTO\MedBotDeleteClientDTO;
use App\Exceptions\MedBotException;
use Illuminate\Support\Facades\Http;

class ApiAIYandex
{
    protected $config;

    public function __construct()
    {
        $this->config = config('medbot');
    }

    protected function getAuthData()
    {
        return [
            'partner_key' => $this->config['partner_key'],
            'partner_password' => $this->config['partner_password'],
            'service_name' => $this->config['service_name'],
        ];
    }

    protected function getHash($data)
    {
        return hash('sha256', implode('', array_values($data)));
    }

    public function createClient(MedBotCreateClientDTO $clientDTO)
    {
        $data = $this->getAuthData();
        $data['client_id'] = $clientDTO->clientId;
        $data['token'] = $this->getHash($data);
        $data['name'] = $clientDTO->name;
        $data['email'] = $clientDTO->email;
        $data['phone'] = $clientDTO->phone;

        $response = Http::post($this->config['url'].'creat/client', $data);

        if ($response->status() !== 200) {
            throw new MedBotException('Не удалось создать клиента: '.($response->json()['message'] ?? $response->json()['code']));
        }

        if ($response->json()['code'] != 'create_client') {
            throw new MedBotException('Не удалось создать клиента: '.($response->json()['message'] ?? $response->json()['code']));
        }

        return $response->json();
    }

    public function deleteClient(MedBotDeleteClientDTO $clientDTO)
    {
        $data = $this->getAuthData();
        $data['client_id'] = $clientDTO->clientId;
        $data['token'] = $this->getHash($data);

        $response = Http::post($this->config['url'].'delete/client', $data);

        if ($response->status() !== 200) {
            throw new MedBotException('Не удалось создать клиента: '.($response->json()['message'] ?? $response->json()['code']));
        }

        if ($response->json()['code'] != 'delete_client') {
            throw new MedBotException('Не удалось создать клиента: '.($response->json()['message'] ?? $response->json()['code']));
        }

        return $response->json();
    }
}
