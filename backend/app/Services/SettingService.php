<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SettingAudit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SettingService
{
    private const CACHE_KEY = 'settings:all';

    /**
     * All settings keyed by their stable key, cached until an update invalidates.
     *
     * @return Collection<string, Setting>
     */
    public function all(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): Collection {
            return Setting::query()->orderBy('key')->get()->keyBy('key');
        });
    }

    public function find(string $key): ?Setting
    {
        return $this->all()->get($key);
    }

    public function getString(string $key, ?string $default = null): ?string
    {
        $value = $this->find($key)?->typedValue();

        return $value === null ? $default : (string) $value;
    }

    public function getInt(string $key, ?int $default = null): ?int
    {
        $value = $this->find($key)?->typedValue();

        return $value === null ? $default : (int) $value;
    }

    public function getBool(string $key, ?bool $default = null): ?bool
    {
        $value = $this->find($key)?->typedValue();

        return $value === null ? $default : (bool) $value;
    }

    /**
     * Allowlisted public settings as a flat key => typed-value map.
     */
    public function publicSettings(): array
    {
        return $this->all()
            ->filter(fn (Setting $setting): bool => $setting->is_public)
            ->map(fn (Setting $setting): string|int|bool|null => $setting->typedValue())
            ->all();
    }

    /**
     * Full operational view (metadata included). Secrets are never stored in
     * this table, so nothing here needs redacting.
     */
    public function adminIndex(): array
    {
        return $this->all()
            ->map(fn (Setting $setting): array => [
                'key' => $setting->key,
                'value' => $setting->typedValue(),
                'type' => $setting->type,
                'group' => $setting->group,
                'is_public' => $setting->is_public,
                'description' => $setting->description,
                'constraints' => $setting->constraints,
                'updated_by' => $setting->updated_by === null ? null : (int) $setting->updated_by,
                'updated_at' => $setting->updated_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    /**
     * Validate and persist a partial update. Only known keys with a matching
     * declared type and stored constraints are accepted; cross-field
     * invariants are checked against the merged resulting state. Every
     * changed key is audited with actor, old/new values, and reason.
     *
     * @return array<string, array{old: string|int|bool|null, new: string|int|bool|null}>
     */
    public function update(array $input, User $actor, string $reason): array
    {
        $settings = $this->all();
        $errors = [];
        $proposed = [];

        foreach ($input as $key => $value) {
            $setting = $settings->get($key);

            if (! $setting instanceof Setting) {
                $errors["settings.$key"] = ['Unknown setting key.'];

                continue;
            }

            $validator = Validator::make(['value' => $value], ['value' => $this->rulesFor($setting)]);

            if ($validator->fails()) {
                $errors["settings.$key"] = $validator->errors()->get('value');

                continue;
            }

            $proposed[$key] = $validator->validated()['value'];
        }

        $errors = array_merge($errors, $this->crossFieldErrors($settings, $proposed));

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $changed = [];

        DB::transaction(function () use ($settings, $proposed, $actor, $reason, &$changed): void {
            foreach ($proposed as $key => $value) {
                $setting = $settings->get($key);
                $storageValue = $setting->toStorageValue($value);

                if ($setting->value === $storageValue) {
                    continue; // No-op update: nothing changed, nothing audited.
                }

                $oldValue = $setting->typedValue();

                Setting::query()->where('key', $key)->update([
                    'value' => $storageValue,
                    'updated_by' => $actor->id,
                    'updated_at' => now(),
                ]);

                SettingAudit::query()->create([
                    'setting_id' => $setting->id,
                    'key' => $setting->key,
                    'actor_id' => $actor->id,
                    'old_value' => $setting->value,
                    'new_value' => $storageValue,
                    'reason' => $reason,
                    'created_at' => now(),
                ]);

                $changed[$key] = ['old' => $oldValue, 'new' => $setting->typedValueOf($storageValue)];
            }
        });

        if ($changed !== []) {
            Cache::forget(self::CACHE_KEY);
        }

        return $changed;
    }

    /**
     * @return list<string>
     */
    private function rulesFor(Setting $setting): array
    {
        $rules = match ($setting->type) {
            'boolean' => ['boolean'],
            'integer' => ['integer'],
            default => ['string'],
        };

        if ($setting->constraints) {
            $rules = array_merge($rules, array_values(array_filter(explode('|', $setting->constraints))));
        }

        return $rules;
    }

    /**
     * Cross-field invariants evaluated against the merged state (current
     * values overlaid with the proposed update).
     *
     * @param  Collection<string, Setting>  $settings
     * @param  array<string, mixed>  $proposed
     * @return array<string, list<string>>
     */
    private function crossFieldErrors(Collection $settings, array $proposed): array
    {
        $errors = [];

        $merged = $settings
            ->map(fn (Setting $setting): string|int|bool|null => $setting->typedValue())
            ->all();

        foreach ($proposed as $key => $value) {
            if (isset($settings[$key])) {
                $merged[$key] = $settings[$key]->typedValueOf($settings[$key]->toStorageValue($value));
            }
        }

        $passingScore = $merged['scoring.passing_score'] ?? null;
        $passScale = $merged['scoring.pass_scale'] ?? null;
        $penalty = $merged['scoring.negative_penalty_points'] ?? null;

        if ($passingScore !== null
            && $passScale !== null
            && $passingScore > $passScale) {
            $errors['settings.scoring.passing_score'] = ['The passing score must not exceed the score scale (scoring.pass_scale).'];
        }

        if ($penalty !== null
            && $penalty < 0) {
            $errors['settings.scoring.negative_penalty_points'] = ['The negative-marking penalty must be non-negative.'];
        }

        return $errors;
    }
}
