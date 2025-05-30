<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use Illuminate\Http\Request;

class ConfigurationController extends Controller
{
    public function valueByType(string $type, string $options, mixed $value): string
    {
        if ($type == 'multi_select' OR $type == 'radio') {
            return $this->getOptionValue($options, $value);
        }

        return (string)$value;
    }

    public function getOptionValue(string $option, string $selected): string
    {
        if (!$option) {
            return $selected;
        }

        $option = unserialize($option);
        $selected = str($selected)->isJson() ? json_decode($selected, true, 512, JSON_THROW_ON_ERROR) : explode(',', $selected);

        $value = [];
        foreach ($selected as $item) {
            $value[] = $option[$item];
        }

        return implode('; ', $value);
    }
}
