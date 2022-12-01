<?php

namespace Xgbnl\LaravelSwagger;

use Closure;
use Generator;
use Illuminate\Container\Container;
use Illuminate\Foundation\Http\FormRequest;
use Xgbnl\LaravelSwagger\Attributes\OAMultiPartForm;
use Xgbnl\LaravelSwagger\Attributes\OAName;
use Xgbnl\LaravelSwagger\Attributes\OAParam;
use Xgbnl\LaravelSwagger\Attributes\OAParameter;
use Xgbnl\LaravelSwagger\Attributes\OAProperty;
use Xgbnl\LaravelSwagger\Attributes\OARequestBody;
use Xgbnl\LaravelSwagger\Attributes\OAResponseBody;
use Xgbnl\LaravelSwagger\Attributes\OASchema;
use Xgbnl\LaravelSwagger\Attributes\OATag;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;

final class OperationBuilder
{
    private ReflectionMethod|ReflectionFunction $rf;

    private ?ReflectionClass $classRef = null;

    private array $parameters = [];

    private bool $withSecurity = false;

    private bool $isRestful = false;

    private ?string $baseResponseDataKey = null;

    private ?PhpDocParser $docParser = null;

    private string $requestType = 'application/json';

    private ?array $requestBody = null;

    /**
     * @throws ReflectionException
     */
    public function __construct(
        private string $method,
        public array $tags,
        public array $schemas,
        Closure|array $action,
        array $parameterNames,
    ) {
        if (is_array($action)) {
            if (count($action) > 1) {
                [$controller, $action] = $action;
            } else {
                $controller = $action[0];
                $action     = '__invoke';
            }

            $this->rf = new ReflectionMethod($controller, $action);

            if ($this->rf->class !== $controller) {
                $this->classRef = new ReflectionClass($controller);
            } else {
                $this->classRef = $this->rf->getDeclaringClass();
            }

            if (empty($this->rf->getAttributes(OARequestBody::class)) && empty($this->rf->getAttributes(OAMultiPartForm::class))) {
                $formReq = null;
                foreach ($this->rf->getParameters() as $methodParam) {
                    if (($paramName = $methodParam->getType()?->getName()) && is_subclass_of($paramName, FormRequest::class)) {
                        $formReq = $paramName;
                        break;
                    }
                }

                if (!$formReq && $this->classRef->hasMethod('checkFormRequest') && ('store' === $action || 'update' === $action)) {
                    $checkFormRequest = $this->classRef->getMethod('checkFormRequest');
                    $checkFormRequest->setAccessible(true);
                    $formReq = $checkFormRequest->invoke($this->classRef->newInstanceWithoutConstructor());
                    $checkFormRequest->setAccessible(false);

                    if (!class_exists($formReq)) {
                        $formReq = null;
                    }
                }

                if ($formReq || in_array($action, ['store', 'update'])) {
                    if (!$formReq && $this->classRef->hasProperty('formRequest')) {
                        $formReq = $this->classRef->getProperty('formRequest');
                        $formReq->setAccessible(true);

                        try {
                            $formReq = $formReq->getValue(Container::getInstance()->get($controller));
                        } catch (NotFoundExceptionInterface|ContainerExceptionInterface) {
                            $formReq = null;
                        }
                    }

                    if ($formReq) {
                        $defName = md5($formReq);

                        if (!isset($this->schemas[$defName]) && ($formReq = (new $formReq()))) {
                            $attrs = $formReq->attributes();
                            $rules = call_user_func([$formReq, 'rules']);

                            $parseParam = function (string $k, array|string $v, string $description) use ($rules, &$parseParam, $attrs) {
                                $parts = is_array($v) ? $v : explode('|', $v);

                                $prop = [];
                                foreach ($parts as $part) {
                                    if ('integer' === $part || 'string' === $part || 'boolean' === $part || 'date' === $part || 'file' === $part | 'array' === $part) {
                                        if ('file' === $part) {
                                            $this->requestType = 'multipart/form-data';
                                        } elseif ('array' === $part && isset($rules[$k . '.*'])) {
                                            $part = 'array<' . ($parseParam($k . '.*', $rules[$k . '.*'], $description)['type'] ?? 'integer') . '>';
                                        }

                                        $prop = $this->swapType($part, $prop);
                                    } elseif ('nullable' === $part) {
                                        $prop['required'] = false;
                                    } elseif (str_starts_with($part, 'in:')) {
                                        $enum = [];
                                        foreach (explode(',', substr($part, 3)) as $enumK) {
                                            $alias        = $k . '.in.' . $enumK;
                                            $enum[$enumK] = $attrs[$alias] ?? $enumK;
                                        }
                                        return $this->parseEnum($enum, $description);
                                    }
                                }

                                return $prop + ['name' => $k, 'description' => $description];
                            };

                            $props = [];
                            foreach ($rules as $k => $v) {
                                if (str_contains($k, '*') || in_array($k, $parameterNames)) {
                                    continue;
                                }

                                $props[$k] = $parseParam($k, $v, $attrs[$k] ?? $k);
                            }

                            if (($formReqDoc = (new ReflectionClass($formReq))->getDocComment()) !== false) {
                                $formReqTitle = PhpDocParser::parse($formReqDoc)->getDescription();
                            } else {
                                $formReqTitle = class_basename($formReq);
                            }

                            $this->schemas[$defName] = ['properties' => $props, 'type' => 'object', 'title' => $formReqTitle];
                        }

                        if (isset($this->schemas[$defName])) {
                            $this->requestBody = [
                                '$ref' => '#/components/schemas/' . $defName,
                            ];
                        }
                    }
                }
            }
        } else {
            $this->rf = new ReflectionFunction($action);
        }

        if ($docComment = $this->rf->getDocComment()) {
            $this->docParser = PhpDocParser::parse($docComment);
        }

        $this->withParameters($parameterNames);
    }

    public function withRestful(bool $isRestful): OperationBuilder
    {
        $this->isRestful = $isRestful;
        return $this;
    }

    public function withBaseResponse(?string $dataKey): OperationBuilder
    {
        $this->baseResponseDataKey = $dataKey;
        return $this;
    }

    public function withSecurity(array $middlewares, array $requires): OperationBuilder
    {
        foreach ($middlewares as $middleware) {
            if (in_array($middleware, $requires)) {
                $this->withSecurity = true;
                break;
            }
        }

        return $this;
    }

    public function withParameters(array $names): OperationBuilder
    {
        $names = array_flip($names);

        $parameters = [];
        foreach ($names as $k => $_) {
            $parameters['path-' . $k] = ['in' => 'path', 'name' => $k, 'schema' => ['type' => 'string'], 'required' => true];
        }

        foreach ($this->rf->getParameters() as $param) {
            $name = $param->getName();

            if (!isset($names[$name])) {
                continue;
            }

            if ($attr = ($param->getAttributes(OAParam::class)[0] ?? null)) {
                $arr = $this->parseProp($attr->newInstance(), $param->getType()->getName());

                $arr['in']   = 'path';
                $arr['name'] = $name;

                $parameters['path-' . $name] = $arr;
                unset($names[$name]);
            }
        }

        if (!empty($names)) {
            foreach ($this->docCommentTags('param') as $param) {
                if (isset($names[$param['name']])) {
                    $type = $param['type'];
                    unset($param['type']);

                    $param['schema'] = [
                        'type' => $type,
                    ];

                    $param['in']       = 'path';
                    $param['required'] = true;

                    $parameters['path-' . $param['name']] = $param;
                }
            }
        }

        if (str_contains($this->classRef, 'MonthController')) {
        }

        $this->parameters = $parameters;

        return $this;
    }

    /**
     * @throws ReflectionException
     */
    public function build(): array
    {
        $operation = [
            'responses' => [
                '400' => [
                    'description' => '参数有误',
                ],
                '5XX' => [
                    'description' => '未知错误',
                ],
            ],
        ];

        if ($this->isRestful) {
            $operation['responses'] += match ($this->method) {
                'post' => ['201' => ['description' => '创建成功']],
                'patch', 'put' => ['200' => ['description' => '保存成功']],
                'delete' => ['204' => ['description' => '删除成功']],
                default  => ['200' => ['description' => '操作成功']]
            };
        } else {
            $operation['responses']['200']['description']                 = '请求成功';
            $operation['responses']['200']['content']['application/json'] = [];
        }

        foreach ($this->rf->getAttributes() as $attr) {
            if (!class_exists($attr->getName())) {
                continue;
            }

            $inst = $attr->newInstance();

            switch (true) {
                case $inst instanceof OATag:
                    if (!isset($this->tags[$inst->name])) {
                        $this->tags[$inst->name] = (array) $inst;
                    }
                    $operation['tags'][] = $inst->name;
                    break;

                case $inst instanceof OAParameter:
                    $arr = $this->parseProp($inst, $inst->type);

                    if (!empty($arr['enum'])) {
                        $enum = $arr['enum'];
                        unset($arr['enum']);

                        $arr['schema'] = [
                            'type'  => 'array',
                            'items' => [
                                'type' => $arr['type'],
                                'enum' => $enum,
                            ],
                        ];
                    }

                    $this->parameters[$arr['in'] . '-' . $arr['name']] = $arr;
                    break;

                case $inst instanceof OARequestBody:
                    if ($schema = $this->parseSchema($inst->schema)) {
                        $operation['requestBody']['content'][$inst->type]['schema'] = $schema;
                        $operation['requestBody']['description']                    = '请求内容';
                    }
                    break;

                case $inst instanceof OAName && !isset($operation['summary']):
                    $operation = $operation + (array) $inst;
                    break;

                case $inst instanceof OAResponseBody:
                    if (is_array($inst->schemaOrRef)) {
                        if (is_string(array_key_first($inst->schemaOrRef))) {
                            $schema = $this->parseSchema($inst->schemaOrRef);
                        } else {
                            $schema = $this->parseSchema($inst->schemaOrRef[0], 'array');
                        }
                    } else {
                        $schema = $this->parseSchema($inst->schemaOrRef);
                    }

                    if ($schema) {
                        if ($this->isRestful && 'post' === $this->method) {
                            $statusCode = '201';
                        } else {
                            $statusCode = '200';
                        }

                        if ($this->baseResponseDataKey) {
                            $schema = [
                                'allOf' => [
                                    [
                                        '$ref' => '#/components/schemas/BaseResponse',
                                    ],
                                    [
                                        'type'       => 'object',
                                        'properties' => [
                                            'data' => $schema,
                                        ],
                                    ],
                                ],
                            ];
                        }

                        $operation['responses'][$statusCode]['content'][$inst->type]['schema'] = $schema;
                    }
                    break;
            }
        }

        if ($this->requestBody && empty($operation['requestBody']['content'][$this->requestType]['schema'])) {
            $operation['requestBody']['content'][$this->requestType]['schema'] = $this->requestBody;
        }

        if ($this->classRef) {
            if (empty($operation['tags']) && ($tag = ($this->classRef->getAttributes(OATag::class)[0] ?? null)?->newInstance())) {
                if (!isset($this->tags[$tag->name])) {
                    $this->tags[$tag->name] = (array) $tag;
                }
                $operation['tags'][] = $tag->name;
            }
        }

        if (empty($operation['summary']) && $this->docParser) {
            $operation['summary']     = $this->docParser->getShortDescription();
            $operation['description'] = $this->docParser->getDescription();
        }

        if (empty($operation['parameters'] = array_values($this->parameters))) {
            unset($operation['parameters']);
        }

        if ($this->withSecurity) {
            $operation['security'][] = ['api_key' => []];
        }

        return $operation;
    }

    private function docCommentTags(string $tagName, ?PhpDocParser $docParser = null): Generator
    {
        if (!$docParser) {
            $docParser = $this->docParser;
        }

        if (!$docParser) {
            return;
        }

        foreach ($docParser->getTags($tagName) as $tag) {
            if (strlen($name = $tag['name'] ?? '') < 1) {
                continue;
            }

            $attr = [
                'name'        => $name,
                'type'        => 'string',
                'description' => $tag['description'] ?? '',
            ];

            yield $this->swapType($props['type'] ?? 'string', $attr);
        }
    }

    /**
     * @throws ReflectionException
     */
    private function parseSchema(string|array $def, $outputType = 'object'): ?array
    {
        if (is_array($def)) {
            $schema = [
                'type'       => 'object',
                'properties' => [],
            ];

            foreach ($def as $prop => $item) {
                $type     = 'string';
                $required = false;
                $example  = null;

                if (is_array($item)) {
                    $second = $item[1] ?? null;
                    $third  = $item[2] ?? null;

                    if (null !== $second) {
                        if (is_bool($second)) {
                            $required = $second;
                        } else {
                            $type = $second;
                        }
                    }

                    if (null !== $third) {
                        if (is_bool($third)) {
                            $required = $third;
                        } else {
                            $type = $third;
                        }
                    }

                    if (isset($item['example'])) {
                        $example = $item['example'];
                    }

                    unset($item['example']);

                    $description = $item[0] ?? '';
                } else {
                    $description = $item;
                }

                if (!empty($item['enum']) && is_array($item['enum'])) {
                    $schema['properties'][$prop] = $this->parseEnum($item['enum'], $description);
                } else {
                    $schema['properties'][$prop] = $this->swapType($type, ['description' => $description]);
                }

                $type = $schema['properties'][$prop]['type'] ?? null;

                if ('object' === $type || 'array' === $type) {
                    if ('object' === $type) {
                        $schema['properties'][$prop]['title'] = $description;
                        unset($schema['properties'][$prop]['description']);
                    }

                    if (!empty($item['schema'])) {
                        $schema['properties'][$prop] = array_merge($schema['properties'][$prop], $this->parseSchema($item['schema'], $type));
                    }
                }

                if ($required) {
                    $schema['properties'][$prop]['required'] = true;
                }

                if (null !== $example) {
                    $schema['properties'][$prop]['example'] = $example;
                }
            }
        } else {
            switch ($def) {
                case 'string':
                case 'int':
                case 'integer':
                case 'bool':
                case 'boolean':
                case 'object':
                case 'array':
                    $schema = $this->swapType($def, []);
                    break;

                default:
                    $hashName = md5($def);

                    if (!isset($this->schemas[$hashName]) && class_exists($def)) {
                        $rfc = new ReflectionClass($def);

                        if ($docComment = $rfc->getDocComment()) {
                            $phpDoc = PhpDocParser::parse($docComment);

                            $props = [];
                            foreach ($this->docCommentTags('property', $phpDoc) as $prop) {
                                ['name' => $name, 'description' => $description] = $prop;

                                if (($i = strpos($description, '：')) && strpos($description, '=', $i)) {
                                    [$description, $enum] = explode('：', $description);

                                    $enums = [];
                                    foreach (array_map(fn ($enum) => array_map(fn ($e) => trim($e), explode('=', $enum)), explode(',', $enum)) as [$k, $v]) {
                                        $enums[$k] = $v;
                                    }

                                    $prop = $this->parseEnum($enums, $description);
                                }

                                $props[$name] = $prop;
                            }

                            foreach ($rfc->getAttributes(OAProperty::class) as $attr) {
                                $inst               = $attr->newInstance();
                                $props[$inst->name] = $this->parseProp($inst, $inst->type);
                            }

                            if (is_subclass_of($def, FormRequest::class) && $formReq = (new $def())) {
                                $attributes = $formReq->attributes();

                                foreach ($formReq->rules() as $k => $v) {
                                    if (str_contains($k, '*')) {
                                        continue;
                                    }

                                    $param = [
                                        'name'        => $k,
                                        'description' => $attributes[$k] ?? $k,
                                        'required'    => false,
                                    ];

                                    if (is_string($v)) {
                                        foreach (explode('|', $v) as $part) {
                                            if ('integer' === $part || 'string' === $part || 'boolean' === $part || 'date' === $part || 'file' === $part) {
                                                if ('file' === $part) {
                                                    $this->requestType = 'multipart/form-data';
                                                }
                                                $param['type'] = $part;
                                            } elseif ('nullable' === $part) {
                                                $param['required'] = false;
                                            } elseif (str_starts_with($part, 'in:')) {
                                                $enum = [];
                                                foreach (explode(',', substr($part, 3)) as $enumK) {
                                                    $alias        = $k . '.in.' . $enumK;
                                                    $enum[$enumK] = $attributes[$alias] ?? $enumK;
                                                }
                                                $param = $this->parseEnum($enum, $param['description']);
                                            }
                                        }
                                    }

                                    $props[$k] = $param;
                                }
                            }

                            $schema = [
                                'type'       => 'object',
                                'properties' => $props,
                            ];
                        } else {
                            $phpDoc = null;
                        }

                        foreach ($rfc->getAttributes(OASchema::class) as $attr) {
                            $inst   = $attr->newInstance();
                            $schema = $this->parseSchema($inst->schema, $inst->type);
                        }

                        if (!empty($schema)) {
                            $description              = explode("\n", $phpDoc?->getDescription())[0] ?? null;
                            $schema['title']          = $description ?: basename(str_replace('\\', '/', $rfc->name));
                            $this->schemas[$hashName] = $schema;
                        } else {
                            return null;
                        }
                    }

                    $schema = [
                        '$ref' => '#/components/schemas/' . $hashName,
                    ];
                    break;
            }
        }

        if ('array' === $outputType) {
            return [
                'type'  => 'array',
                'items' => $schema,
            ];
        }

        return $schema;
    }

    private function parseEnum(array $items, string $description = ''): array
    {
        if (empty($description)) {
            $lines = [];
        } else {
            $lines[] = ' > ' . $description . ':';
        }

        $type = null;

        $names = [];
        foreach ($items as $name => $desc) {
            if (null === $type && is_int($name)) {
                $type = 'integer';
            }

            $lines[] = sprintf(' * `%s` - %s', $name, $desc);
            $names[] = $name;
        }

        return [
            'description' => implode(PHP_EOL, $lines),
            'type'        => $type ?: 'string',
            'enum'        => $names,
        ];
    }

    private function parseProp($inst, $type): array
    {
        $arr = (array) $inst;
        unset($inst);

        if (null === $arr['example']) {
            unset($arr['example']);
        }

        $enum = $arr['enum'] ?? [];
        unset($arr['enum']);

        if (!empty($enum)) {
            return $this->parseEnum($enum, $arr['description']) + $arr;
        }

        $arr['schema'] = $this->swapType($type, $arr['schema'] ?? []);

        return $arr;
    }

    private function getType(string $type): string
    {
        return match ($type) {
            'int' => 'integer',
            'float', 'double' => 'number',
            'bool'   => 'boolean',
            'Carbon' => 'string',
            default  => $type,
        };
    }

    private function swapType(string $type, array $inst = []): array
    {
        preg_match('#array(<(\w+)>)?#', $type, $matches);

        if (count($matches) > 0) {
            $type = [
                'type'  => 'array',
                'items' => [
                    'type' => $this->getType($matches[2] ?? 'integer'),
                ],
            ];
        } else {
            $type = $this->getType($type);
        }

        if (is_array($type)) {
            return array_merge($inst, $type);
        }

        $inst['type'] = $type;

        return $inst;
    }
}
