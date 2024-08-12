<?php

declare(strict_types=1);

//Credits to https://github.com/bootstrapguru/dexor

namespace UseTheFork\Synapse\Tools\Concerns;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionParameter;
use UseTheFork\Synapse\Attributes\Description;

/**
 * Trait HasTools
 *
 * @author Hermann D. Schimpf (hschimpf)
 * Refer https://github.com/openai-php/client/issues/285#issuecomment-1883895076
 */
trait HasTools
{
    protected array $registered_tools = [];
    protected array $tools = [];


    /**
     * sets the memory type this agent will use.
     *
     * @return void
     */
    protected function registerTools(): array
    {
      return [];
    }

    /**
     * @throws ReflectionException
     */
    public function initializeTools(): void
    {
        foreach ($this->registerTools() as $tool) {

            $reflection = new ReflectionClass($tool);

            $tool_name = Str::snake(basename(str_replace('\\', '/', $tool::class)));

            if (! $reflection->hasMethod('handle')) {
                Log::warning(sprintf('Tool class %s has no "handle" method', $tool));

                continue;
            }

            $tool_definition = [
                'type' => 'function',
                'function' => ['name' => $tool_name],
            ];

            // set function description, if it has one
            if (! empty($descriptions = $reflection->getAttributes(Description::class))) {
                $tool_definition['function']['description'] = implode(
                    separator: "\n",
                    array: array_map(static fn ($td) => $td->newInstance()->value, $descriptions),
                );
            }

            if ($reflection->getMethod('handle')->getNumberOfParameters() > 0) {
                $tool_definition['function']['parameters'] = $this->parseToolParameters($reflection);
            }

            $this->registered_tools[$tool_name] = [
                'definition' => $tool_definition,
                'tool' => $tool,
            ];
        }
    }

    /**
     * @throws ReflectionException
     */
    public function call(string $tool_name, ?array $arguments = []): mixed
    {
        if (null === $tool_class = $this->registered_tools[$tool_name]) {
            return null;
        }
        $tool = $tool_class['tool'];

        $tool_class = new ReflectionClass($tool_class['tool']);
        $handle_method = $tool_class->getMethod('handle');

        $params = [];
        foreach ($handle_method->getParameters() as $parameter) {
            $parameter_description = $this->getParameterDescription($parameter);
            if (! array_key_exists($parameter->name, $arguments) && ! $parameter->isOptional() && ! $parameter->isDefaultValueAvailable()) {
                return sprintf('Parameter %s(%s) is required for the tool %s', $parameter->name, $parameter_description, $tool_name);
            }

            // check if parameter type is an Enum and add fetch a valid value
            if (($parameter_type = $parameter->getType()) !== null && ! $parameter_type->isBuiltin()) {
                if (enum_exists($parameter_type->getName())) {
                    $params[$parameter->name] = $parameter_type->getName()::tryFrom($arguments[$parameter->name]) ?? $parameter->getDefaultValue();

                    continue;
                }
            }

            $params[$parameter->name] = $arguments[$parameter->name] ?? $parameter->getDefaultValue();
        }

        return $tool->handle(...$params);
    }

    /**
     * @throws ReflectionException
     */
    private function parseToolParameters(ReflectionClass $tool): array
    {
        $parameters = ['type' => 'object'];

        if (count($method_parameters = $tool->getMethod('handle')->getParameters()) > 0) {
            $parameters['properties'] = [];
        }

        foreach ($method_parameters as $method_parameter) {
            $property = ['type' => $this->getToolParameterType($method_parameter)];

            // set property description, if it has one
            if (! empty($descriptions = $method_parameter->getAttributes(Description::class))) {
                $property['description'] = implode(
                    separator: "\n",
                    array: array_map(static fn ($pd) => $pd->newInstance()->value, $descriptions),
                );
            }

            // register parameter to the required properties list if it's not optional
            if (! $method_parameter->isOptional()) {
                $parameters['required'] ??= [];
                $parameters['required'][] = $method_parameter->getName();
            }

            // check if parameter type is an Enum and add it's valid values to the property
            if (($parameter_type = $method_parameter->getType()) !== null && ! $parameter_type->isBuiltin()) {
                if (enum_exists($parameter_type->getName())) {
                    $property['type'] = 'string';
                    $property['enum'] = array_column((new ReflectionEnum($parameter_type->getName()))->getConstants(), 'value');
                }
            }

            $parameters['properties'][$method_parameter->getName()] = $property;
        }

        return $parameters;
    }

    private function getToolParameterType(ReflectionParameter $parameter): string
    {
        if (null === $parameter_type = $parameter->getType()) {
            return 'string';
        }

        if (! $parameter_type->isBuiltin()) {
            return $parameter_type->getName();
        }

        return match ($parameter_type->getName()) {
            'bool' => 'boolean',
            'int' => 'integer',
            'float' => 'number',

            default => 'string',
        };
    }

    private function getParameterDescription(ReflectionParameter $parameter): string
    {
        $descriptions = $parameter->getAttributes(Description::class);
        if (! empty($descriptions)) {
            return implode("\n", array_map(static fn ($pd) => $pd->newInstance()->value, $descriptions));
        }

        return $this->getToolParameterType($parameter);
    }
}