<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class AutomationDefinition
{
    private const STEP_TYPES = ['condition', 'action'];

    private const ACTIONS = ['send_message', 'dispatch_webhook', 'set_context'];

    private const OPERATORS = ['equals', 'not_equals', 'contains', 'starts_with', 'ends_with', 'exists', 'in'];

    public function validate(array $steps): array
    {
        if (count($steps) > 50) {
            throw ValidationException::withMessages(['steps' => 'Um fluxo pode ter no máximo 50 etapas.']);
        }

        foreach ($steps as $index => $step) {
            if (! is_array($step) || ! in_array($step['type'] ?? null, self::STEP_TYPES, true)) {
                throw ValidationException::withMessages(["steps.{$index}.type" => 'Tipo de etapa inválido.']);
            }
            if ($step['type'] === 'condition') {
                if (! filled($step['field'] ?? null) || ! in_array($step['operator'] ?? null, self::OPERATORS, true)) {
                    throw ValidationException::withMessages(["steps.{$index}" => 'A condição precisa de field e operator válidos.']);
                }
            }
            if ($step['type'] === 'action' && ! in_array($step['action'] ?? null, self::ACTIONS, true)) {
                throw ValidationException::withMessages(["steps.{$index}.action" => 'Ação de automação inválida.']);
            }
        }

        return array_values($steps);
    }
}
