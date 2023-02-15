<?php

namespace Henzeb\Ruler\Concerns;

use Closure;
use ReflectionClass;
use RuntimeException;
use ReflectionException;
use Illuminate\Support\Str;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Henzeb\Ruler\Validator\RulerValidator;
use Henzeb\Ruler\Contracts\ReplacerAwareRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\InvokableRule;
use Illuminate\Validation\InvokableValidationRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;

use function is_a;
use function interface_exists;

trait Ruler
{
    /**
     * @throws ReflectionException
     */
    public function boot(): void
    {
        $this->bootRuler();
    }

    /**
     * Use this method when you are implementing your own boot method.
     *
     * @return void
     * @throws ReflectionException
     */
    protected function bootRuler(): void
    {
        if (method_exists($this, 'rules')) {
            $this->rules($this->rules);
        }
    }

    private function rulerClassmap(): array
    {
        return [
            'Illuminate\Contracts\Validation\InvokableRule' => 'extend',
            'Illuminate\Contracts\Validation\ValidationRule' => 'extend',
            'Illuminate\Contracts\Validation\DataAwareRule' => 'extendDependent',
            'Illuminate\Contracts\Validation\ImplicitRule' => 'extendImplicit',
            'Illuminate\Contracts\Validation\Rule' => 'extend',
        ];
    }

    private function rulerAvailableClasses(): array
    {
        return array_filter(
            [
                'Illuminate\Contracts\Validation\InvokableRule',
                'Illuminate\Contracts\Validation\ValidationRule',
                'Illuminate\Contracts\Validation\Rule'
            ],
            'interface_exists'
        );
    }

    /**
     * Allows you to add an array of rules.
     *
     * @param array $rules
     * @return void
     * @throws ReflectionException
     */
    protected function rules(array $rules)
    {
        foreach ($rules as $rule => $extension) {
            $this->rule($extension, is_string($rule) ? $rule : null);
        }
    }

    /**
     * Extends the Validator with the given Rule implementation.
     *
     * Depending on your laravel version some might not be available or deprecated,
     * hence the hacky way of typehinting.
     *
     * @param string|Rule|InvokableRule|ValidationRule $extension
     * @param string|null $rule
     * @return void
     *
     * @throws ReflectionException
     */
    protected function rule(string|object $extension, string $rule = null): void
    {
        if (is_string($extension) && class_exists($extension)) {
            $extension = (new ReflectionClass($extension))->newInstanceWithoutConstructor();
        }

        $rule = $rule ?? Str::snake(class_basename($extension));

        $this->validateRuleOrThrowException($extension, $rule);

        foreach ($this->rulerClassmap() as $class => $method) {
            if ($extension instanceof $class) {
                $this->extendValidator(
                    $rule,
                    $method,
                    $extension::class
                );
            }
        }

        $this->addReplacer($rule, $extension);
    }

    /**
     * extends the validator
     *
     * @param string $rule
     * @param string $method
     * @param mixed $extension
     * @return void
     */
    private function extendValidator(string $rule, string $method, string $extension): void
    {
        $invokable = $this->rulerIsInvokableRule($extension);

        Validator::$method(
            $rule,
            (static function ($attribute, $value, $parameters, $validator) use ($extension, $invokable) {
                $rule = new $extension(...$parameters);

                if ($invokable) {
                    $rule = InvokableValidationRule::make($rule);
                }

                RulerValidator::$rulers[$extension] = $rule;

                if ($rule instanceof DataAwareRule) {
                    $rule->setData($validator->getData());
                }

                if ($rule instanceof ValidatorAwareRule) {
                    $rule->setValidator($validator);
                }

                return $rule->passes($attribute, $value);
            })->bindTo(null, RulerValidator::class),
            (static fn() => RulerValidator::$rulers[$extension]->message())->bindTo(null, RulerValidator::class)
        );
    }

    /**
     * adds a replacer
     *
     * @param string $rule
     * @param object $extension
     * @return void
     */
    private function addReplacer(string $rule, object $extension): void
    {
        $replacers = $extension instanceof ReplacerAwareRule ? $extension->replacers() : [];

        Validator::replacer(
            $rule,
            function ($message, $attribute, $rule, $parameters, $validator) use ($replacers) {
                foreach (
                    $this->labelParameters(
                        $replacers,
                        $parameters,
                        $attribute,
                        $validator->getData()
                    ) as $key => $value
                ) {
                    $message = str_replace(':' . $key, $value, $message);
                }
                return $message;
            }
        );
    }

    /**
     * This method combines the replacers with the parameters as keys
     *
     * @param array $replacers
     * @param array $parameters
     * @param string $attribute
     * @param array $data
     * @return array
     */
    private function labelParameters(array $replacers, array $parameters, string $attribute, array $data): array
    {
        if (empty($replacers)) {
            return $parameters;
        }

        /**
         * allow for optional parameters
         */
        $replacers = array_slice($replacers, 0, count($parameters));

        [$replacerKeys, $replacerClosures] = $this->parseReplacers($replacers);

        $replacers = array_combine($replacerKeys, $parameters);

        array_walk(
            $replacers,
            function (&$currentParameter, $key) use ($replacerClosures, $parameters, $attribute, $data) {
                if (isset($replacerClosures[$key])) {
                    $currentParameter = $replacerClosures[$key]($currentParameter, $attribute, $parameters, $data);
                }
            }
        );

        return $replacers;
    }

    /**
     * Separates the closures from the keys
     *
     * @param array $replacers
     * @return array
     */
    private function parseReplacers(array $replacers): array
    {
        $result = [0 => [], 1 => []];

        foreach ($replacers as $key => $replacer) {
            $result[0][] = $replacer instanceof Closure ? $key : $replacer;

            if ($replacer instanceof Closure) {
                $result[1][$key] = $replacer;
            }
        }

        return $result;
    }


    protected function validateRuleOrThrowException(object $extension, string $rule): void
    {
        if ($this->isValidRule($extension)) {
            return;
        }
        throw new RuntimeException(
            sprintf(
                'Validation rule \'%s\' should be an instance of \'%s\' or \'%s\'',
                $rule,
                Rule::class,
                InvokableRule::class
            )
        );
    }


    private function isValidRule(object $extension): bool
    {
        foreach ($this->rulerAvailableClasses() as $ruleClass) {
            if (is_a($extension, $ruleClass)) {
                return true;
            }
        }

        return false;
    }

    private function rulerIsInvokableRule(string $extension): bool
    {
        $invokableRules = [
            'Illuminate\Contracts\Validation\InvokableRule',
            'Illuminate\Contracts\Validation\ValidationRule'
        ];
        foreach ($invokableRules as $invokableRule) {
            if (interface_exists($invokableRule)
                && is_a($extension, $invokableRule, true)
            ) {
                return true;
            }
        }
        return false;
    }


}
