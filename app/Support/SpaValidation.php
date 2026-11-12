<?php

namespace App\Support;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class SpaValidation
{
    public static function make(array $data, array $rules, array $messages = [], mixed $id = null): ValidatorContract
    {
        $converted = [];
        $customMessages = [];
        foreach ($rules as $field => $fieldRules) {
            foreach (explode('|', $fieldRules) as $sourceRule) {
                preg_match('/^(\w+)(?:\[(.*)\])?$/D', $sourceRule, $parts);
                $name = $parts[1];
                $argument = $parts[2] ?? '';
                $rule = match ($name) {
                    'required' => 'required',
                    'if_exist' => 'sometimes',
                    'permit_empty' => 'nullable',
                    'min_length' => "min:$argument",
                    'max_length' => "max:$argument",
                    'valid_email' => 'email',
                    'valid_date' => 'date_format:Y-m-d',
                    'valid_time' => 'date_format:H:i,H:i:s',
                    'is_natural' => ['integer', 'min:0'],
                    'is_natural_no_zero' => ['integer', 'min:1'],
                    'decimal' => 'numeric',
                    'greater_than_equal_to' => "min:$argument",
                    'less_than_equal_to' => "max:$argument",
                    'less_than' => 'max:'.((int) $argument - 1),
                    'regex_match' => "regex:$argument",
                    'in_list' => "in:$argument",
                    'is_unique' => self::uniqueRule($argument, $id),
                    default => throw new \LogicException("Unknown validation rule: $name"),
                };

                foreach (is_array($rule) ? $rule : [$rule] as $item) {
                    $converted[$field][] = $item;
                    if (isset($messages[$field][$name])) {
                        $key = is_string($item) ? explode(':', $item, 2)[0] : 'unique';
                        $customMessages["$field.$key"] = $messages[$field][$name];
                    }
                }
            }
        }

        return Validator::make($data, $converted, $customMessages);
    }

    private static function uniqueRule(string $argument, mixed $id)
    {
        [$target, $column, $ignore] = array_pad(explode(',', $argument), 3, null);
        [$table, $field] = explode('.', $target, 2);
        $rule = Rule::unique($table, $field);
        $value = $ignore === '{id}' ? $id : $ignore;

        return $column && $value !== null ? $rule->ignore($value, $column) : $rule;
    }
}
