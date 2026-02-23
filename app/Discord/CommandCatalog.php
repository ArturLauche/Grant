<?php

declare(strict_types=1);

namespace Grant\Discord;

final class CommandCatalog
{
    public static function definitions(): array
    {
        return [
            [
                'name' => 'ping',
                'description' => 'Check if Grant is alive',
                'type' => 1,
            ],
            [
                'name' => 'echo',
                'description' => 'Echo your input back',
                'type' => 1,
                'options' => [
                    [
                        'name' => 'input',
                        'description' => 'Text to echo',
                        'type' => 3,
                        'required' => true,
                    ],
                ],
            ],
            [
                'name' => 'marks',
                'description' => 'Manage officer marks',
                'type' => 1,
                'options' => [
                    ['type' => 1, 'name' => 'add', 'description' => 'Add marks', 'options' => self::officerAndAmountOptions()],
                    ['type' => 1, 'name' => 'subtract', 'description' => 'Subtract marks', 'options' => self::officerAndAmountOptions()],
                    ['type' => 1, 'name' => 'get', 'description' => 'Get marks', 'options' => [[
                        'name' => 'officer', 'description' => 'Officer user', 'type' => 6, 'required' => false,
                    ]]],
                ],
            ],
            [
                'name' => 'officer',
                'description' => 'Officer management',
                'type' => 1,
                'options' => [
                    ['type' => 1, 'name' => 'register', 'description' => 'Register an officer', 'options' => [[
                        'name' => 'user', 'description' => 'Target user (optional)', 'type' => 6, 'required' => false,
                    ]]],
                    ['type' => 1, 'name' => 'info', 'description' => 'Show officer info', 'options' => [[
                        'name' => 'officer', 'description' => 'Target user', 'type' => 6, 'required' => false,
                    ]]],
                    ['type' => 1, 'name' => 'remove', 'description' => 'Remove officer', 'options' => [[
                        'name' => 'officer', 'description' => 'Target user', 'type' => 6, 'required' => true,
                    ]]],
                    ['type' => 1, 'name' => 'promote', 'description' => 'Promote officer', 'options' => [[
                        'name' => 'officer', 'description' => 'Target user', 'type' => 6, 'required' => true,
                    ], [
                        'name' => 'rank', 'description' => 'New rank label', 'type' => 3, 'required' => true,
                    ]]],
                    ['type' => 1, 'name' => 'demote', 'description' => 'Demote officer', 'options' => [[
                        'name' => 'officer', 'description' => 'Target user', 'type' => 6, 'required' => true,
                    ], [
                        'name' => 'rank', 'description' => 'New rank label', 'type' => 3, 'required' => true,
                    ]]],
                    ['type' => 1, 'name' => 'blacklist', 'description' => 'Set blacklist status', 'options' => [[
                        'name' => 'officer', 'description' => 'Target user', 'type' => 6, 'required' => true,
                    ], [
                        'name' => 'state', 'description' => 'on to blacklist, off to unblacklist', 'type' => 3, 'required' => true,
                        'choices' => [
                            ['name' => 'on', 'value' => 'on'],
                            ['name' => 'off', 'value' => 'off'],
                        ],
                    ]]],
                ],
            ],
            [
                'name' => 'command',
                'description' => 'Developer database maintenance commands',
                'type' => 1,
                'options' => [
                    ['type' => 1, 'name' => 'export', 'description' => 'Export officers as base64 JSON', 'options' => [[
                        'name' => 'limit', 'description' => 'Number of rows to export (1-500, default 50)', 'type' => 4, 'required' => false, 'min_value' => 1, 'max_value' => 500,
                    ]]],
                    ['type' => 1, 'name' => 'import', 'description' => 'Import officers from base64 JSON export payload', 'options' => [[
                        'name' => 'payload', 'description' => 'Base64 string returned by /command export', 'type' => 3, 'required' => true,
                    ]]],
                ],
            ],
        ];
    }

    private static function officerAndAmountOptions(): array
    {
        return [
            ['name' => 'officer', 'description' => 'Target officer', 'type' => 6, 'required' => true],
            ['name' => 'amount', 'description' => 'Positive integer amount', 'type' => 4, 'required' => true, 'min_value' => 1],
        ];
    }
}
