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
use Illuminate\Contracts\Validation\ImplicitRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\InvokableRule;
use Illuminate\Validation\InvokableValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;

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
            InvokableRule::class => 'extend',
            DataAwareRule::class => 'extendDependent',
            ImplicitRule::class => 'extendImplicit',
            Rule::class => 'extend',
        ];
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
     * Extends the Validator with the given Illuminate\Contracts\Validation\Rule implementation.
     *
     * @param string|Rule|InvokableRule $extension
     * @param string|null $rule
     * @return void
     *
     * @throws ReflectionException
     */
    protected function rule(string|Rule|InvokableRule $extension, string $rule = null): void
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
        Validator::$method(
            $rule,
            (static function ($attribute, $value, $parameters, $validator) use ($extension) {
                $rule = new $extension(...$parameters);

                if ($rule instanceof InvokableRule) {
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
        $replacers = $extension instanceof ReplacerAwareRule?$extension->replacers():[];

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

    protected function isValidRule(object $extension): bool
    {
        return $extension instanceof Rule || $extension instanceof InvokableRule;
    }
}
