<?php

namespace Henzeb\Ruler\Concerns;

use Closure;
use Henzeb\Ruler\Contracts\ReplacerAwareRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ImplicitRule;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

trait Ruler
{
    /**
     * @param string|Rule $extension the extension you'd like to add can be classFQN or an instantiated Rule object
     * @param string|null $rule name of the rule
     * @return void
     *
     * @throws ReflectionException in case the string could not be instantiated
     */
    protected function rule(string|Rule $extension, string $rule = null): void
    {
        if (is_string($extension) && class_exists($extension)) {
            $extension = (new ReflectionClass($extension))->newInstanceWithoutConstructor();
        }

        if (!$extension instanceof Rule) {
            throw new RuntimeException('Validation rule \'' . $rule . '\' should be an instance of ' . Rule::class);
        }

        if ($extension instanceof ReplacerAwareRule) {
            $replacers = $extension->replacers();
        }

        $rule = $rule ?? Str::snake(class_basename($extension));

        $extends = [
            DataAwareRule::class => 'extendDependent',
            ImplicitRule::class => 'extendImplicit',
            Rule::class => 'extend'
        ];

        foreach ($extends as $class => $method) {
            if ($extension instanceof $class) {
                $this->extendValidator(
                    $rule,
                    $method,
                    $extension::class,
                    $extension->message(),
                    $replacers ?? []
                );
            }
        }

        $this->addReplacer($rule, $replacers ?? []);
    }

    /**
     * @param array $rules
     * @return void
     * @throws ReflectionException
     */
    protected function rules(array $rules)
    {
        foreach ($rules as $rule => $extension) {
            $this->rule($extension, is_string($rule) ?$rule:null);
        }
    }

    /**
     * extends the validator
     *
     * @param string $rule
     * @param string $method
     * @param mixed $extension
     * @param string|array $message
     * @param array $replacers
     * @return void
     */
    private function extendValidator(string $rule, string $method, string $extension, string|array $message, array $replacers): void
    {

        if (is_array($message)) {
            $message = reset($message);
        }

        Validator::$method(
            $rule,
            function ($attribute, $value, $parameters, $validator) use ($extension) {
                $rule = new $extension(...$parameters);

                if ($rule instanceof DataAwareRule) {
                    $rule->setData($validator->getData());
                }

                return $rule->passes($attribute, $value);
            },
            $message
        );
    }

    /**
     * adds a replacer
     *
     * @param string $rule
     * @param array $replacers
     * @return void
     */
    private function addReplacer(string $rule, array $replacers): void
    {
        Validator::replacer($rule,
            function ($message, $attribute, $rule, $parameters, $validator) use ($replacers) {

                foreach ($this->labelParameters($replacers, $parameters, $attribute, $validator->getData()) as $key => $value) {

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

        list($replacerKeys, $replacerClosures) = $this->parseReplacers($replacers);

        $replacers = array_combine($replacerKeys, $parameters);

        array_walk($replacers, function (&$currentParameter, $key) use ($replacerClosures, $parameters, $attribute, $data) {
            if (isset($replacerClosures[$key])) {
                $currentParameter = $replacerClosures[$key]($currentParameter, $attribute, $parameters, $data);
            }
        });

        return $replacers;
    }

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
}
